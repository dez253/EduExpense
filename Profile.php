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
        /* Global Styles */
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #f0f4f8, #dde1e7);
            padding: 50px 0;
            color: #333;
        }

        .profile-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 850px;
            margin: auto;
            text-align: center;
            position: relative;
        }

        /* Profile Image Styling */
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #007bff;
            margin-bottom: 20px;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }

        /* Profile Header */
        .profile-header h2 {
            font-size: 2.5rem;
            color: #007bff;
            font-weight: 700;
        }

        .profile-header h3 {
            font-size: 1.5rem;
            color: #6c757d;
        }

        /* Profile Info */
        .profile-info {
            font-size: 1.1rem;
            color: #555;
            margin-bottom: 15px;
            text-align: left;
        }

        .profile-info strong {
            color: #007bff;
        }

        /* Button Styling */
        .btn {
            padding: 12px 30px;
            border-radius: 10px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .btn-edit {
            background: #28a745;
            color: white;
            border: none;
            margin-right: 20px;
        }

        .btn-edit:hover {
            background: #218838;
            transform: translateY(-3px);
        }

        .btn-back {
            background: #007bff;
            color: white;
            border: none;
        }

        .btn-back:hover {
            background: #0056b3;
            transform: translateY(-3px);
        }

        /* Button Group */
        .btn-group {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .profile-container {
                padding: 30px;
                margin: 0 20px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                margin-bottom: 15px;
            }
        }

    </style>
</head>
<body>

<div class="profile-container">
    <!-- Profile Image -->
    <img src="https://via.placeholder.com/150" alt="Profile Image" class="profile-image">

    <!-- Profile Header Section -->
    <div class="profile-header">
        <h2>Welcome, <?php echo htmlspecialchars($user['first_name']) . ' ' . htmlspecialchars($user['last_name']); ?></h2>
        <h3>Here's your profile details</h3>
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
