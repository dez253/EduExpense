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

// Fetch user information (name) for the welcome message
$sql_user = "SELECT username FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();

// Fetch expense data for the charts
$sql_daily = "SELECT DATE(date) as day, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY DATE(date)";
$stmt_daily = $conn->prepare($sql_daily);
$stmt_daily->bind_param('i', $user_id);
$stmt_daily->execute();
$result_daily = $stmt_daily->get_result();

$daily_expenses = [];
if ($result_daily->num_rows > 0) {
    while ($row = $result_daily->fetch_assoc()) {
        $daily_expenses[] = $row;
    }
}

// Fetch monthly expenses
$sql_monthly = "SELECT MONTH(date) as month, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY MONTH(date)";
$stmt_monthly = $conn->prepare($sql_monthly);
$stmt_monthly->bind_param('i', $user_id);
$stmt_monthly->execute();
$result_monthly = $stmt_monthly->get_result();

$monthly_expenses = [];
if ($result_monthly->num_rows > 0) {
    while ($row = $result_monthly->fetch_assoc()) {
        $monthly_expenses[] = $row;
    }
}

// Fetch yearly expenses
$sql_yearly = "SELECT YEAR(date) as year, SUM(amount) as total FROM expenses WHERE user_id = ? GROUP BY YEAR(date)";
$stmt_yearly = $conn->prepare($sql_yearly);
$stmt_yearly->bind_param('i', $user_id);
$stmt_yearly->execute();
$result_yearly = $stmt_yearly->get_result();

$yearly_expenses = [];
if ($result_yearly->num_rows > 0) {
    while ($row = $result_yearly->fetch_assoc()) {
        $yearly_expenses[] = $row;
    }
}

$conn->close();

// Convert PHP arrays to JSON for Vue.js
$dailyData = json_encode($daily_expenses);
$monthlyData = json_encode($monthly_expenses);
$yearlyData = json_encode($yearly_expenses);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Finance Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/vue@2"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        /* Sidebar Styles */
        #sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100%;
            width: 200px;
            background-color: #343a40;
            padding-top: 20px;
        }

        #sidebar a {
            color: #fff;
            text-decoration: none;
            display: block;
            padding: 10px;
            font-size: 18px;
        }

        #sidebar a:hover {
            background-color: #007bff;
        }

        /* Main Content */
        #app {
            margin-left: 220px;
            padding: 20px;
        }

        .card {
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            height: 400px;
            width: 100%;
        }

        .dashboard {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        .dashboard .card {
            flex: 1 1 30%;
            min-width: 300px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard .card {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<div id="sidebar">
    <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i><span class="sidebar-text">Dashboard</span></a>
    <a href="expense.php"><i class="fas fa-plus-circle"></i><span class="sidebar-text">Add Expense</span></a>
    <a href="view_expenses.php"><i class="fas fa-eye"></i><span class="sidebar-text">View Expenses</span></a>
    <a href="profile.php"><i class="fas fa-user"></i><span class="sidebar-text">Profile</span></a>
    <a href="settings.php"><i class="fas fa-cogs"></i><span class="sidebar-text">Settings</span></a>
    <a href="budget.php"><i class="fas fa-wallet"></i><span class="sidebar-text">Budget</span></a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span class="sidebar-text">Logout</span></a>
</div>

<!-- Main Content -->
<div id="app" class="container">
    <h1>View Finance</h1>
    
    <div class="dashboard">
        <!-- Daily Expenses Line Chart -->
        <div class="card">
            <h2>Daily Expenses</h2>
            <div class="chart-container">
                <canvas id="dailyExpenseChart"></canvas>
            </div>
        </div>

        <!-- Monthly Expenses Bar Chart -->
        <div class="card">
            <h2>Monthly Expenses</h2>
            <div class="chart-container">
                <canvas id="monthlyExpenseChart"></canvas>
            </div>
        </div>

        <!-- Yearly Expenses Doughnut Chart -->
        <div class="card">
            <h2>Yearly Expenses</h2>
            <div class="chart-container">
                <canvas id="yearlyExpenseChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
    // Fetch data from PHP and prepare for chart rendering
    new Vue({
        el: '#app',
        data: {
            dailyExpenses: <?php echo $dailyData; ?>,
            monthlyExpenses: <?php echo $monthlyData; ?>,
            yearlyExpenses: <?php echo $yearlyData; ?>
        },
        mounted() {
            this.renderCharts();
        },
        methods: {
            renderCharts() {
                // Daily Expenses Line Chart
                new Chart(document.getElementById('dailyExpenseChart'), {
                    type: 'line',
                    data: {
                        labels: this.dailyExpenses.map(e => e.day),
                        datasets: [{
                            label: 'Daily Expenses (UGX)',
                            data: this.dailyExpenses.map(e => parseFloat(e.total)),
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.2)',
                            tension: 0.2
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            x: { title: { display: true, text: 'Date' } },
                            y: { title: { display: true, text: 'Amount (UGX)' } }
                        }
                    }
                });

                // Monthly Expenses Bar Chart
                new Chart(document.getElementById('monthlyExpenseChart'), {
                    type: 'bar',
                    data: {
                        labels: this.monthlyExpenses.map(e => `Month ${e.month}`),
                        datasets: [{
                            label: 'Monthly Expenses (UGX)',
                            data: this.monthlyExpenses.map(e => parseFloat(e.total)),
                            backgroundColor: '#28a745'
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            x: { title: { display: true, text: 'Month' } },
                            y: { title: { display: true, text: 'Amount (UGX)' } }
                        }
                    }
                });

                // Yearly Expenses Doughnut Chart
                new Chart(document.getElementById('yearlyExpenseChart'), {
                    type: 'doughnut',
                    data: {
                        labels: this.yearlyExpenses.map(e => e.year),
                        datasets: [{
                            label: 'Yearly Expenses (UGX)',
                            data: this.yearlyExpenses.map(e => parseFloat(e.total)),
                            backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545']
                        }]
                    },
                    options: {
                        responsive: true
                    }
                });
            }
        }
    });
</script>

</body>
</html>
