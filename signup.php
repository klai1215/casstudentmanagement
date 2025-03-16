<?php
session_start();
include("db/config.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Initialize variables with empty strings as default
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $program = isset($_POST['program']) ? trim($_POST['program']) : '';
    $role = isset($_POST['role']) ? trim($_POST['role']) : '';

    // Validate all required fields are filled
    if (empty($first_name) || empty($last_name) || empty($email) || 
        empty($password) || empty($confirm_password) || empty($role)) {
        $error_message = "All fields are required!";
    }
    // Program is only required for students
    else if ($role === 'student' && empty($program)) {
        $error_message = "Program is required for students!";
    }
    // Validate passwords match
    else if ($password !== $confirm_password) {
        $error_message = "Passwords do not match!";
    }
    // Validate email format
    else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format!";
    }
    else {
        // Start transaction
        $conn->begin_transaction();

        try {
            // First, check if email already exists
            $check_email_sql = "SELECT id FROM users WHERE email = ?";
            $check_email = $conn->prepare($check_email_sql);
            if ($check_email === false) {
                throw new Exception("Database error: " . $conn->error);
            }
            
            $check_email->bind_param("s", $email);
            $check_email->execute();
            $result = $check_email->get_result();

            if ($result->num_rows > 0) {
                throw new Exception("Email already exists!");
            }

            // Create user account
            $create_user_sql = "INSERT INTO users (email, password, username, role) VALUES (?, ?, ?, ?)";
            $create_user = $conn->prepare($create_user_sql);
            if ($create_user === false) {
                throw new Exception("Database error: " . $conn->error);
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $username = $email; // Using email as username
            $create_user->bind_param("ssss", $email, $hashed_password, $username, $role);
            
            if (!$create_user->execute()) {
                throw new Exception("Error creating user: " . $create_user->error);
            }
            $user_id = $conn->insert_id;

            // Create role-specific record
            if ($role === 'student') {
                $create_student_sql = "INSERT INTO students (user_id, first_name, middle_name, last_name, program, email) VALUES (?, ?, ?, ?, ?, ?)";
                $create_record = $conn->prepare($create_student_sql);
                if ($create_record === false) {
                    throw new Exception("Database error: " . $conn->error);
                }
                $create_record->bind_param("isssss", $user_id, $first_name, $middle_name, $last_name, $program, $email);
            } else {
                $create_teacher_sql = "INSERT INTO teachers (user_id, first_name, middle_name, last_name, email, password) VALUES (?, ?, ?, ?, ?, ?)";
                $create_record = $conn->prepare($create_teacher_sql);
                if ($create_record === false) {
                    throw new Exception("Database error: " . $conn->error);
                }
                $create_record->bind_param("isssss", $user_id, $first_name, $middle_name, $last_name, $email, $hashed_password);
            }
            
            if (!$create_record->execute()) {
                throw new Exception("Error creating record: " . $create_record->error);
            }

            // Commit transaction
            $conn->commit();

            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = $role;
            $_SESSION['username'] = $username;

            // Redirect based on role
            if ($role === 'teacher') {
                header("Location: teacher_dashboard.php");
            } else {
                header("Location: student_dashboard.php");
            }
            exit();

        } catch (Exception $e) {
            // Rollback on error
            $conn->rollback();
            $error_message = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>

<div class="signup-container">
    <div class="signup-box">
        <h2>Sign Up</h2>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" class="signup-form">
            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required onchange="toggleProgramField()">
                    <option value="">Select Role</option>
                    <option value="student">Student</option>
                    <option value="teacher">Teacher</option>
                </select>
            </div>

            <div class="form-group">
                <label for="first_name">First Name</label>
                <input type="text" id="first_name" name="first_name" required>
            </div>

            <div class="form-group">
                <label for="middle_name">Middle Name</label>
                <input type="text" id="middle_name" name="middle_name">
            </div>

            <div class="form-group">
                <label for="last_name">Last Name</label>
                <input type="text" id="last_name" name="last_name" required>
            </div>

            <div class="form-group" id="program_field" style="display: none;">
                <label for="program">Program</label>
                <select id="program" name="program">
                    <option value="">Select Program</option>
                    <option value="BSIT">Bachelor of Science in Information Technology</option>
                    <option value="BSCS">Bachelor of Science in Computer Science</option>
                    <option value="BSIS">Bachelor of Science in Information Systems</option>
                </select>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit" class="signup-button">Sign Up</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</div>

<script>
function toggleProgramField() {
    const role = document.getElementById('role').value;
    const programField = document.getElementById('program_field');
    const programSelect = document.getElementById('program');
    
    if (role === 'student') {
        programField.style.display = 'block';
        programSelect.required = true;
    } else {
        programField.style.display = 'none';
        programSelect.required = false;
        programSelect.value = ''; // Clear the program value when teacher is selected
    }
}
</script>

<style>
    body {
        margin: 0;
        padding: 0;
        font-family: 'Segoe UI', sans-serif;
        background: #f8f9fa;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
    }

    .signup-container {
        width: 100%;
        max-width: 500px;
        padding: 20px;
    }

    .signup-box {
        background: white;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }

    h2 {
        text-align: center;
        color: #6c5ce7;
        margin-bottom: 30px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        color: #2d3436;
        font-weight: 500;
    }

    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }

    .signup-button {
        width: 100%;
        padding: 12px;
        background: #6c5ce7;
        color: white;
        border: none;
        border-radius: 4px;
        font-size: 16px;
        cursor: pointer;
        margin-top: 10px;
    }

    .signup-button:hover {
        background: #5b4bc4;
    }

    .login-link {
        text-align: center;
        margin-top: 20px;
        color: #666;
    }

    .login-link a {
        color: #6c5ce7;
        text-decoration: none;
    }

    .alert {
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }

    .alert-error {
        background-color: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }

    @media (max-width: 768px) {
        .signup-container {
            padding: 10px;
        }

        .signup-box {
            padding: 20px;
        }
    }
</style>

</body>
</html>