<?php
// Start session
session_start();

// Database connection
$host = 'localhost';
$dbname = 'finance_student';
$username = 'root';
$password = '';
$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login,logout page.php");
    exit();
}

// Session timeout (30 minutes of inactivity)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login,logout page.php");
    exit();
}
$_SESSION['last_activity'] = time();

// Get the logged-in user's ID
$user_id = $_SESSION['user_id'];

// Handle form submission for adding or updating an expense
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['description'], $_POST['category_description'], $_POST['amount'], $_POST['date'])) {
    $description = htmlspecialchars(trim($_POST['description']));
    $category_description = htmlspecialchars(trim($_POST['category_description']));
    $amount = floatval($_POST['amount']);
    $date = htmlspecialchars($_POST['date']);

    if (isset($_POST['edit_id']) && !empty($_POST['edit_id'])) {
        // Update existing expense
        $edit_id = intval($_POST['edit_id']);
        $stmt = $conn->prepare("UPDATE expenses SET description = ?, category_description = ?, amount = ?, date = ? WHERE id = ? AND user_id = ?");
        if (!$stmt) {
            die("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param('ssdsii', $description, $category_description, $amount, $date, $edit_id, $user_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new expense
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, description, category_description, amount, date) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
            die("Error preparing statement: " . $conn->error);
        }
        $stmt->bind_param('issds', $user_id, $description, $category_description, $amount, $date);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: expense.php");
    exit();
}

// Handle delete action
if (isset($_GET['delete'])) {
    $expense_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $expense_id, $user_id);
    $stmt->execute();
    $stmt->close();
    header("Location: expense.php");
    exit();
}

// Handle edit action
$edit_expense = null;
if (isset($_GET['edit'])) {
    $expense_id = intval($_GET['edit']);
    $stmt = $conn->prepare("SELECT id, description, category_description, amount, date FROM expenses WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $expense_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_expense = $result->fetch_assoc();
    $stmt->close();
}

// Fetch expenses for the logged-in user
$stmt = $conn->prepare("SELECT id, description, category_description, amount, date FROM expenses WHERE user_id = ? ORDER BY date DESC");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$expenses = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch the user's budget
$budget_stmt = $conn->prepare("SELECT source, amount FROM budgets WHERE user_id = ?");
$budget_stmt->bind_param('i', $user_id);
$budget_stmt->execute();
$budget_result = $budget_stmt->get_result();
$category_budgets = [];
while ($row = $budget_result->fetch_assoc()) {
    $category_budgets[$row['source']] = $row['amount'];
}
$budget_stmt->close();

// Calculate total expenses
$total_expenses = array_sum(array_column($expenses, 'amount'));

// After fetching the user's budget, calculate the remaining balance
$total_budget = array_sum($category_budgets); // Sum of all category budgets
$remaining_balance = $total_budget - $total_expenses; // Remaining balance after expenses


// Disconnect from the database
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
     body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f8f9fa; /* Light grayish tone */
}
/* Sidebar Styles */
#sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 80px;
    height: 100vh;
    background-color: #2E573F; /* Dark green background */
    color: white; /* White text */
    padding-top: 20px;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
    transition: width 0.3s ease;
}

#sidebar:hover {
    width: 250px; /* Expanded width on hover */
}

/* Sidebar Links */
#sidebar a {
    color: white; /* White text for the links */
    padding: 15px;
    text-decoration: none;
    display: flex;
    align-items: center;
    font-size: 16px;
    width: 100%;
    text-align: left;
}

/* Sidebar Icons */
#sidebar i {
    margin-right: 10px;
    font-size: 18px;
}

/* Sidebar Text */
#sidebar .sidebar-text {
    display: inline-block;
    transition: opacity 0.3s ease;
}

#sidebar:not(:hover) .sidebar-text {
    opacity: 0; /* Hide text when sidebar is collapsed */
}


/* Main Container */
.container {
    margin-left: 270px; /* Space for expanded sidebar */
    padding: 20px;
}

/* Welcome Section */
.welcome-section {
    margin-top: 20px;
    padding: 10px 20px;
    background-color: #007bff; /* Blue */
    color: white;
    border-radius: 5px;
    transition: opacity 0.5s ease-out;
}

/* Dashboard Layout */
.dashboard {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-top: 30px;
}

/* Expense Card Section */
.expense-card-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 30px;
}

.expense-card {
    background: #ffffff; /* White */
    padding: 15px;
    margin: 10px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    width: 100%;
    max-width: 250px;
}

.expense-card:hover {
    box-shadow: 0 6px 10px rgba(0, 0, 0, 0.2);
}

.expense-card h5 {
    font-size: 18px;
    color: #BC705B; /* Warm brownish tone */
    margin-bottom: 10px;
}

.expense-card p {
    font-size: 14px;
    color: #666;
}

