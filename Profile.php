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

// Fetch current user information (first name, last name, username, and email)
$sql_user = "SELECT first_name, last_name, username, email FROM users WHERE user_id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param('i', $user_id);
$stmt_user->execute();
$user_result = $stmt_user->get_result();
$user = $user_result->fetch_assoc();

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Page</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f9;
            padding: 20px;
        }

        .profile-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 700px;
            margin: auto;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 20px;
        }

        .profile-info {
            margin-bottom: 15px;
        }

        .btn {
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            text-align: center;
        }

        .btn-edit {
            background-color: #4CAF50;
            color: white;
            border: none;
        }

        .btn-edit:hover {
            background-color: #45a049;
        }

        .btn-back {
            background-color: #007bff;
            color: white;
            border: none;
        }

        .btn-back:hover {
            background-color: #0056b3;
        }

        .btn-group {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }
    </style>
</head>
<body>

<div class="profile-container">
    <div class="profile-header">
        <h2>Profile</h2>
    </div>

    <!-- Display user information -->
    <div class="profile-info">
        <strong>First Name:</strong> <?php echo htmlspecialchars($user['first_name']); ?>
    </div>
    <div class="profile-info">
        <strong>Last Name:</strong> <?php echo htmlspecialchars($user['last_name']); ?>
    </div>
    <div class="profile-info">
        <strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?>
    </div>
    <div class="profile-info">
        <strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?>
    </div>

    <!-- Button group for edit and back buttons -->
    <div class="btn-group">
        <a href="edit_profile.php" class="btn btn-edit">Edit Profile</a>
        <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
    </div>
</div>

</body>
</html>
