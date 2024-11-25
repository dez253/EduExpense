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

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");  // Redirect to login page if not logged in
    exit();
}

// Get the logged-in user's ID
$user_id = $_SESSION['user_id'];

// Handle form submission for adding a new budget
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['source'], $_POST['amount'], $_POST['spending_limit'], $_POST['date_assigned'])) {
    $source = $_POST['source'];
    $amount = $_POST['amount'];
    $spending_limit = $_POST['spending_limit'];
    $date_assigned = $_POST['date_assigned'];

    // Insert budget into the database
    $stmt = $conn->prepare("INSERT INTO budgets (user_id, amount, spending_limit, source, date_assigned) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('idsss', $user_id, $amount, $spending_limit, $source, $date_assigned);
    $stmt->execute();
    $stmt->close();

    // Redirect to avoid resubmission on refresh
    header("Location: budget.php");
    exit();
}

// Fetch user's budgets and their total expenses for comparison
$sql = "SELECT b.id, b.source, b.amount, b.spending_limit, b.date_assigned, 
               IFNULL(SUM(e.amount), 0) AS total_expenses
        FROM budgets b
        LEFT JOIN expenses e ON e.category_description = b.source AND e.user_id = b.user_id
        WHERE b.user_id = ?
        GROUP BY b.id";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$budgets = [];
$total_budget = 0; // Variable to store total budget

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $budgets[] = $row;
        $total_budget += $row['amount']; // Add the amount to the total budget
    }
}

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgeting</title>
    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f9;
        }

        /* Sidebar Style */
        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 80px; /* Initial collapsed width */
            height: 100vh;
            background-color: #343a40;
            color: white;
            padding-top: 20px;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
            transition: width 0.3s ease;
        }

        /* Sidebar hover effect - expand to show text */
        #sidebar:hover {
            width: 250px; /* Expanded width */
        }

        /* Sidebar Links */
        #sidebar a {
            color: white;
            padding: 15px;
            text-decoration: none;
            display: flex;
            align-items: center;
            font-size: 18px;
            width: 100%;
            text-align: left;
            transition: all 0.3s ease;
        }

        #sidebar a:hover {
            background-color: #007bff;
        }

        #sidebar i {
            margin-right: 10px;
            font-size: 20px;
        }

        #sidebar .sidebar-text {
            display: inline-block;
            transition: opacity 0.3s ease;
        }

        #sidebar:not(:hover) .sidebar-text {
            opacity: 0;
        }

        /* Main Content Area */
        .container {
            margin-left: 270px;
            padding: 20px;
        }

        /* Welcome Section */
        .welcome-section {
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border-radius: 5px;
            transition: opacity 0.5s ease-out;
        }

        .welcome-section.hidden {
            opacity: 0;
            pointer-events: none;
        }

        .dashboard {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 20px;
            max-width: 1000px;  /* Reduced max width */
            margin-top: 30px;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            flex: 1;
            min-width: 280px;  /* Reduced card size */
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .chart-container {
            position: relative;
            width: 100%;
            max-width: 400px;
        }

        h1 {
            margin: 0;
            padding: 20px 0;
            color: #333;
        }

        .expense-card {
            background: #fff;
            padding: 15px;
            margin: 10px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 250px;
        }

        .expense-card h5 {
            font-size: 18px;
            margin-bottom: 10px;
        }

        .expense-card p {
            font-size: 14px;
            color: #666;
        }

        button {
            background-color: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            margin-top: 10px;
        }

        button:hover {
            background-color: #45a049;
        }

        @media (max-width: 340px) {
            #sidebar {
                width: 50%;
                position: relative;
                height: auto;
            }
            .container {
                margin-left: 0;
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
    <h1>Set Your Budget</h1>

    <form action="budget.php" method="POST">
        <div class="mb-3">
            <label for="source" class="form-label">Category</label>
            <input type="text" class="form-control" id="source" name="source" required>
        </div>
        <div class="mb-3">
            <label for="amount" class="form-label">Assigned Budget Amount (UGX)</label>
            <input type="number" class="form-control" id="amount" name="amount" required>
        </div>
        <div class="mb-3">
            <label for="spending_limit" class="form-label">Spending Limit (UGX)</label>
            <input type="number" class="form-control" id="spending_limit" name="spending_limit" required>
        </div>
        <div class="mb-3">
            <label for="date_assigned" class="form-label">Date Assigned</label>
            <input type="date" class="form-control" id="date_assigned" name="date_assigned" required>
        </div>
        <button type="submit">Save Budget</button>
    </form>

    <div class="welcome-section">
        <h2>Total Budget: UGX <?php echo number_format($total_budget); ?></h2>
    </div>

    <div class="dashboard">
        <?php foreach ($budgets as $budget) { ?>
            <div class="expense-card">
                <h5><?php echo $budget['source']; ?></h5>
                <p>Assigned: UGX <?php echo number_format($budget['amount']); ?></p>
                <p>Limit: UGX <?php echo number_format($budget['spending_limit']); ?></p>
                <p>Expenses: UGX <?php echo number_format($budget['total_expenses']); ?></p>
                <p>Remaining: UGX <?php echo number_format($budget['amount'] - $budget['total_expenses']); ?></p>
            </div>
        <?php } ?>
    </div>
</div>
</body>
</html>
