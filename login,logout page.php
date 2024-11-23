<?php
session_start(); // Start the session to track the user's login state

// Database connection
$servername = "localhost";
$username = "root"; // MySQL username
$password = ""; // MySQL password
$dbname = "finance_student"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Registration process
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $username = $_POST['reg_username'];
    $email = $_POST['reg_email'];
    $password = password_hash($_POST['reg_password'], PASSWORD_DEFAULT); // Hash the password
    $first_name = $_POST['reg_first_name'] ?? ''; // Optional field
    $last_name = $_POST['reg_last_name'] ?? '';   // Optional field
    $date_of_birth = $_POST['reg_date_of_birth'] ?? ''; // Optional field

    // Check if the username or email already exists
    $check_sql = "SELECT * FROM users WHERE username = ? OR email = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("ss", $username, $email);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows > 0) {
        $reg_error = "Username or email already exists.";
    } else {
        // Insert new user into the database
        $sql_register = "INSERT INTO users (username, password_hash, email, first_name, last_name, date_of_birth) 
                         VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_register = $conn->prepare($sql_register);
        $stmt_register->bind_param("ssssss", $username, $password, $email, $first_name, $last_name, $date_of_birth);
        $stmt_register->execute();
        $reg_success = "Registration successful! You can now log in.";
        
        // Automatically switch to login form after successful registration
        echo "<script>window.onload = function() { switchForm('login'); }</script>";
    }
}

// Login process
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = $_POST['login_username'];
    $password = $_POST['login_password'];

    // Check if the user exists
    $sql_login = "SELECT * FROM users WHERE username = ?";
    $stmt_login = $conn->prepare($sql_login);
    $stmt_login->bind_param("s", $username);
    $stmt_login->execute();
    $result = $stmt_login->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];

            // Redirect to the dashboard after successful login
            header("Location: dashboard.php");
            exit();
        } else {
            $login_error = "Invalid password.";
        }
    } else {
        $login_error = "No user found with that username.";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login & Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
        }
        .container {
            max-width: 500px;
            padding: 30px;
            margin-top: 50px;
            background-color: white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .tab-buttons {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        .tab-buttons button {
            width: 48%;
            padding: 10px;
            cursor: pointer;
            border: 1px solid #ccc;
            background-color: #f4f4f9;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .tab-buttons button.active {
            background-color: #007bff;
            color: white;
        }
        .form-container {
            display: none;
        }
        .form-container.active {
            display: block;
        }
    </style>
</head>
<body>

<div class="container">
    <!-- Tab Buttons -->
    <div class="tab-buttons">
        <button class="active btn btn-primary" onclick="switchForm('login')">Login</button>
        <button class="btn btn-outline-primary" onclick="switchForm('register')">Register</button>
    </div>

    <!-- Login Form -->
    <div class="form-container active" id="login-form">
        <h2 class="text-center">Login</h2>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="login_username" class="form-label">Username:</label>
                <input type="text" class="form-control" name="login_username" id="login_username" required><br><br>
            </div>

            <div class="mb-3">
                <label for="login_password" class="form-label">Password:</label>
                <input type="password" class="form-control" name="login_password" id="login_password" required><br><br>
            </div>

            <?php if (isset($login_error)): ?>
                <p class="text-danger"><?php echo $login_error; ?></p>
            <?php endif; ?>

            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
        </form>
    </div>

    <!-- Register Form -->
    <div class="form-container" id="register-form">
        <h2 class="text-center">Register</h2>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="reg_username" class="form-label">Username:</label>
                <input type="text" class="form-control" name="reg_username" id="reg_username" required><br><br>
            </div>

            <div class="mb-3">
                <label for="reg_email" class="form-label">Email:</label>
                <input type="email" class="form-control" name="reg_email" id="reg_email" required><br><br>
            </div>

            <div class="mb-3">
                <label for="reg_password" class="form-label">Password:</label>
                <input type="password" class="form-control" name="reg_password" id="reg_password" required><br><br>
            </div>

            <div class="mb-3">
                <label for="reg_first_name" class="form-label">First Name:</label>
                <input type="text" class="form-control" name="reg_first_name" id="reg_first_name"><br><br>
            </div>

            <div class="mb-3">
                <label for="reg_last_name" class="form-label">Last Name:</label>
                <input type="text" class="form-control" name="reg_last_name" id="reg_last_name"><br><br>
            </div>

            <div class="mb-3">
                <label for="reg_date_of_birth" class="form-label">Date of Birth:</label>
                <input type="date" class="form-control" name="reg_date_of_birth" id="reg_date_of_birth"><br><br>
            </div>

            <?php if (isset($reg_error)): ?>
                <p class="text-danger"><?php echo $reg_error; ?></p>
            <?php elseif (isset($reg_success)): ?>
                <p class="text-success"><?php echo $reg_success; ?></p>
            <?php endif; ?>

            <button type="submit" name="register" class="btn btn-primary w-100">Register</button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>

<script>
    // Function to toggle between login and register forms
    function switchForm(form) {
        document.getElementById('login-form').classList.remove('active');
        document.getElementById('register-form').classList.remove('active');

        if (form === 'login') {
            document.getElementById('login-form').classList.add('active');
            document.querySelector('.tab-buttons button:nth-child(1)').classList.add('active');
            document.querySelector('.tab-buttons button:nth-child(2)').classList.remove('active');
        } else {
            document.getElementById('register-form').classList.add('active');
            document.querySelector('.tab-buttons button:nth-child(1)').classList.remove('active');
            document.querySelector('.tab-buttons button:nth-child(2)').classList.add('active');
        }
    }
</script>

</body>
</html>
