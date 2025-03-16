<?php
session_start();
include("db/config.php");

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: unauthorized.php");
    exit();
}

// Get student information
$student_id = $_SESSION['user_id'];
$student_query = $conn->prepare("SELECT * FROM students WHERE user_id = ?");
$student_query->bind_param("i", $student_id);
$student_query->execute();
$student = $student_query->get_result()->fetch_assoc();

// Get payment statistics for this specific student
$payment_stats = $conn->prepare("
    SELECT 
        SUM(amount) as total_paid,
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount
    FROM payments 
    WHERE student_id = ?
");
$payment_stats->bind_param("i", $student['id']);
$payment_stats->execute();
$stats = $payment_stats->get_result()->fetch_assoc();

// Get recent payments for this specific student
$recent_payments = $conn->prepare("
    SELECT * FROM payments 
    WHERE student_id = ? 
    ORDER BY payment_date DESC 
    LIMIT 5
");
$recent_payments->bind_param("i", $student['id']);
$recent_payments->execute();
$payments = $recent_payments->get_result();

// Get student's grades
$grades_query = $conn->prepare("
    SELECT 
        g.subject_code,
        g.grade,
        g.semester,
        g.school_year
    FROM grades g
    WHERE g.student_id = ?
    ORDER BY g.school_year DESC, g.semester DESC
");
$grades_query->bind_param("i", $student['id']);
$grades_query->execute();
$grades_result = $grades_query->get_result();

// Store student info in session for use across pages
$_SESSION['student_id'] = $student['id'];
$_SESSION['student_name'] = $student['first_name'] . ' ' . $student['last_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
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
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-color);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            padding: 8px 15px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card h3 {
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -250px;
            }

            .sidebar.active {
                margin-left: 0;
            }

            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="logo.png" alt="School Logo">
        <h3>Student Panel</h3>
    </div>
    
    <ul class="sidebar-menu">
        <li class="menu-item">
            <a href="student_dashboard.php">
                <i class="fas fa-home"></i> Dashboard
            </a>
        </li>
        
        <li class="menu-item">
            <a href="my_courses.php">
                <i class="fas fa-book"></i> My Courses
            </a>
        </li>
        
        <li class="menu-item">
            <a href="my_grades.php">
                <i class="fas fa-chart-line"></i> My Grades
            </a>
        </li>
        
        <li class="menu-item">
            <a href="my_schedule.php">
                <i class="fas fa-calendar"></i> Class Schedule
            </a>
        </li>
        
        <li class="menu-item">
            <a href="my_payments.php">
                <i class="fas fa-file-invoice-dollar"></i> Payment History
            </a>
        </li>
        
        <li class="menu-item">
            <a href="student_profile.php">
                <i class="fas fa-user"></i> My Profile
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
            <span>Welcome, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></span>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <!-- Dashboard Content -->
    <div class="dashboard-cards">
        <div class="card">
            <h3><i class="fas fa-book"></i> My Courses</h3>
            <p>Currently enrolled courses: <span id="courseCount">0</span></p>
        </div>

        <div class="card">
            <h3><i class="fas fa-chart-line"></i> Academic Progress</h3>
            <p>Current GPA: <span id="gpa">0.00</span></p>
        </div>

        <div class="card">
            <h3><i class="fas fa-calendar"></i> Next Class</h3>
            <p id="nextClass">No classes scheduled</p>
        </div>

        <div class="card">
            <h3><i class="fas fa-file-invoice-dollar"></i> Payment Status</h3>
            <p id="paymentStatus">Up to date</p>
        </div>
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

    // Load dashboard data
    async function loadDashboardData() {
        try {
            const response = await fetch('get_student_dashboard_data.php');
            const data = await response.json();
            
            document.getElementById('courseCount').textContent = data.courseCount;
            document.getElementById('gpa').textContent = data.gpa;
            document.getElementById('nextClass').textContent = data.nextClass;
            document.getElementById('paymentStatus').textContent = data.paymentStatus;
        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }

    // Load initial data
    loadDashboardData();
</script>

</body>
</html>
