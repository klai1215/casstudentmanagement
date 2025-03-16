<?php
session_start();
include("db/config.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// CRUD Operations
$error = '';
$success = '';

// Delete Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'delete') {
    $id = intval($_POST['id']);
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        $success = "Student deleted successfully!";
    } else {
        $error = "Error deleting student: " . $conn->error;
    }
}

// Add/Edit Student
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['action']) && $_POST['action'] == 'add' || $_POST['action'] == 'edit')) {
    $id = $_POST['action'] == 'edit' ? intval($_POST['id']) : null;
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $program = trim($_POST['program']);
    $section = trim($_POST['section']);
    $year_level = trim($_POST['year_level']);
    $year_level = is_numeric($year_level) && $year_level !== '' ? intval($year_level) : NULL;

    $enrollment_date = trim($_POST['enrollment_date']);

    // Basic validation
    if (empty($last_name) || empty($first_name) || empty($email)) {
        $error = "Required fields: Last Name, First Name, and Email";
    } else {
        if ($_POST['action'] == 'add') {
            $stmt = $conn->prepare("INSERT INTO students (last_name, first_name, middle_name, email, phone, program, section, year_level, enrollment_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssis", $last_name, $first_name, $middle_name, $email, $phone, $program, $section, $year_level, $enrollment_date);
            } 
        else {
            $stmt = $conn->prepare("UPDATE students SET last_name=?, first_name=?, middle_name=?, email=?, phone=?, program=?, section=?, year_level=?, enrollment_date=? WHERE id=?");
            $stmt->bind_param("sssssssssi", $last_name, $first_name, $middle_name, $email, $phone, $program, $section, $year_level, $enrollment_date, $id);
            
        }

        if ($stmt->execute()) {
            $success = $_POST['action'] == 'add' ? "Student added successfully!" : "Student updated successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Get all unique sections for the dropdown
$sections_query = $conn->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL ORDER BY section");
$sections = [];
while($row = $sections_query->fetch_assoc()) {
    if(!empty($row['section'])) {
        $sections[] = $row['section'];
    }
}

// Static year levels array
$year_levels = [1, 2, 3, 4];

// Get all unique programs for the dropdown
$programs_query = $conn->query("SELECT DISTINCT program FROM students WHERE program IS NOT NULL ORDER BY program");
$programs = [];
while($row = $programs_query->fetch_assoc()) {
    if(!empty($row['program'])) {
        $programs[] = $row['program'];
    }
}

// Handle filtering
$where_conditions = [];
$params = [];
$param_types = "";

if(isset($_GET['section']) && !empty($_GET['section'])) {
    $where_conditions[] = "section = ?";
    $params[] = $_GET['section'];
    $param_types .= "s";
}

if(isset($_GET['year_level']) && !empty($_GET['year_level'])) {
    $where_conditions[] = "year_level = ?";
    $params[] = $_GET['year_level'];
    $param_types .= "i";
}

if(isset($_GET['program']) && !empty($_GET['program'])) {
    $where_conditions[] = "program = ?";
    $params[] = $_GET['program'];
    $param_types .= "s";
}

// Build the query
$query = "SELECT * FROM students";
if(!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}
$query .= " ORDER BY last_name, first_name";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if(!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

// Fetch student data for editing
$edit_student = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $edit_student = $conn->query("SELECT * FROM students WHERE id = $id")->fetch_assoc();
}

// Get all payments with student names
$query = "SELECT p.*, CONCAT(s.last_name, ', ', s.first_name) as student_name 
          FROM payments p 
          LEFT JOIN students s ON p.student_id = s.id 
          ORDER BY p.payment_date DESC";
$result = $conn->query($query);

// Get all students for dropdown
$students_query = $conn->query("SELECT id, CONCAT(last_name, ', ', first_name) as student_name FROM students ORDER BY last_name, first_name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $student_id = $_POST['student_id'];
    $amount = $_POST['amount'];
    $details = $_POST['details'];
    $status = $_POST['status'];
    $payment_date = $_POST['payment_date'];

    $stmt = $conn->prepare("INSERT INTO payments (student_id, amount, payment_date, details, status) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("idsss", $student_id, $amount, $payment_date, $details, $status);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = "Payment added successfully!";
        header("Location: manage_payments.php");
        exit();
    } else {
        $error_message = "Error adding payment: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Payment</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6c5ce7;
            --secondary-color: #a8a5e6;
            --background-color: #f8f9fa;
            --text-color: #2d3436;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
        }

        body {
            background-color: var(--background-color);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            background: white;
            width: 250px;
            min-height: 100vh;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            transition: 0.3s;
            position: fixed;
            z-index: 1000;
        }

        .sidebar.collapsed {
            margin-left: -250px;
        }

        .sidebar-header {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
        }

        .sidebar-header img {
            width: 40px;
            margin-right: 10px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .menu-item {
            margin: 5px 0;
        }

        .menu-item a,
        .dropdown-toggle {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            text-decoration: none;
            color: var(--text-color);
            border-radius: 8px;
            transition: 0.2s;
        }

        .menu-item a:hover,
        .dropdown-toggle:hover {
            background: var(--primary-color);
            color: white;
        }

        .menu-item.active a {
            background: var(--primary-color);
            color: white;
        }

        .menu-icon-label {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 250px;
            transition: 0.3s;
            padding: 30px;
        }

        .main-content.expanded {
            margin-left: 0;
        }

        /* Top Navigation */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .toggle-sidebar {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }
            .sidebar.collapsed {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
        }

        /* Dropdown specific styles */
        .dropdown-toggle {
            justify-content: space-between;
            cursor: pointer;
            width: 100%;
        }

        .dropdown-icon {
            font-size: 12px;
            transition: transform 0.3s ease;
        }

        .dropdown-menu {
            display: none;
            list-style: none;
            padding: 5px 0 5px 43px;
            margin: 0;
        }

        .dropdown-menu li {
            margin: 5px 0;
        }

        .dropdown-menu a {
            padding: 8px 15px;
            font-size: 14px;
            color: var(--text-color);
            text-decoration: none;
            display: block;
            border-radius: 6px;
        }

        .dropdown-menu a:hover {
            background: var(--primary-color);
            color: white;
        }

        .menu-item.dropdown.active .dropdown-toggle {
            background: var(--primary-color);
            color: white;
        }

        .menu-item.dropdown.active .dropdown-menu {
            display: block;
        }

        .menu-item.dropdown.active .dropdown-icon {
            transform: rotate(180deg);
        }

        /* Icon styles */
        .fas {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }

        /* Form Container */
        .form-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .form-actions {
            grid-column: 1 / -1;
            text-align: right;
            margin-top: 15px;
        }

        /* Table Styles */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin: 20px;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .teachers-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .teachers-table th,
        .teachers-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .teachers-table th {
            background: var(--primary-color);
            color: white;
        }

        .teachers-table tr:hover {
            background: #f5f5f5;
        }

        /* Status Badge Styles */
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .status-badge.paid {
            background-color: #4CAF50;
            color: white;
        }

        .status-badge.pending {
            background-color: #ff9800;
            color: white;
        }

        .status-badge.cancelled {
            background-color: #f44336;
            color: white;
        }

        /* Action Button Styles */
        .action-btn {
            padding: 6px 10px;
            margin: 0 3px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn.edit {
            background-color: #4CAF50;
        }

        .action-btn.delete {
            background-color: #f44336;
        }

        /* Button Styles */
        .button {
            background: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: 500;
        }

        .button:hover {
            background: #5b4bc4;
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 30px;
            color: #666;
        }

        /* Base styles - no highlighting by default */
        .sidebar-menu .menu-item a,
        .sidebar-menu .dropdown-toggle,
        .sidebar-menu .menu-item.active a,
        .sidebar-menu .menu-item.dropdown.active .dropdown-toggle,
        .sidebar-menu .menu-item:first-child a {
            background: transparent;
            color: var(--text-color);
            transition: all 0.2s ease;
        }

        /* Show highlight only on hover */
        .sidebar-menu .menu-item a:hover,
        .sidebar-menu .dropdown-toggle:hover,
        .sidebar-menu .dropdown-menu a:hover {
            background: var(--primary-color);
            color: white;
        }

        /* Hide dropdown menus by default */
        .dropdown-menu {
            display: none;
            list-style: none;
            padding: 5px 0 5px 43px;
            margin: 0;
        }

        /* Remove all other states */
        .menu-item.active a,
        .menu-item.dropdown.active .dropdown-toggle,
        .menu-item a:focus,
        .dropdown-toggle:focus {
            background: transparent;
            color: var(--text-color);
        }
    </style>
</head>
<body>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="sms.png" alt="School Logo">
        <h3>Admin Panel</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="admin_dashboard.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </li>
        
        <!-- Students Dropdown -->
        <li class="menu-item dropdown">
            <div class="dropdown-toggle">
                <div class="menu-icon-label">
                    <i class="fas fa-user-graduate"></i>
                    <span>Students</span>
                </div>
            </div>
            <ul class="dropdown-menu">
                <li><a href="manage_students.php">View Students</a></li>
                <li><a href="add_student.php">Add Student</a></li>
            </ul>
        </li>

        <li class="menu-item dropdown">
            <div class="dropdown-toggle">
                <div class="menu-icon-label">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Teachers</span>
                </div>
            </div>
            <ul class="dropdown-menu">
                <li><a href="manage_teachers.php">View Teachers</a></li>
                <li><a href="add_teacher.php">Add Teacher</a></li>
            </ul>
        </li>
        
        <li class="menu-item dropdown">
            <div class="dropdown-toggle">
                <div class="menu-icon-label">
                    <i class="fas fa-book-open"></i>
                    <span>Courses</span>
                </div>
            </div>
            <ul class="dropdown-menu">
                <li><a href="manage_courses.php">View Courses</a></li>
                <li><a href="add_course.php">Add Courses</a></li>
            </ul>
        </li>

        
        <li class="menu-item dropdown">
            <div class="dropdown-toggle">
                <div class="menu-icon-label">
                     <i class="fas fa-book"></i>
                    <span>Program</span>
                </div>
            </div>
        </li>

        <li class="menu-item dropdown">
            <div class="dropdown-toggle">
                <div class="menu-icon-label">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Financial Reports</span>
                </div>
            </div>
            <ul class="dropdown-menu">
                <li><a href="manage_payments.php">View Payments</a></li>
                <li><a href="add_payments.php">Add Payments</a></li>
            </ul>
        </li>

        
        <li class="menu-item">
            <div class="menu-icon-label">
            <a href="system_settings.php">
                <i class="fas fa-cog"></i> System Settings
            </a>
        </li>

        <li class="menu-item">
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </a>
        </li>
    </ul>
</aside>

<main class="main-content" id="mainContent">
    <nav class="top-nav">
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        
        </div>
    </nav>

    <nav class="top-nav">
        <h2>Add Payment</h2>
    </nav>

    <div class="table-container">
        <h3>Add New Payment</h3>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>

        <form method="POST" class="add-form">
            <div class="form-group">
                <label for="student_id">Student</label>
                <select name="student_id" id="student_id" required>
                    <option value="">Select Student</option>
                    <?php while ($student = $students_query->fetch_assoc()): ?>
                        <option value="<?= $student['id'] ?>">
                            <?= htmlspecialchars($student['student_name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="amount">Amount (₱)</label>
                <input type="number" name="amount" id="amount" step="0.01" required>
            </div>

            <div class="form-group">
                <label for="payment_date">Payment Date</label>
                <input type="datetime-local" name="payment_date" id="payment_date" required>
            </div>

            <div class="form-group">
                <label for="details">Details</label>
                <textarea name="details" id="details" rows="3" required></textarea>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" required>
                    <option value="paid">Paid</option>
                    <option value="pending">Pending</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="submit" class="button">Add Payment</button>
                <a href="manage_payments.php" class="button secondary">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script>
    // Toggle Sidebar
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('toggleSidebar');

    toggleBtn.addEventListener('click', () => {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('expanded');
        toggleBtn.innerHTML = sidebar.classList.contains('collapsed') ? 
            '<i class="fas fa-bars"></i>' : '<i class="fas fa-times"></i>';
    });

    // Simplified dropdown functionality
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            const dropdownMenu = e.currentTarget.nextElementSibling;
            
            // Hide all other dropdown menus
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                if (menu !== dropdownMenu) {
                    menu.style.display = 'none';
                }
            });

            // Toggle current dropdown menu
            dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.sidebar-menu')) {
            document.querySelectorAll('.dropdown-menu').forEach(menu => {
                menu.style.display = 'none';
            });
        }
    });

    // Remove any active classes when page loads
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.menu-item').forEach(item => {
            item.classList.remove('active');
        });
    });
</script>

</body>
</html>