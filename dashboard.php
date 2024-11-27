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
$user = $user_result->fetch_assoc();

// Fetch expense data for the Doughnut chart
$sql = "SELECT description, category_description, date, SUM(amount) as amount 
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
// Fetch expenses data for Category Breakdown
$sql_category = "SELECT category_description, SUM(amount) AS total_amount 
                 FROM expenses 
                 WHERE user_id = ? 
                 GROUP BY category_description";
$stmt_category = $conn->prepare($sql_category);
$stmt_category->bind_param('i', $user_id);
$stmt_category->execute();
$category_result = $stmt_category->get_result();

$categories = [];
while ($row = $category_result->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch expenses data for Expense Trend (group by month)
$sql_trend = "SELECT DATE_FORMAT(date, '%Y-%m') AS month, SUM(amount) AS total_amount 
              FROM expenses 
              WHERE user_id = ? 
              GROUP BY month ORDER BY month";
$stmt_trend = $conn->prepare($sql_trend);
$stmt_trend->bind_param('i', $user_id);
$stmt_trend->execute();
$trend_result = $stmt_trend->get_result();

$trend_data = [];
while ($row = $trend_result->fetch_assoc()) {
    $trend_data[] = $row;
}

// Fetch budget data for the progress bar
$sql_budget = "SELECT amount, spending_limit FROM budgets WHERE user_id = ?";
$stmt_budget = $conn->prepare($sql_budget);
$stmt_budget->bind_param('i', $user_id);
$stmt_budget->execute();
$budget_result = $stmt_budget->get_result();
$budget = $budget_result->fetch_assoc();


$conn->close();

// Convert PHP array to JSON for Vue.js
$expensesData = json_encode($expenses);

// Calculate budget progress
$budgetAmount = $budget ? $budget['amount'] : 0;
$spendingLimit = $budget ? $budget['spending_limit'] : 1; // Avoid division by zero
$budgetProgress = ($budgetAmount / $spendingLimit) * 100;

// Check if the budget is exceeded
$budgetExceeded = $budgetAmount > $spendingLimit ? true : false;

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
/* General Body Styles */
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: #E6F4E7; /* Light green covering the entire website */
    margin: 0;
    padding: 10px;
    display: flex;
    justify-content: center; 
    align-items: center; 
    min-height: 100vh;
    flex-direction: column;
}

/* Sidebar Styles */
#sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 80px;
    height: 100vh;
    background-color: #2E573F; /* Dark green */
    color: white;
    padding-top: 20px;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
    transition: width 0.3s ease;
}

#sidebar:hover {
    width: 250px; /* Expanded width */
}

#sidebar a {
    color: white;
    padding: 15px;
    text-decoration: none;
    display: flex;
    align-items: center;
    font-size: 16px;
    width: 100%;
    text-align: left;
}

#sidebar i {
    margin-right: 10px;
    font-size: 18px;
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
    padding: 20px 30px;
    max-width: 1200px;
    margin-top: 30px;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    background-color: white; /* Keeps the container white for contrast */
    border-radius: 8px; /* Rounded corners */
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); /* Soft shadow for depth */
}

/* Green Text Box - Reusable Style */
.green-box {
    background-color: #A9DAB0; /* Soft green shade for the box */
    border: 2px solid #2E573F; /* Dark green border */
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); /* Subtle shadow */
    padding: 20px;
    margin-bottom: 15px;
    width: 100%;
    max-width: 800px; /* Optional max width for consistency */
    text-align: center;
}

/* Budget Section - Using Green Box Style */
.budget-container {
    display: flex;
    justify-content: space-between; /* Ensures text is aligned properly */
    align-items: center;
    text-align: left; /* Align text to the left */
    width: 100%;
    max-width: 700px;
    margin: 0 auto; /* Centers the container */
}

.budget-container h5,
.budget-container p {
    color: #3a6ea5; /* Blue color for text */
    font-weight: bold;
    margin: 0; /* Removes extra margin */
}

/* Expenses Container Flexbox */
.expenses-container {
    display: flex;
    flex-wrap: wrap; /* Allows cards to wrap to the next line */
    justify-content: space-between; /* Even spacing between items */
    gap: 20px; /* Space between cards */
    background-color: #A9DAB0; /* Soft green background for the container */
    padding: 20px; /* Padding for spacing */
    border-radius: 8px; /* Rounded corners */
    margin-top: 20px; /* Spacing from the top */
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
    width: 100%;
}

/* Individual Expense Cards */
.expense-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 15px;
    flex: 1 1 calc(25% - 20px); /* 4 cards per row with space in between */
    max-width: 230px;
    min-width: 200px;
    margin-bottom: 15px;
    text-align: center;
}

.expense-card h5 {
    font-size: 16px;
    margin-bottom: 10px;
    color: #2E573F; /* Dark green for text */
}

.expense-card p {
    font-size: 14px;
    color: #555;
}

.expense-card p span {
    font-weight: bold;
}

/* Graph Container */
.dashboard {
    display: flex; /* Ensures graphs are displayed on the same line */
    flex-wrap: nowrap; /* Prevents wrapping */
    justify-content: space-between; /* Creates even spacing between graphs */
    gap: 15px; /* Adds space between cards */
    margin-top: 20px;
    width: 100%; /* Stretches the dashboard full width */
    overflow-x: auto; /* Enables horizontal scrolling if content overflows */
}

