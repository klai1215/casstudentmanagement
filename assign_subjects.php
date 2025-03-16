<?php
session_start();
include("db/config.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: unauthorized.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $teacher_id = $_POST['teacher_id'];
    $selected_subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];

    try {
        $conn->begin_transaction();

        // Delete existing assignments for this teacher
        $delete_sql = "DELETE FROM teacher_subjects WHERE teacher_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $teacher_id);
        $delete_stmt->execute();

        // Insert new assignments
        if (!empty($selected_subjects)) {
            $insert_sql = "INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            foreach ($selected_subjects as $subject_id) {
                $insert_stmt->bind_param("ii", $teacher_id, $subject_id);
                $insert_stmt->execute();
            }
        }

        $conn->commit();
        $success_message = "Subjects assigned successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get all teachers
$teachers_query = "SELECT id, first_name, middle_name, last_name FROM teachers ORDER BY last_name, first_name";
$teachers_result = $conn->query($teachers_query);

// Get all subjects
$subjects_query = "SELECT * FROM courses ORDER BY course_code";
$subjects_result = $conn->query($subjects_query);

// Get current assignments for display
$assignments_query = "
    SELECT 
        ts.id,
        t.first_name,
        t.middle_name,
        t.last_name,
        s.course_code,
        s.course_name,
        ts.teacher_id,
        ts.subject_id
    FROM teacher_subjects ts
    JOIN teachers t ON ts.teacher_id = t.id
    JOIN courses s ON ts.subject_id = s.id
    ORDER BY t.last_name, t.first_name, s.course_code
";
$assignments_result = $conn->query($assignments_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects to Teachers</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
</head>
<body>

<div class="container">
    <h2>Assign Subjects to Teachers</h2>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Assignment Form -->
    <div class="assignment-form">
        <form method="POST" class="assign-form">
            <div class="form-group">
                <label for="teacher_id"><i class="fas fa-user"></i> Select Teacher:</label>
                <select name="teacher_id" id="teacher_id" required onchange="loadTeacherSubjects(this.value)">
                    <option value="">Choose a teacher</option>
                    <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                        <option value="<?php echo $teacher['id']; ?>">
                            <?php echo htmlspecialchars($teacher['last_name'] . ', ' . $teacher['first_name'] . ' ' . $teacher['middle_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label><i class="fas fa-book"></i> Select Subjects:</label>
                <div class="subjects-container">
                    <?php
                    if ($subjects_result->num_rows > 0) {
                        while ($subject = $subjects_result->fetch_assoc()): 
                    ?>
                        <div class="subject-checkbox">
                            <label>
                                <input type="checkbox" name="subjects[]" value="<?php echo $subject['id']; ?>">
                                <span class="subject-code"><?php echo htmlspecialchars($subject['course_code']); ?></span>
                                <span class="subject-name"><?php echo htmlspecialchars($subject['course_name']); ?></span>
                                <span class="subject-units">(<?php echo htmlspecialchars($subject['units']); ?> units)</span>
                            </label>
                        </div>
                    <?php 
                        endwhile;
                    } else {
                        echo '<p class="no-subjects">No courses available. Please add courses first.</p>';
                    }
                    ?>
                </div>
            </div>

            <button type="submit" class="btn-assign">
                <i class="fas fa-save"></i> Save Assignments
            </button>
        </form>
    </div>

    <!-- Current Assignments Display -->
    <div class="current-assignments">
        <h3><i class="fas fa-list"></i> Current Assignments</h3>
        <div id="assignmentsList">
            <!-- Assignments will be loaded here -->
        </div>
    </div>
</div>

<style>
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.assignment-form {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 10px;
    font-weight: bold;
    color: #333;
}

select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 16px;
}

.subjects-container {
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 15px;
    max-height: 300px;
    overflow-y: auto;
    background: #f9f9f9;
}

.subject-checkbox {
    margin-bottom: 10px;
    padding: 8px;
    background: white;
    border: 1px solid #eee;
    border-radius: 4px;
}

.subject-checkbox:hover {
    background: #f0f0f0;
}

.subject-checkbox label {
    display: flex;
    align-items: center;
    cursor: pointer;
    gap: 10px;
}

.subject-code {
    font-weight: bold;
    min-width: 100px;
    color: #2196F3;
}

.subject-name {
    color: #333;
    flex-grow: 1;
}

.subject-units {
    color: #666;
    font-size: 0.9em;
}

.no-subjects {
    text-align: center;
    color: #666;
    padding: 20px;
}

input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.btn-assign {
    background: #4CAF50;
    color: white;
    padding: 12px 24px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.btn-assign:hover {
    background: #45a049;
}

.alert {
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 4px;
}

.alert-success {
    background-color: #dff0d8;
    color: #3c763d;
    border: 1px solid #d6e9c6;
}

.alert-error {
    background-color: #f2dede;
    color: #a94442;
    border: 1px solid #ebccd1;
}

.current-assignments {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.current-assignments h3 {
    margin-bottom: 15px;
    color: #333;
}

/* Responsive Design */
@media (max-width: 768px) {
    .subjects-container {
        grid-template-columns: 1fr;
    }
}
</style>

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

<script>
function loadTeacherSubjects(teacherId) {
    if (!teacherId) return;

    // Clear all checkboxes
    document.querySelectorAll('input[name="subjects[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });

    // Fetch and check current assignments
    fetch(`get_teacher_subjects.php?teacher_id=${teacherId}`)
        .then(response => response.json())
        .then(subjects => {
            subjects.forEach(subjectId => {
                const checkbox = document.querySelector(`input[value="${subjectId}"]`);
                if (checkbox) checkbox.checked = true;
            });
        })
        .catch(error => console.error('Error:', error));
}

// Initialize tooltips or other UI enhancements here
document.addEventListener('DOMContentLoaded', function() {
    // Add any initialization code here
});
</script>

</body>
</html> 