/* Button Styles */
button.custom-btn {
    background-color: #BC705B; /* Warm brownish tone */
    border: 1px solid #BC705B;
    color: #fff; /* White text */
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 4px;
}

button.custom-btn:hover {
    background-color: #8B171B; /* Darker red tone */
    border-color: #8B171B;
}

/* Balance Info Section */
.balance-info {
    background-color: #28a745; /* Green */
    color: white; /* White text */
    padding: 15px;
    border-radius: 5px;
    text-align: center;
}

/* Profile Section */
.profile-card {
    background-color: #ffffff; /* White */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Text Color for Headings */
h1, h2, h3, h4, h5, h6 {
    color: #BC705B; /* Warm brownish tone */
}

/* Responsive Design */
@media (max-width: 768px) {
    #sidebar {
        width: 60px; /* Smaller sidebar for smaller screens */
    }

    .container {
        margin-left: 70px; /* Adjust for smaller sidebar */
    }

    .dashboard {
        flex-direction: column;
        align-items: center;
    }

    .expense-card {
        max-width: 300px;
    }
}

@media (max-width: 340px) {
    #sidebar {
        width: 100%; /* Full width for very small screens */
        position: relative;
        height: auto;
    }

    .container {
        margin-left: 0;
    }

    .dashboard {
        flex-direction: column;
        gap: 15px;
    }

    .expense-card {
        max-width: 100%;
    }
}
</style>
</head>

<body>
<div id="sidebar">
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span class="sidebar-text">Dashboard</span></a>
    <a href="expense.php"><i class="fas fa-plus-circle"></i><span class="sidebar-text">Add Expense</span></a>
    <a href="view_expenses.php"><i class="fas fa-eye"></i><span class="sidebar-text">View Expenses</span></a>
    <a href="profile.php"><i class="fas fa-user"></i><span class="sidebar-text">Profile</span></a>
    <a href="settings.php"><i class="fas fa-cogs"></i><span class="sidebar-text">Settings</span></a>
    <a href="budget.php"><i class="fas fa-wallet"></i><span class="sidebar-text">Budget</span></a> <!-- New Budget Section -->
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span class="sidebar-text">Logout</span></a>
</div>


    <div class="container">
        <h1>Expense Tracker</h1>

        <div>
            <h2>Budget: UGX <?php echo number_format(array_sum($category_budgets), 0); ?> | 
            Expenses: UGX <?php echo number_format($total_expenses, 0); ?>
                Balance: UGX <?php echo number_format($remaining_balance, 0); ?></h2>
        </div>

        <!-- Updated Form -->
        <form action="expense.php" method="POST" class="p-4 mb-4 bg-white rounded shadow-sm">
            <h4 class="mb-3 text-primary"><?php echo $edit_expense ? 'Edit Expense' : 'Add New Expense'; ?></h4>
            
            <?php if ($edit_expense): ?>
                <input type="hidden" name="edit_id" value="<?php echo $edit_expense['id']; ?>">
            <?php endif; ?>

            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <input type="text" id="description" name="description" class="form-control" placeholder="Enter expense description" required value="<?php echo $edit_expense['description'] ?? ''; ?>">
            </div>

            <div class="mb-3">
                <label for="category_description" class="form-label">Category</label>
                <input type="text" id="category_description" name="category_description" class="form-control" placeholder="Enter expense category" required value="<?php echo $edit_expense['category_description'] ?? ''; ?>">
            </div>

            <div class="mb-3">
                <label for="amount" class="form-label">Amount (UGX)</label>
                <input type="number" id="amount" name="amount" class="form-control" placeholder="Enter amount" required value="<?php echo $edit_expense['amount'] ?? ''; ?>">
            </div>

            <div class="mb-3">
                <label for="date" class="form-label">Date</label>
                <input type="date" id="date" name="date" class="form-control" required value="<?php echo $edit_expense['date'] ?? ''; ?>">
            </div>
            <!-- Button to generate report -->
<div class="mb-3">
    <a href="generate_report.php" class="btn btn-success">Generate Report (PDF)</a>
</div>


            <button type="submit" class="btn btn-primary w-100">
                <?php echo $edit_expense ? 'Update Expense' : 'Add Expense'; ?>
            </button>
        </form>

        <!-- Display Expenses -->
        <div>
            <?php if (!empty($expenses)): ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $expense): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($expense['description']); ?></td>
                                <td><?php echo htmlspecialchars($expense['category_description']); ?></td>
                                <td><?php echo number_format($expense['amount'], 0); ?></td>
                                <td><?php echo htmlspecialchars($expense['date']); ?></td>
                                <td>
                                    <a href="expense.php?edit=<?php echo $expense['id']; ?>" class="btn btn-sm btn-warning">Edit</a>
                                    <a href="expense.php?delete=<?php echo $expense['id']; ?>" class="btn btn-sm btn-danger">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No expenses found. Add your first expense!</p>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