.card {
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    padding: 15px;
    flex: 1 1 30%; /* Makes each graph card occupy 30% width */
    min-width: 300px; /* Ensures a minimum width for smaller screens */
    max-width: 400px; /* Ensures consistency for larger screens */
    text-align: center; /* Centers content inside the card */
}

/* Footer Styles */
footer {
    background-color: #2E573F; /* Dark green */
    color: white;
    text-align: center;
    padding: 10px 0;
    margin-top: 20px;
    font-size: 14px;
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
    <a href="budget.php"><i class="fas fa-wallet"></i><span class="sidebar-text">Budget</span></a> <!-- New Budget Section -->
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i><span class="sidebar-text">Logout</span></a>
    
</div>
<div class="notification-icon">
    <i class="fas fa-bell"></i>
</div>

<!-- Main Content -->
<div id="app" class="container">
    <!-- Welcome Section -->
    <div class="welcome-section" id="welcome-message">
        <h3>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h3>
    </div>
    <h1>Finance Dashboard</h1>
    
    <!-- Search and Expense Section -->
<div class="search-expenses-container d-flex flex-column mb-4">
    <!-- Search Input (Moved to the top) -->
    <div class="search-container mb-4" style="flex: 1; max-width: 400px; margin-bottom: 20px;">
        <input v-model="searchQuery" type="text" placeholder="Search by description or category" class="form-control" />
    </div>
    
    <!-- Expenses List -->
    <div class="expenses-container d-flex flex-wrap" style="flex: 2; gap: 20px;">
    <!-- Check if there are any expenses -->
    <div v-if="filteredExpenses.length > 0" class="d-flex flex-wrap" style="gap: 20px;">
        <!-- Loop through expenses and create cards -->
        <div v-for="(expense, index) in filteredExpenses" :key="index" class="expense-card mb-3" style="min-width: 200px; max-width: 250px; flex: 1 1 calc(25% - 20px);">
            <h5>{{ expense.description }}</h5>
            <p>Category: {{ expense.category_description }}</p>
            <p>Date: {{ expense.date }}</p>
            <p>Amount: UGX {{ expense.amount }}</p>
        </div>
    </div>

    <!-- Message when no expenses exist -->
    <div v-else style="width: 100%; text-align: center;">
        No expenses found.
    </div>
</div>

</div>
    <!-- Budget Section (Total Budget and Limit) -->
    <div class="budget-container">
        <div>
            <h5>Total Budget</h5>
            <p>UGX <?php echo number_format($budgetAmount, 0); ?></p>
        </div>
        <div>
            <h5>Budget Limit</h5>
            <p>UGX <?php echo number_format($spendingLimit, 0); ?></p>
        </div>
    </div>
<div class="dashboard">
        <!-- Expense Doughnut Chart -->
        <div class="card" style="flex: 1 1 30%; margin-right: 20px;">
            <h2>Expenses Breakdown</h2>
            <div class="chart-container">
                <canvas id="expensesChart"></canvas>
            </div>
        </div>


        <!-- Category Doughnut Chart -->
        <div class="card" style="flex: 1 1 30%; margin-right: 20px;">
            <h2>Category Breakdown</h2>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <!-- Line Chart for Expenses Comparison -->
        <div class="card" style="flex: 1 1 30%; margin-right: 20px;">
            <h2>Expense Trend Over Time</h2>
            <div class="chart-container">
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>
    </div>
</div>


<script>
    new Vue({
        el: '#app',
        data: {
            expenses: <?php echo $expensesData; ?>,
            searchQuery: ''
        },
        computed: {
            filteredExpenses() {
                return this.expenses.filter(expense => {
                    return expense.description.toLowerCase().includes(this.searchQuery.toLowerCase()) ||
                           expense.category_description.toLowerCase().includes(this.searchQuery.toLowerCase());
                });
            }
        }
    });

    // JavaScript for charts (Doughnut and Line Charts)
    const ctx = document.getElementById('expensesChart').getContext('2d');
    const expensesData = <?php echo json_encode($expenses); ?>;

    const expenseLabels = expensesData.map(exp => exp.description);
    const expenseAmounts = expensesData.map(exp => exp.amount);

    const expensesChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: expenseLabels,
            datasets: [{
                label: 'Expenses',
                data: expenseAmounts,
                backgroundColor: ['#ff6384', '#36a2eb', '#ffcd56', '#4caf50', '#ff9f40'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });

    // Category Doughnut Chart (similar structure, adjust for categories)
    const categoryChart = new Chart(document.getElementById('categoryChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: ['Food', 'Transport', 'Entertainment', 'Rent'], // Example categories
            datasets: [{
                label: 'Category Expenses',
                data: [1200, 800, 400, 1500], // Example data
                backgroundColor: ['#ff6384', '#36a2eb', '#ffcd56', '#4caf50'],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
    

    // Line Chart for Expense Comparison
    const comparisonChart = new Chart(document.getElementById('comparisonChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: ['January', 'February', 'March', 'April'], // Example months
            datasets: [{
                label: 'Expenses Trend',
                data: [500, 700, 900, 650], // Example expense data
                borderColor: '#ff9f40',
                fill: false,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                }
            }
        }
    });
</script>

</body>
</html>
