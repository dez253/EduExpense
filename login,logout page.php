<?php
// Start the session
session_start();

// Database connection (make sure to change these parameters to your actual database credentials)
$host = 'localhost';
$username = 'root'; // replace with your database username
$password = ''; // replace with your database password
$dbname = 'finance_student'; // replace with your database name

$conn = new mysqli($host, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize the messages as empty strings to avoid undefined variable warnings
$login_message = '';
$register_message = '';
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

// Login User
if (isset($_POST['login'])) {
    $login_username = $_POST['login_username'];
    $login_password = $_POST['login_password'];

    // Check if the user exists
    $check_login_query = "SELECT * FROM users WHERE username = '$login_username'";
    $result = $conn->query($check_login_query);

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        if (password_verify($login_password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            // Redirect or show success message
            header("Location: dashboard.php"); // Replace 'welcome.php' with your dashboard or desired page
            exit();
        } else {
            $login_message = 'Invalid password!';
        }
    } else {
        $login_message = 'Username not found!';
    }
}


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sliding Login & Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    /* Global Styles */
  /* Global Styles */
  body {
    font-family: 'Arial', sans-serif;
    background-image: url('wp10935121-business-office-wallpapers.jpg'); /* Replace with your image path */
    background-size: cover; /* Ensures the image covers the entire screen */
    background-position: center; /* Centers the image */
    background-repeat: no-repeat; /* Prevents repetition of the image */
    margin: 0;
    padding: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh; /* Ensures it takes the full height of the viewport */
}


/* Container */
.container {
    position: relative;
    width: 90%;
    max-width: 1000px; /* Increased width for more space */
    height: auto; /* Adjusted for responsive height */
    background: #fff;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: row; /* Ensure the forms and cover are laid out side by side initially */
    flex-wrap: wrap; /* Allow elements to wrap on smaller screens */
}

/* Forms Section */
.forms {
    display: flex;
    width: 100%; /* Adjusted for full width */
    transition: transform 0.6s ease-in-out;
}

/* Form Wrapper */
.form-wrapper {
    flex: 1;
    width: 50%; /* Forms take up half the width */
    padding: 40px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.form-wrapper.login {
    background: #f9f9f9; /* Light grey background for login */
}

.form-wrapper.register {
    background: #f4f4f4; /* Slightly darker grey for registration */
}

/* Heading Styles */
h2 {
    font-size: 1.8rem;
    font-weight: bold;
    margin-bottom: 20px;
    color: #4c6b3c; /* Deep green for a professional look */
    text-align: center;
}

/* Label Styles */
label {
    color: #333;
    font-weight: bold;
}

/* Input Fields */
.form-control {
    border-radius: 5px;
    box-shadow: 0 3px 5px rgba(0, 0, 0, 0.1);
    margin-bottom: 15px;
    border: 1px solid #d1d1d1;
}

/* Button Styles */
button {
    background: #4c6b3c; /* Deep green for buttons */
    color: #fff;
    border: none;
    border-radius: 5px;
    padding: 10px;
    font-weight: bold;
    font-size: 1rem;
    transition: background 0.3s;
}

button:hover {
    background: #3a5731; /* Darker green for hover effect */
}

/* Cover Section */
.cover {
    position: absolute;
    top: 0;
    right: 0;
    width: 50%; /* Cover initially takes up half the container */
    height: 100%;
    background: #4c6b3c; /* Green background for cover */
    color: #fff;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    text-align: center;
    z-index: 1;
    transition: transform 0.6s ease-in-out;
}

.cover h1 {
    font-size: 2rem;
    margin-bottom: 15px;
}

.cover p {
    font-size: 1rem;
    margin-bottom: 20px;
}

/* Switch Buttons */
.switch-btns button {
    background: transparent;
    border: 2px solid #fff;
    color: #fff;
    padding: 10px 20px;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.3s, color 0.3s;
}
button[name="login"] {
    background-color: #4c6b3c; /* A shade of green */
    color: white; /* White text for contrast */
    border: none; /* Removes default border */
    border-radius: 5px; /* Smooth edges */
    padding: 10px;
    font-weight: bold;
    font-size: 1rem;
    cursor: pointer;
    transition: background-color 0.3s ease-in-out;
}

button[name="login"]:hover {
    background-color: #4c6b3c; /* Slightly darker green for hover effect */
}

.switch-btns button:hover {
    background: #fff;
    color: #4c6b3c; /* Green text on hover */
}

/* Password Strength Message */
#password_strength {
    font-size: 0.9rem;
    font-weight: 500;
    margin-top: 5px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        flex-direction: column; /* Stack elements vertically on smaller screens */
        padding: 10px;
        height: auto;
    }

    .form-wrapper {
        padding: 20px;
        width: 100%; /* Full width for form wrappers on small screens */
    }

    .cover {
        width: 100%; /* Cover section takes full width */
        height: auto; /* Let the height adjust based on content */
        padding: 30px;
    }

    h2 {
        font-size: 1.6rem;
    }

    .form-control {
        padding: 12px;
    }

    button {
        font-size: 1rem;
    }
}


    </style>
