<?php
session_start();
include("db/config.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
if ($_SESSION['role'] !== 'admin') {
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

// Replace the year levels query with a static array
$year_levels = [1, 2, 3, 4];

// Get all unique programs for the dropdown
$programs_query = $conn->query("SELECT DISTINCT program FROM students WHERE program IS NOT NULL ORDER BY program");
$programs = [];
while($row = $programs_query->fetch_assoc()) {
    if(!empty($row['program'])) {
        $programs[] = $row['program'];
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

if(isset($_GET['year_level']) && !empty($_GET['year_level'])) {
    $where_conditions[] = "year_level = ?";
    $params[] = $_GET['year_level'];
    $param_types .= "i";  // integer parameter
}

if(isset($_GET['program']) && !empty($_GET['program'])) {
    $where_conditions[] = "program = ?";
    $params[] = $_GET['program'];
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students</title>
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
            gap: 10px; /* Reduced from 15px */
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
            padding: 8px; /* Reduced from 12px */
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
            gap: 10px;
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
            padding: 8px 15px;
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

        .actions-container {
            display: flex;
            gap: 8px; /* Adjust the gap between buttons as needed */
            align-items: center; /* Align items vertically in the middle */
        }

        .button {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            color: white;
            font-size: 14px;
            cursor: pointer;
            border: none; /* Remove border for buttons */
            white-space: nowrap; /* Prevent text from wrapping */
        }

        .button.view-button {
            background-color: #17a2b8; /* Teal for view button */
        }

        .button.secondary {
            background-color: #6c757d; /* Gray for secondary button */
        }

        .button:hover {
            opacity: 0.8;
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
        <h2>Manage Students</h2>
    </nav>

    <!-- Student Form -->
    

    <!-- Filters Container -->
    <div class="filters-container">
        <div class="filter-group">
            <label>Select Section</label>
            <select name="section" onchange="applyFilters()">
                <option value="">All Sections</option>
                <option value="">A</option>
                <?php foreach($sections as $section): ?>
                    <option value="<?= htmlspecialchars($section) ?>"
                        <?= (isset($_GET['section']) && $_GET['section'] === $section) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($section) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Program</label>
            <select name="program" onchange="applyFilters()">
                <option value="">All Programs</option>
                <?php foreach($programs as $program): ?>
                    <option value="<?= htmlspecialchars($program) ?>"
                        <?= (isset($_GET['program']) && $_GET['program'] === $program) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($program) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label>Year Level</label>
            <select name="year_level" onchange="applyFilters()">
                <option value="">All Years</option>
                <?php 
                $year_labels = [
                    1 => '1st Year',
                    2 => '2nd Year',
                    3 => '3rd Year',
                    4 => '4th Year'
                ];
                foreach($year_labels as $value => $label): ?>
                    <option value="<?= $value ?>"
                        <?= (isset($_GET['year_level']) && $_GET['year_level'] == $value) ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="search-group">
            <input type="text" id="searchInput" placeholder="Search students..." 
                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
            <button onclick="applyFilters()" class="button">Search</button>
        </div>
    </div>

    <!-- Students Table -->
    <div class="table-container">
        <h3>Students List</h3>
        <table class="students-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Program</th>
                    <th>Section</th>
                    <th>Year Level</th>
                    <th>Enrollment Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($students && $students->num_rows > 0) : ?>
                    <?php while ($row = $students->fetch_assoc()) : ?>
                        <tr>
                            <td><?= htmlspecialchars($row['id']) ?></td>
                            <td><?= htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $row['middle_name']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><?= htmlspecialchars($row['phone'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['program'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['section'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['year_level'] ?: 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['enrollment_date'] ?: 'N/A') ?></td>
                            <td>
                                <div class="actions-container">
                                    <a href="view_details.php?id=<?= $row['id'] ?>" class="button view-button">View More</a>
                                    <a href="edit_student.php?id=<?= $row['id'] ?>" class="button">Edit</a>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="button secondary" onclick="return confirm('Are you sure you want to delete this student?')">Delete</button>
                                    </form>
                                </div>
                            </td>
                            </tr>
                    <?php endwhile; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="10" style="text-align: center;">No students found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
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
    const program = document.querySelector('select[name="program"]').value;
    const yearLevel = document.querySelector('select[name="year_level"]').value;
    const search = document.getElementById('searchInput').value;
    
    let url = window.location.pathname + '?';
    if (section) url += `section=${encodeURIComponent(section)}&`;
    if (program) url += `program=${encodeURIComponent(program)}&`;
    if (yearLevel) url += `year_level=${encodeURIComponent(yearLevel)}&`;
    if (search) url += `search=${encodeURIComponent(search)}&`;
    
    window.location.href = url.slice(0, -1); // Remove trailing &
}
</script>

</body>
</html>