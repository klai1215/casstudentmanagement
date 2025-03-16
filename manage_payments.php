<?php
session_start();
include("db/config.php");


// Updated query with correct column names
$query = "SELECT 
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.middle_name,
            s.program,
            s.year_level,
            s.section,
            s.program_cost,
            COALESCE(SUM(p.amount), 0) as total_paid,
            (s.program_cost - COALESCE(SUM(p.amount), 0)) as balance,
            MAX(p.payment_date) as last_payment,
            CASE 
                WHEN SUM(p.amount) >= s.program_cost THEN 'Fully Paid'
                WHEN SUM(p.amount) > 0 THEN 'Partial Payment'
                ELSE 'No Payment'
            END as payment_status
        FROM students s
        LEFT JOIN payments p ON s.id = p.student_id
        GROUP BY s.id, s.first_name, s.last_name, s.middle_name, s.program, s.year_level, s.section, s.program_cost
        ORDER BY s.last_name, s.first_name";
$result = $conn->query($query);
if (!$result) {
    die("Query failed: " . $conn->error);
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $id = $_POST['action'] == 'edit' ? intval($_POST['id']) : null;
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $program = trim($_POST['program']);
    $section = trim($_POST['section']);
    $year_level = is_numeric($_POST['year_level']) ? intval($_POST['year_level']) : NULL;
    $enrollment_date = trim($_POST['enrollment_date']);
    $program_cost = is_numeric($_POST['program_cost']) ? floatval($_POST['program_cost']) : NULL;

    if (empty($last_name) || empty($first_name) || empty($email)) {
        $error = "Required fields: Last Name, First Name, and Email";
    } else {
        if ($_POST['action'] == 'add') {
            $stmt = $conn->prepare("INSERT INTO students (last_name, first_name, middle_name, email, phone, program, section, year_level, enrollment_date, program_cost) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssssis", $last_name, $first_name, $middle_name, $email, $phone, $program, $section, $year_level, $enrollment_date, $program_cost);
        } else {
            $stmt = $conn->prepare("UPDATE students SET last_name=?, first_name=?, middle_name=?, email=?, phone=?, program=?, section=?, year_level=?, enrollment_date=?, program_cost=? WHERE id=?");
            $stmt->bind_param("sssssssis", $last_name, $first_name, $middle_name, $email, $phone, $program, $section, $year_level, $enrollment_date, $program_cost, $id);
        }

        if ($stmt->execute()) {
            $success = $_POST['action'] == 'add' ? "Student added successfully!" : "Student updated successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// Fetch Filters for Dropdowns
$sections = [];
$sections_query = $conn->query("SELECT DISTINCT section FROM students WHERE section IS NOT NULL ORDER BY section");
while ($row = $sections_query->fetch_assoc()) {
    if (!empty($row['section'])) $sections[] = $row['section'];
}

$year_levels = [1, 2, 3, 4];

$programs = [];
$programs_query = $conn->query("SELECT DISTINCT program FROM students WHERE program IS NOT NULL ORDER BY program");
while ($row = $programs_query->fetch_assoc()) {
    if (!empty($row['program'])) $programs[] = $row['program'];
}

// Filtering Logic
$where_conditions = [];
$params = [];
$param_types = "";

if (!empty($_GET['section'])) {
    $where_conditions[] = "section = ?";
    $params[] = $_GET['section'];
    $param_types .= "s";
}
if (!empty($_GET['year_level'])) {
    $where_conditions[] = "year_level = ?";
    $params[] = $_GET['year_level'];
    $param_types .= "i";
}
if (!empty($_GET['program'])) {
    $where_conditions[] = "program = ?";
    $params[] = $_GET['program'];
    $param_types .= "s";
}

$query = "SELECT * FROM students";
if (!empty($where_conditions)) {
    $query .= " WHERE " . implode(" AND ", $where_conditions);
}
$query .= " ORDER BY last_name, first_name";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$students = $stmt->get_result();

// Get Payments with Student Info
$query = "SELECT 
            s.id as student_id,
            s.first_name,
            s.last_name,
            s.middle_name,
            s.program,
            s.year_level,
            s.section,
            s.program_cost,
            COALESCE(SUM(p.amount), 0) as total_paid,
            (s.program_cost - COALESCE(SUM(p.amount), 0)) as balance,
            MAX(p.payment_date) as last_payment,
            CASE 
                WHEN SUM(p.amount) >= s.program_cost THEN 'Fully Paid'
                WHEN SUM(p.amount) > 0 THEN 'Partial Payment'
                ELSE 'No Payment'
            END as payment_status
        FROM students s
        LEFT JOIN payments p ON s.id = p.student_id
        GROUP BY s.id, s.first_name, s.last_name, s.middle_name, s.program, s.year_level, s.section, s.program_cost
        ORDER BY s.last_name, s.first_name";

$result = $conn->query($query);
if (!$result) {
    die("Query failed: " . $conn->error);
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Payments</title>
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

        .container {
            padding: 20px;
        }

        .header-actions {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 20px;
        }

        .btn-add-payment {
            background-color: #6c5ce7;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .data-table th {
            background-color: #f8f9fa;
            font-weight: bold;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-badge.no-payment {
            background-color: #ff9f43;
            color: white;
        }

        .btn-view,
        .btn-edit {
            padding: 5px 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
            background: none;
        }

        .btn-view i,
        .btn-edit i {
            color: #666;
        }

        .btn-view:hover i {
            color: #17a2b8;
        }

        .btn-edit:hover i {
            color: #28a745;
        }

        .search-filters {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }

        .search-filters input,
        .search-filters select {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .payments-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .payments-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .btn-add-payment {
            background: #6c5ce7;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .status-badge {
            background: #ff9f43;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
        }

        .actions {
            display: flex;
            gap: 5px;
        }

        .btn-action {
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
        }

        .btn-action:hover {
            color: #6c5ce7;
        }

        /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.5);
        z-index: 1001;
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        width: 50%;
        border-radius: 8px;
        position: relative;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #ddd;
        padding-bottom: 15px;
        margin-bottom: 15px;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 1.5em;
    }

    .close {
        cursor: pointer;
        font-size: 28px;
        font-weight: bold;
    }

    .modal-footer {
        margin-top: 20px;
        text-align: right;
    }

    .btn {
        padding: 8px 20px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        margin-left: 10px;
    }

    .btn-primary {
        background-color: #6c5ce7;
        color: white;
    }

    .btn-secondary {
        background-color: #666;
        color: white;
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
        <h2>Student Payments</h2>
    </nav>

    <!-- Search and Filter Section -->
    <div class="search-filters">
        <div class="search-box">
            <input type="text" id="studentSearch" placeholder="Search by Student Name..." onkeyup="searchStudents()">
            <i class="fas fa-search search-icon"></i>
        </div>
        <div class="filter-box">
            <select id="yearFilter" onchange="filterStudents()">
                <option value="">All Year Levels</option>
                <option value="1">1st Year</option>
                <option value="2">2nd Year</option>
                <option value="3">3rd Year</option>
                <option value="4">4th Year</option>
            </select>
            <select id="statusFilter" onchange="filterStudents()">
                <option value="">All Payment Status</option>
                <option value="Fully Paid">Fully Paid</option>
                <option value="Partial Payment">Partial Payment</option>
                <option value="No Payment">No Payment</option>
            </select>
        </div>
    </div>

    <!-- Payments Table -->
    <div class="table-container">
        <div class="table-header">
            <h3>Payments List</h3>
            </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Year & Section</th>
                    <th>Total Paid</th>
                    <th>Balance</th>
                    <th>Last Payment</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="studentTableBody">
                <?php while ($row = $result->fetch_assoc()): 
                    $student_name = $row['last_name'] . ', ' . $row['first_name'] . ' ' . ($row['middle_name'] ?? '');
                    $year_section = $row['year_level'] . ' ' . $row['program'] . ' - ' . $row['section'];
                ?>
                    <tr class="payment-row"
                        data-search="<?php echo strtolower(htmlspecialchars($student_name)); ?>"
                        data-year="<?php echo htmlspecialchars($row['year_level']); ?>"
                        data-status="<?php echo htmlspecialchars($row['payment_status']); ?>">
                        <td><?php echo htmlspecialchars($student_name); ?></td>
                        <td><?php echo htmlspecialchars($year_section); ?></td>
                        <td>₱<?php echo number_format($row['total_paid'], 2); ?></td>
                        <td>₱<?php echo number_format($row['balance'], 2); ?></td>
                        <td><?php echo $row['last_payment'] ? date('M d, Y', strtotime($row['last_payment'])) : 'N/A'; ?></td>
                        <td>
                            <span class="status-badge <?php echo strtolower(str_replace(' ', '-', $row['payment_status'])); ?>">
                                <?php echo $row['payment_status']; ?>
                            </span>
                        </td>
                        <td>
                            <button onclick="viewPayments(<?php echo $row['student_id']; ?>)" 
                                    class="btn-view" title="View History">
                                <i class="fas fa-history"></i>
                            </button>
                            <button onclick="showPaymentModal(<?php echo $row['student_id']; ?>, '<?php echo htmlspecialchars($student_name); ?>')" 
                                class="btn-add" title="Add Payment">
                                <i class="fas fa-plus"></i> Add Payment
                            </button>

                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Add Payment Modal -->
<div id="addPaymentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add Payment for <span id="studentName"></span></h2>
            <span class="close" onclick="closePaymentModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form id="addPaymentForm">
                <input type="hidden" id="studentId" name="student_id">
                
                <div class="form-group">
                    <label for="paymentAmount">Payment Amount (₱)</label>
                    <input type="number" id="paymentAmount" name="payment_amount" required>
                </div>
                
                <div class="form-group">
                    <label for="paymentMethod">Payment Method</label>
                    <select id="paymentMethod" name="payment_method" required>
                        <option value="Cash">Cash</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Bank Transfer">Bank Transfer</option>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Submit Payment</button>
                    <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Modal Functions
    function showPaymentModal(studentId, studentName) {
        document.getElementById('studentId').value = studentId;
        document.getElementById('studentName').textContent = studentName;
        document.getElementById('addPaymentModal').style.display = 'block';
    }

    function closePaymentModal() {
        document.getElementById('addPaymentModal').style.display = 'none';
        document.getElementById('addPaymentForm').reset();
    }

    // Form Submission Handling
    document.getElementById('addPaymentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('process_payment.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Payment added successfully!');
                closePaymentModal();
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error occurred'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('addPaymentModal');
        if (event.target === modal) {
            closePaymentModal();
        }
    }

    // Remove conflicting addPayment function
    // Remove this duplicate function if it exists elsewhere in your code
    // function addPayment(studentId) {
    //     window.location.href = `add_payment.php?student_id=${studentId}`;
    // }
</script>



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

    function searchStudents() {
        const searchInput = document.getElementById('studentSearch');
        filterStudents();
    }

    function filterStudents() {
        const searchValue = document.getElementById('studentSearch').value.toLowerCase();
        const yearValue = document.getElementById('yearFilter').value;
        const statusValue = document.getElementById('statusFilter').value;
        
        document.querySelectorAll('.payment-row').forEach(row => {
            const nameMatch = row.getAttribute('data-search').includes(searchValue);
            const yearMatch = !yearValue || row.getAttribute('data-year') === yearValue;
            const statusMatch = !statusValue || row.getAttribute('data-status') === statusValue;
            
            if (nameMatch && yearMatch && statusMatch) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    }

    function viewPayments(studentId) {
        window.location.href = `payment_history.php?student_id=${studentId}`;
    }

    // Add New Payment
    function addPayment(studentId) {
        window.location.href = `add_payment.php?student_id=${studentId}`;
    }
</script>

</body>
</html>