</head>
<body>
    <div class="container">
        <div class="forms" id="forms">
            <!-- Login Form -->
            <div class="form-wrapper login">
                <h2>Login</h2>
                <form method="POST" action="">
                    <label for="login_username">Username:</label>
                    <input type="text" class="form-control" name="login_username" id="login_username" required>
                    <label for="login_password">Password:</label>
                    <input type="password" class="form-control" name="login_password" id="login_password" required>
                    <button type="submit" name="login" class="btn w-100 mt-3">Login</button>
                </form>
                <?php if ($login_message): ?>
                    <div class="alert alert-info mt-3">
                        <?= $login_message ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Register Form -->
            <div class="form-wrapper register">
                <h2>Register</h2>
                <form method="POST" action="">
                    <label for="reg_username">Username:</label>
                    <input type="text" class="form-control" name="reg_username" id="reg_username" required>
                    <label for="reg_email">Email:</label>
                    <input type="email" class="form-control" name="reg_email" id="reg_email" required>
                    <label for="reg_first_name">First Name:</label>
                    <input type="text" class="form-control" name="reg_first_name" id="reg_first_name">
                    <label for="reg_last_name">Last Name:</label>
                    <input type="text" class="form-control" name="reg_last_name" id="reg_last_name">
                    <label for="reg_date_of_birth">Date of Birth:</label>
                    <input type="date" class="form-control" name="reg_date_of_birth" id="reg_date_of_birth">
                    <label for="reg_password">Password:</label>
                    <input type="password" class="form-control" name="reg_password" id="reg_password" required>
                    <small id="password_strength" class="form-text text-muted"></small>
                    <button type="submit" name="register" class="btn w-100 mt-3">Register</button>
                </form>
                <?php if ($register_message): ?>
                    <div class="alert alert-info mt-3">
                        <?= $register_message ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Cover Section -->
        <div class="cover login" id="cover">
            <h1>Welcome Back!</h1>
            <p>To keep connected with us, please login with your personal information.</p>
            <div class="switch-btns">
                <button onclick="switchTo('register')">Register</button>
            </div>
        </div>
    </div>

    <script>
        // Password strength validation
        document.getElementById("reg_password").addEventListener("input", function () {
            const password = this.value;
            const strengthText = document.getElementById("password_strength");
            const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
            if (password.length >= 8 && regex.test(password)) {
                strengthText.textContent = "Strong password.";
                strengthText.style.color = "green";
            } else {
                strengthText.textContent = "Password must be at least 8 characters long, include an uppercase letter, a number, and a special character.";
                strengthText.style.color = "red";
            }
        });

        function switchTo(form) {
            const forms = document.getElementById('forms');
            const cover = document.getElementById('cover');

            if (form === 'register') {
                forms.style.transform = 'translateX(-50%)';
                cover.classList.remove('login');
                cover.classList.add('register');
                cover.innerHTML = `
                    <h1>Join Us Now!</h1>
                    <p>Create an account and start your journey with us.</p>
                    <div class="switch-btns">
                        <button onclick="switchTo('login')">Login</button>
                    </div>
                `;
            } else {
                forms.style.transform = 'translateX(0%)';
                cover.classList.remove('register');
                cover.classList.add('login');
                cover.innerHTML = `
                    <h1>Welcome Back!</h1>
                    <p>To keep connected with us, please login with your personal information.</p>
                    <div class="switch-btns">
                        <button onclick="switchTo('register')">Register</button>
                    </div>
                `;
            }
        }
    </script>
</body>
</html>
