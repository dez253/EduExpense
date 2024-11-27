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

// Fetch expense data for comparison
$sql_expenses = "SELECT id, description, amount FROM expenses WHERE user_id = ?";
$stmt_expenses = $conn->prepare($sql_expenses);
$stmt_expenses->bind_param('i', $user_id);
$stmt_expenses->execute();
$result_expenses = $stmt_expenses->get_result();

$expenses = [];
if ($result_expenses->num_rows > 0) {
    while ($row = $result_expenses->fetch_assoc()) {
        $expenses[] = $row;
    }
}

$conn->close();

// Convert PHP arrays to JSON for Vue.js
$dailyData = json_encode($daily_expenses);
$monthlyData = json_encode($monthly_expenses);
$expensesData = json_encode($expenses);
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
     /* Global Styles */
/* Global Styles */
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #f8f9fa; /* Light gray background */
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


/* Main Content Container */
.container {
    margin-left: 270px; /* Adjust for expanded sidebar */
    padding: 20px;
}

/* Welcome Section */
.welcome-section {
    margin-top: 20px;
    padding: 10px 20px;
    background-color: #007bff; /* Blue background */
    color: white;
    border-radius: 5px;
    transition: opacity 0.5s ease-out;
}

/* Dashboard Layout */
.dashboard {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-top: 30px;
}

/* Expense Card Styles */
.expense-card-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

.expense-card {
    background: #ffffff; /* White background */
    padding: 15px;
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

/* Chart Container */
.chart-container {
    position: relative;
    flex: 1; /* Allows chart to grow in size */
    height: 400px; /* Fixed height for charts */
    padding: 20px;
    background-color: #f8f9fa; /* Subtle background */
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Place multiple charts on the same line */
.chart-row {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
}

/* Button Styles */
button.custom-btn {
    background-color: #BC705B; /* Warm brownish tone */
    border: 1px solid #BC705B;
    color: #fff;
    padding: 10px 20px;
    cursor: pointer;
    border-radius: 4px;
}

button.custom-btn:hover {
    background-color: #8B171B; /* Darker red tone */
    border-color: #8B171B;
}

/* Profile Card */
.profile-card {
    background-color: #ffffff; /* White background */
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

/* Text Colors for Headings */
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

        <!-- Expense Comparison Section -->
        <div class="card">
            <h2>Expense Comparison</h2>
            <div>
                <label for="expense1">Select First Expense:</label>
                <select id="expense1" v-model="firstExpenseId">
                    <option v-for="expense in expenses" :value="expense.id">{{ expense.description }}</option>
                </select>
                <br><br>
                <label for="expense2">Select Second Expense:</label>
                <select id="expense2" v-model="secondExpenseId">
                    <option v-for="expense in expenses" :value="expense.id">{{ expense.description }}</option>
                </select>
                <br><br>
                <button class="btn btn-primary" @click="updateComparison">Compare Expenses</button>
            </div>
            <div class="chart-container">
                <canvas id="comparisonBarChart"></canvas>
            </div>
            
        </div>
    </div>
</div>

<script>
    new Vue({
        el: '#app',
        data: {
            dailyExpenses: <?php echo $dailyData; ?>,
            monthlyExpenses: <?php echo $monthlyData; ?>,
            expenses: <?php echo $expensesData; ?>,
            firstExpenseId: null,
            secondExpenseId: null,
            firstExpense: null,
            secondExpense: null
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
                            borderWidth: 2,
                            fill: false
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
                            backgroundColor: '#28a745',
                            borderColor: '#218838',
                            borderWidth: 1
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
            },

            updateComparison() {
                // Check if both expenses are selected
                if (this.firstExpenseId && this.secondExpenseId) {
                    this.firstExpense = this.expenses.find(expense => expense.id == this.firstExpenseId);
                    this.secondExpense = this.expenses.find(expense => expense.id == this.secondExpenseId);
                    
                    // Render the comparison chart
                    new Chart(document.getElementById('comparisonBarChart'), {
                        type: 'bar',
                        data: {
                            labels: [this.firstExpense.description, this.secondExpense.description],
                            datasets: [{
                                label: 'Expense Comparison (UGX)',
                                data: [this.firstExpense.amount, this.secondExpense.amount],
                                backgroundColor: ['#007bff', '#ff5733'],
                                borderColor: ['#0056b3', '#c0392b'],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                y: { title: { display: true, text: 'Amount (UGX)' } }
                            }
                        }
                    });
                }
            }
        }
    });
</script>
</body>
</html>
