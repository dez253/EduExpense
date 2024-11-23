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
