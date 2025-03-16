<?php
session_start();
include("db/config.php");

// Check if the user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// Get all teachers with their assigned subjects
$query = "SELECT 
            t.id,
            t.first_name,
            t.last_name,
            t.middle_name,
            t.email,
            GROUP_CONCAT(
                CONCAT(c.course_code, ' - ', c.course_name, ' (', c.units, ' units)') 
                SEPARATOR '<br>'
            ) as subjects,
            SUM(c.units) as total_units
        FROM teachers t
        LEFT JOIN teacher_subjects ts ON t.id = ts.teacher_id
        LEFT JOIN courses c ON ts.course_id = c.id
        GROUP BY t.id
        ORDER BY t.last_name, t.first_name";

$result = $conn->query($query);
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

        .btn-assign {
            background-color: #4CAF50;z
            color: white;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-right: 5px;
        }

        .btn-assign:hover {
            background-color: #45a049;
        }

        .actions {
            display: flex;
            gap: 5px;
            align-items: center;
        }

        /* Update existing button styles to match */
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            background-color: #2196F3;
            color: white;
        }

        .btn-edit:hover {
            background-color: #1976D2;
        }

        .btn-delete {
            background-color: #f44336;
            color: white;
        }

        .btn-delete:hover {
            background-color: #d32f2f;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 8px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #555;
        }

        .subject-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        .subject-item {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }

        .subject-item:last-child {
            border-bottom: none;
        }

        .subject-item label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }

        .btn-primary {
            background-color: #4CAF50;
            color: white;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .data-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }

        .subject-list {
            line-height: 1.6;
        }

        .no-subjects {
            color: #666;
            font-style: italic;
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


    <!-- Add this before the table-container div -->
    <div class="filters-container">
        <div class="filter-group">
            <label>Select Section</label>
            <select name="section" onchange="applyFilters()">
                <option value="">All Sections</option>
                <?php foreach($sections as $section): ?>
                    <option value="<?= htmlspecialchars($section) ?>" 
                        <?= (isset($_GET['section']) && $_GET['section'] === $section) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($section) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="search-group">
            <input type="text" id="searchInput" placeholder="Search teachers..." 
                   value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
            <button onclick="applyFilters()" class="button">Search</button>
        </div>
    </div>

    <!-- Teachers Table -->
    <div class="table-container">
        <h3>Teachers List</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Assigned Subjects</th>
                    <th>Total Units</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $row['middle_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td class="subject-list">
                            <?php 
                            if ($row['subjects']) {
                                echo $row['subjects'];
                            } else {
                                echo '<span class="no-subjects">No subjects assigned</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo $row['total_units'] ? $row['total_units'] : '0'; ?></td>
                        <td>
                            <button onclick="openAssignModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name'] . ' ' . $row['middle_name']); ?>')" 
                                    class="btn btn-primary">
                                <i class="fas fa-book"></i> Assign Subjects
                            </button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</main>

<!-- Subject Assignment Modal -->
<div id="assignSubjectsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Assign Subjects to <span id="teacherName"></span></h2>
            <span class="close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="assignSubjectsForm">
                <input type="hidden" id="teacherId" name="teacher_id">
                
                <div class="search-box">
                    <input type="text" id="subjectSearch" placeholder="Search subjects..." onkeyup="filterSubjects()">
                </div>

                <div class="subjects-grid">
                    <?php
                    // Get all available courses
                    $courses_query = "SELECT * FROM courses ORDER BY course_code";
                    $courses_result = $conn->query($courses_query);
                    
                    while ($course = $courses_result->fetch_assoc()):
                    ?>
                        <div class="subject-checkbox" data-subject="<?php echo strtolower($course['course_code'] . ' ' . $course['course_name']); ?>">
                            <label>
                                <input type="checkbox" name="subjects[]" value="<?php echo $course['id']; ?>">
                                <div class="subject-info">
                                    <div class="course-code"><?php echo htmlspecialchars($course['course_code']); ?></div>
                                    <div class="course-name"><?php echo htmlspecialchars($course['course_name']); ?></div>
                                    <div class="course-units"><?php echo htmlspecialchars($course['units']); ?> units</div>
                                </div>
                            </label>
                        </div>
                    <?php endwhile; ?>
                </div>

                <div class="selected-subjects">
                    <h3>Selected Subjects: <span id="selectedCount">0</span></h3>
                    <div id="selectedSubjectsList"></div>
                    <div id="totalUnits">Total Units: 0</div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

    // Dropdown functionality
    document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
        toggle.addEventListener('click', (e) => {
            e.stopPropagation(); // Prevent click from bubbling to document
            // Remove active class from all dropdowns
            document.querySelectorAll('.menu-item.dropdown').forEach(item => {
                if (item !== e.currentTarget.parentElement) {
                    item.classList.remove('active');
                }
            });
            // Toggle active class on clicked dropdown
            const dropdownItem = e.currentTarget.parentElement;
            dropdownItem.classList.toggle('active');
        });
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.sidebar-menu')) {
            document.querySelectorAll('.menu-item.dropdown').forEach(item => {
                item.classList.remove('active');
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

    function openAssignModal(teacherId, teacherName) {
        document.getElementById('teacherId').value = teacherId;
        document.getElementById('teacherName').textContent = teacherName;
        
        // Clear all checkboxes and reset counts
        document.querySelectorAll('input[name="subjects[]"]').forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectedSubjects();
        
        // Fetch current assignments
        fetch(`get_teacher_subjects.php?teacher_id=${teacherId}`)
            .then(response => response.json())
            .then(subjects => {
                subjects.forEach(subjectId => {
                    const checkbox = document.querySelector(`input[value="${subjectId}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                    }
                });
                updateSelectedSubjects();
            });
        
        document.getElementById('assignSubjectsModal').style.display = 'block';
    }

    function updateSelectedSubjects() {
        const selectedSubjects = [];
        let totalUnits = 0;
        
        document.querySelectorAll('input[name="subjects[]"]:checked').forEach(checkbox => {
            const subjectInfo = checkbox.closest('.subject-checkbox');
            const code = subjectInfo.querySelector('.course-code').textContent;
            const name = subjectInfo.querySelector('.course-name').textContent;
            const units = parseInt(subjectInfo.querySelector('.course-units').textContent);
            
            selectedSubjects.push(`${code} - ${name} (${units} units)`);
            totalUnits += units;
        });
        
        document.getElementById('selectedCount').textContent = selectedSubjects.length;
        document.getElementById('selectedSubjectsList').innerHTML = selectedSubjects.join('<br>');
        document.getElementById('totalUnits').textContent = `Total Units: ${totalUnits}`;
    }

    function closeModal() {
        document.getElementById('assignSubjectsModal').style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('assignSubjectsModal');
        if (event.target == modal) {
            closeModal();
        }
    }

    // Handle form submission
    document.getElementById('assignSubjectsForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('update_teacher_subjects.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Subjects assigned successfully!');
                closeModal();
                // Optionally refresh the page or update the UI
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while saving the assignments');
        });
    });
</script>

</body>
</html>
