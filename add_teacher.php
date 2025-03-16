<?php
session_start();
include("db/config.php");

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// Get all unique sections for the dropdown
$sections_query = $conn->query("SELECT DISTINCT section FROM teachers WHERE section IS NOT NULL ORDER BY section");
$sections = [];
while($row = $sections_query->fetch_assoc()) {
    if(!empty($row['section'])) {
        $sections[] = $row['section'];
    }
}

// Handle filtering and search
$where_conditions = [];
$params = [];
$param_types = "";

if(isset($_GET['section']) && !empty($_GET['section'])) {
    $where_conditions[] = "section = ?";
    $params[] = $_GET['section'];
    $param_types .= "s";
}

if(isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = "%" . $_GET['search'] . "%";
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $param_types .= "sss";
}

// Build the query
$query = "SELECT * FROM teachers";
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
$teachers = $stmt->get_result();

// Handle adding a teacher
$teacher_message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_teacher'])) {
    $full_name = $_POST['full_name'];
    // Split full name into parts
    $name_parts = explode(" ", $full_name);
    $first_name = $name_parts[0];
    $last_name = isset($name_parts[1]) ? $name_parts[1] : "";
    $middle_name = isset($name_parts[2]) ? $name_parts[2] : "";
    
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $subject = $_POST['subject'];
    $section = $_POST['section'];

    // Check if the email already exists in the users table
    $check_user = $conn->prepare("SELECT id FROM users WHERE email = ?");
    
    if ($check_user === false) {
        $teacher_message = "<p class='error'>Database error: " . $conn->error . "</p>";
    } else {
        $check_user->bind_param("s", $email);
        $check_user->execute();
        $check_user->store_result();

        if ($check_user->num_rows > 0) {
            $teacher_message = "<p class='error'>Email already exists in users table.</p>";
        } else {
            // Insert into users table
            $role = "teacher";
            $username = strtolower($first_name . "." . $last_name); // Create username from name
            $stmt_user = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            
            if ($stmt_user === false) {
                $teacher_message = "<p class='error'>Database error: " . $conn->error . "</p>";
            } else {
                $stmt_user->bind_param("ssss", $username, $email, $password, $role);
                
                if ($stmt_user->execute()) {
                    // Get the inserted user ID
                    $user_id = $stmt_user->insert_id;

                    // Insert into teachers table
                    $stmt_teacher = $conn->prepare("INSERT INTO teachers (user_id, first_name, last_name, middle_name, email, password, subject, section) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if ($stmt_teacher === false) {
                        $teacher_message = "<p class='error'>Database error: " . $conn->error . "</p>";
                    } else {
                        $stmt_teacher->bind_param("isssssss", $user_id, $first_name, $last_name, $middle_name, $email, $password, $subject, $section);

                        if ($stmt_teacher->execute()) {
                            $teacher_message = "<p class='success'>Teacher added successfully!</p>";
                        } else {
                            $teacher_message = "<p class='error'>Error adding teacher to teachers table: " . $stmt_teacher->error . "</p>";
                        }
                        $stmt_teacher->close();
                    }
                } else {
                    $teacher_message = "<p class='error'>Error adding teacher to users table: " . $stmt_user->error . "</p>";
                }
                $stmt_user->close();
            }
            $check_user->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Teachers</title>
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

        /* First menu item (Dashboard) special styling */
        .menu-item:first-child a {
            background: var(--primary-color);
            color: white;
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

        /* Make sure the dropdown items are properly indented */
        .menu-item.dropdown .dropdown-menu a {
            padding-left: 15px;
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
        <h2>Manage Teachers</h2>
    </nav>

    <!-- Add Teacher Form -->
    <div class="form-container">
        <h2><?php echo isset($_GET['edit']) ? 'Edit Teacher' : 'Add New Teacher'; ?></h2>
        <?php if (!empty($teacher_message)) : ?>
            <div class="message <?php echo strpos($teacher_message, 'success') !== false ? 'success-message' : 'error-message'; ?>">
                <?php echo strip_tags($teacher_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-grid">
                <div class="form-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" required>
                </div>

                <div class="form-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" required>
                </div>

                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name">
                </div>

                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" name="email" required>
                </div>

                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" name="password" required>
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <input type="text" name="subject">
                </div>

                <div class="form-group">
                    <label>Section</label>
                    <input type="text" name="section">
                </div>

                <div class="form-actions">
                    <button type="submit" name="add_teacher" class="button">
                        <?php echo isset($_GET['edit']) ? 'Update Teacher' : 'Add Teacher'; ?>
                    </button>
                    <?php if (isset($_GET['edit'])) : ?>
                        <a href="manage_teachers.php" class="button secondary">Cancel</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Add this before the table-container div -->


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

    // Dropdown functionality - only show/hide menu without highlighting
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

    function applyFilters() {
        const section = document.querySelector('select[name="section"]').value;
        const search = document.getElementById('searchInput').value;
        
        let url = window.location.pathname;
        let params = [];
        
        if(section) {
            params.push(`section=${encodeURIComponent(section)}`);
        }
        if(search) {
            params.push(`search=${encodeURIComponent(search)}`);
        }
        
        if(params.length > 0) {
            url += '?' + params.join('&');
        }
        
        window.location.href = url;
    }

    // Allow search on Enter key press
    document.getElementById('searchInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            applyFilters();
        }
    });
</script>

</body>
</html>
