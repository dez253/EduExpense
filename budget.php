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

// Redirect to login if user is not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get logged-in user's ID
$user_id = $_SESSION['user_id'];

// Fetch categories from category_description table
$sql_categories = "SELECT category_description FROM expenses";
$categories_result = $conn->query($sql_categories);

$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row['category_description'];
}

// Handle form submission for adding a budget
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['source'], $_POST['amount'], $_POST['spending_limit'], $_POST['date_assigned'])) {
    $source = $_POST['source'];
    $amount = $_POST['amount'];
    $spending_limit = $_POST['spending_limit'];
    $date_assigned = $_POST['date_assigned'];

    // Insert budget into database
    $stmt = $conn->prepare("INSERT INTO budgets (user_id, amount, spending_limit, source, date_assigned) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('idsss', $user_id, $amount, $spending_limit, $source, $date_assigned);
    $stmt->execute();
    $stmt->close();

    header("Location: budget.php");
    exit();
}

// Fetch budgets and expenses data
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
$total_budget = 0;
$total_expenses = 0;
$chart_data = [];

while ($row = $result->fetch_assoc()) {
    $budgets[] = $row;
    $total_budget += $row['amount'];
    $total_expenses += $row['total_expenses']; // Calculate total expenses for the user
    $chart_data[] = [
        'source' => $row['source'],
        'amount' => $row['amount'],
        'expenses' => $row['total_expenses']
    ];
}

// Calculate the balance between the total budget and total expenses
$budget_balance = $total_budget - $total_expenses;

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgeting</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8f9fa;
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
            margin-left: 270px;
            padding: 20px;
        }

        /* Heading Styles */
        h1, h2, h3 {
            color: #BC705B;
        }

        /* Chart Containers */
        .chart-container {
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            box-sizing: border-box;
            width: 48%;
            height: 300px;
        }

        /* Button Styling */
        .btn {
            margin-right: 10px;
        }

        /* Flexbox for Layout */
        .dashboard {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: nowrap;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            #sidebar {
                width: 60px;
            }

            .container {
                margin-left: 70px;
            }

            /* Stack charts on smaller screens */
            .dashboard {
                flex-direction: column;
                align-items: center;
            }

            .chart-container {
                width: 90%;
                margin-bottom: 20px;
                height: auto;
            }
        }

        @media (max-width: 340px) {
            #sidebar {
                width: 100%;
                position: relative;
                height: auto;
            }

            .container {
                margin-left: 0;
            }

            /* Stack charts on very small screens */
            .chart-container {
                width: 100%;
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
    <a href="budget.php"><i class="fas fa-wallet"></i><span class="sidebar-text">Budget</span></a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span class="sidebar-text">Logout</span></a>
</div>

<div class="container">
    <h1>Set Your Budget</h1>
    <form action="budget.php" method="POST" class="mb-4">
        <div class="mb-3">
            <label for="source" class="form-label">Category</label>
            <select class="form-control" id="source" name="source" required>
                <option value="" disabled selected>Select a category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category; ?>"><?php echo $category; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="amount" class="form-label">Assigned Budget Amount (UGX)</label>
            <input type="number" class="form-control" id="amount" name="amount" required>
        </div>
        <div class="mb-3">
            <label for="spending_limit" class="form-label">Spending Limit</label>
            <input type="number" class="form-control" id="spending_limit" name="spending_limit" required>
        </div>
        <div class="mb-3">
            <label for="date_assigned" class="form-label">Date Assigned</label>
            <input type="date" class="form-control" id="date_assigned" name="date_assigned" required>
        </div>
        <button type="submit" class="btn btn-primary">Add Budget</button>
    </form>

    <h2>Budget Balance</h2>
    <p>Current Budget: UGX <?php echo number_format($total_budget, 2); ?></p>
    <p>Total Expenses: UGX <?php echo number_format($total_expenses, 2); ?></p>
    <p>Balance: UGX <?php echo number_format($budget_balance, 2); ?></p>

    <div class="dashboard">
        <div class="chart-container">
            <canvas id="budgetChart"></canvas>
        </div>
        <div class="chart-container">
            <canvas id="expenseChart"></canvas>
        </div>
    </div>
</div>

<script>
// Chart.js script for rendering charts
const budgetChart = new Chart(document.getElementById('budgetChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($chart_data, 'source')); ?>,
        datasets: [{
            label: 'Assigned Budget (UGX)',
            data: <?php echo json_encode(array_column($chart_data, 'amount')); ?>,
            backgroundColor: '#4e73df',
            borderColor: '#4e73df',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

const expenseChart = new Chart(document.getElementById('expenseChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($chart_data, 'source')); ?>,
        datasets: [{
            label: 'Total Expenses (UGX)',
            data: <?php echo json_encode(array_column($chart_data, 'expenses')); ?>,
            backgroundColor: '#ff5733',
            borderColor: '#ff5733',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>

</body>
</html>
