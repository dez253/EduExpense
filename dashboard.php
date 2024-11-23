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

$conn->close();

// Convert PHP array to JSON for Vue.js
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

<!-- Main Content -->
<div id="app" class="container">
    <!-- Welcome Section -->
    <div class="welcome-section" id="welcome-message">
        <h3>Welcome, <?php echo htmlspecialchars($user['username']); ?>!</h3>
    </div>

    <h1>Student Finance Dashboard</h1>
    
    <div class="dashboard">
        <!-- Expense Doughnut Chart -->
        <div class="card">
            <h2>Expenses Breakdown</h2>
            <div class="chart-container">
                <canvas id="expensesChart"></canvas>
            </div>
        </div>

        <!-- Category Doughnut Chart -->
        <div class="card">
            <h2>Category Breakdown</h2>
            <div class="chart-container">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

        <!-- Line Chart for Expenses Comparison -->
        <div class="card">
            <h2>Expense Trend Over Time</h2>
            <div class="chart-container">
                <canvas id="comparisonChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Expense Cards Display with Search -->
    <div class="dashboard">
        <input v-model="searchQuery" type="text" placeholder="Search by description or category" class="form-control mb-4" />
        
        <div v-if="filteredExpenses.length > 0">
            <div v-for="(expense, index) in filteredExpenses" :key="index" class="expense-card">
                <h5>{{ expense.description }}</h5>
                <p>Category: {{ expense.category_description }}</p>
                <p>Date: {{ expense.date }}</p>
                <p>Amount: UGX {{ expense.amount }}</p>  <!-- Changed currency to UGX -->
            </div>
        </div>
        <div v-else>
            <p>No expenses found matching your search.</p>
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
