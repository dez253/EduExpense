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
    header("Location: login.php");  // Redirect to login page if not logged in
    exit();
}

// Get the logged-in user's ID
$user_id = $_SESSION['user_id'];

// Handle JSON data request (e.g., for fetching chart data)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetch_data']) && $_GET['fetch_data'] === '1') {
    // Fetch weekly expenses (last 7 days)
    $weeklyExpensesQuery = "
        SELECT DATE(date) AS day, SUM(amount) AS total
        FROM expenses 
        WHERE user_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(date)
    ";
    $stmt = $conn->prepare($weeklyExpensesQuery);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $weeklyResult = $stmt->get_result();
    $weeklyExpenses = $weeklyResult->fetch_all(MYSQLI_ASSOC);

    // Fetch monthly expenses (current year)
    $monthlyExpensesQuery = "
        SELECT MONTH(date) AS month, SUM(amount) AS total
        FROM expenses 
        WHERE user_id = ? AND YEAR(date) = YEAR(CURDATE())
        GROUP BY MONTH(date)
    ";
    $stmt = $conn->prepare($monthlyExpensesQuery);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $monthlyResult = $stmt->get_result();
    $monthlyExpenses = $monthlyResult->fetch_all(MYSQLI_ASSOC);

    // Fetch all expenses (grouped by date for yearly breakdown)
    $allExpensesQuery = "
        SELECT date, amount
        FROM expenses 
        WHERE user_id = ?
    ";
    $stmt = $conn->prepare($allExpensesQuery);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $allResult = $stmt->get_result();
    $allExpenses = $allResult->fetch_all(MYSQLI_ASSOC);

    // Output JSON data for chart.js
    header('Content-Type: application/json');
    echo json_encode([
        'weeklyExpenses' => $weeklyExpenses,
        'monthlyExpenses' => $monthlyExpenses,
        'allExpenses' => $allExpenses
    ]);
    exit();
}

// Process form submission for adding a new expense
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['description'], $_POST['category_description'], $_POST['amount'], $_POST['date'])) {
    $description = $_POST['description'];
    $category_description = $_POST['category_description'];
    $amount = $_POST['amount'];
    $date = $_POST['date']; // Getting the date input

    // Insert expense into expenses table with manually entered date and category description
    $stmt = $conn->prepare("INSERT INTO expenses (user_id, description, category_description, amount, date) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issds', $user_id, $description, $category_description, $amount, $date);
    $stmt->execute();
    $stmt->close();

    // Redirect to avoid resubmission on refresh
    header("Location: expense.php");
    exit();
}

// Fetch expense data for table display
$sql = "SELECT description, category_description, SUM(amount) AS amount, date 
        FROM expenses 
        WHERE user_id = ? 
        GROUP BY description, category_description, date";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
    <!-- Include Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
        }

        #sidebar {
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            width: 250px;
            background-color: #343a40;
            color: white;
            padding-top: 20px;
        }

        #sidebar a {
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            font-size: 16px;
        }

        #sidebar a:hover {
            background-color: #007bff;
        }

        #app {
            margin-left: 270px;
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-top: 20px;
        }

        .form-container {
            margin-top: 30px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        table {
            width: 100%;
            margin-top: 20px;
        }

        th, td {
            text-align: left;
            padding: 8px;
        }

        th {
            background-color: #f2f2f2;
        }

        td {
            background-color: #f9f9f9;
        }
    </style>
</head>

<body>
    <div id="sidebar">
        <a href="dashboard.php">Dashboard</a>
        <a href="expense.php">Add Expense</a>
        <a href="view_expenses.php">View Expenses</a>
        <a href="logout.php">Logout</a>
    </div>

    <div id="app" class="container">
        <h3 class="my-4">Add New Expense</h3>
        <div class="form-container">
            <form action="expense.php" method="POST">
                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <input type="text" class="form-control" id="description" name="description" required>
                </div>
                <div class="mb-3">
                    <label for="category_description" class="form-label">Category Description</label>
                    <input type="text" class="form-control" id="category_description" name="category_description" required>
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label">Amount</label>
                    <input type="number" class="form-control" id="amount" name="amount" required step="0.01">
                </div>
                <div class="mb-3">
                    <label for="date" class="form-label">Date</label>
                    <input type="date" class="form-control" id="date" name="date" required>
                </div>
                <button type="submit" class="btn btn-primary">Add Expense</button>
            </form>
        </div>

        <h3 class="my-4">Expenses Overview</h3>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Category Description</th>
                    <th>Total Amount</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($expenses as $expense) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($expense['description']); ?></td>
                        <td><?php echo htmlspecialchars($expense['category_description']); ?></td>
                        <td><?php echo number_format($expense['amount'], 2); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($expense['date'])); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</body>

</html>
