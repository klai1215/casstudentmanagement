<?php
session_start();
include("db/config.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get student data for editing
$edit_student = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $edit_student = $stmt->get_result()->fetch_assoc();
    
    if (!$edit_student) {
        header("Location: manage_students.php");
        exit();
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $program = trim($_POST['program']);
    $section = trim($_POST['section']);
    $year_level = !empty($_POST['year_level']) ? intval($_POST['year_level']) : null;
    $enrollment_date = !empty($_POST['enrollment_date']) ? trim($_POST['enrollment_date']) : null;

    if (empty($last_name) || empty($first_name) || empty($email)) {
        echo "Required fields: Last Name, First Name, and Email";
    } else {
        $stmt = $conn->prepare("UPDATE students SET last_name=?, first_name=?, middle_name=?, email=?, phone=?, program=?, section=?, year_level=?, enrollment_date=? WHERE id=?");
        $stmt->bind_param("sssssssisi", 
            $last_name, 
            $first_name, 
            $middle_name, 
            $email, 
            $phone, 
            $program, 
            $section, 
            $year_level, 
            $enrollment_date, 
            $id
        );

        if ($stmt->execute()) {
            header("Location: manage_students.php?success=Student updated successfully!");
            exit();
        } else {
            echo "Error: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student</title>
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
        }

        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .students-table th,
        .students-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .students-table th {
            background: var(--primary-color);
            color: white;
        }

        .students-table tr:hover {
            background: #f5f5f5;
        }

        /* Filters Container */
        .filters-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-group label {
            font-weight: 500;
            color: var(--text-color);
        }

        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 150px;
        }

        .search-group {
            display: flex;
            gap: 10px;
            flex: 1;
            max-width: 500px;
        }

        .search-group input {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .search-group .button {
            padding: 8px 20px;
        }

        /* Button Styles */
        .button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .button:hover {
            background: #5b4bc4;
        }

        .button.secondary {
            background: #6c757d;
        }

        /* Message Styles */
        .message {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
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

<!-- Main Content -->
<main class="main-content" id="mainContent">
    <!-- Top Navigation -->
    <nav class="top-nav">
        <button class="toggle-sidebar" id="toggleSidebar">
            <i class="fas fa-bars"></i>
        </button>
        <div class="user-info">
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </div>
    </nav>

    <nav class="top-nav">
        <h2>Edit Student</h2>
    </nav>

    <!-- Form Container -->
    <div class="form-container">
        <?php if (isset($error)): ?>
            <div class="message error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        <?php if (isset($success)): ?>
            <div class="message success-message"><?php echo $success; ?></div>
        <?php endif; ?>

        <form method="POST" action="edit_student.php">
            <input type="hidden" name="id" value="<?php echo $edit_student['id']; ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($edit_student['last_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($edit_student['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" value="<?php echo htmlspecialchars($edit_student['middle_name']); ?>">
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($edit_student['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($edit_student['phone']); ?>">
                </div>
                <div class="form-group">
                    <label>Program</label>
                    <input type="text" name="program" value="<?php echo htmlspecialchars($edit_student['program']); ?>">
                </div>
                <div class="form-group">
                    <label>Section</label>
                    <input type="text" name="section" value="<?php echo htmlspecialchars($edit_student['section']); ?>">
                </div>
                <div class="form-group">
                    <label>Year Level</label>
                    <select name="year_level">
                        <option value="">Select Year Level</option>
                        <option value="1" <?php echo ($edit_student['year_level'] == 1) ? 'selected' : ''; ?>>1st Year</option>
                        <option value="2" <?php echo ($edit_student['year_level'] == 2) ? 'selected' : ''; ?>>2nd Year</option>
                        <option value="3" <?php echo ($edit_student['year_level'] == 3) ? 'selected' : ''; ?>>3rd Year</option>
                        <option value="4" <?php echo ($edit_student['year_level'] == 4) ? 'selected' : ''; ?>>4th Year</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Enrollment Date</label>
                    <input type="date" name="enrollment_date" value="<?php echo htmlspecialchars($edit_student['enrollment_date']); ?>">
                </div>
                <div class="form-actions">
                    <button type="submit" class="button">Update Student</button>
                    <a href="manage_students.php" class="button secondary">Cancel</a>
                </div>
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

    // Active Menu Item
    document.querySelectorAll('.menu-item').forEach(item => {
        item.addEventListener('click', function() {
            document.querySelectorAll('.menu-item').forEach(i => i.classList.remove('active'));
            this.classList.add('active');
        });
    });

    // Dropdown functionality
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            const dropdownItem = e.currentTarget.parentElement;
            dropdownItem.classList.toggle('active');
        });
    });

    // Ensure dropdown stays open when its items are active
    document.addEventListener('DOMContentLoaded', () => {
        const currentPath = window.location.pathname;
        const dropdowns = document.querySelectorAll('.menu-item.dropdown');
        
        dropdowns.forEach(dropdown => {
            const links = dropdown.querySelectorAll('.dropdown-menu a');
            links.forEach(link => {
                if (currentPath.includes(link.getAttribute('href'))) {
                    dropdown.classList.add('active');
                }
            });
        });
    });
</script>

</body>
</html>
