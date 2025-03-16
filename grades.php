<?php
session_start();
include("db/config.php");

// Check if student is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

// Fetch grades
$stmt = $conn->prepare("
    SELECT c.course_code, c.course_name, g.grade, g.created_at 
    FROM grades g
    JOIN courses c ON g.course_id = c.id
    WHERE g.student_id = ?
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$grades = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Student Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .grade-alert {
            animation: slideIn 0.5s ease-out;
            border-left: 5px solid #0d6efd;
        }
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h2>Your Grades</h2>
        
        <?php if ($grades->num_rows > 0): ?>
            <div class="mt-4">
                <?php while($grade = $grades->fetch_assoc()): ?>
                    <div class="alert grade-alert">
                        <h5><?= htmlspecialchars($grade['course_code'] . ' - ' . $grade['course_name']) ?></h5>
                        <p class="mb-0">
                            Grade: <?= $grade['grade'] ?>%<br>
                            <small class="text-muted">Posted on: <?= date('M j, Y g:i a', strtotime($grade['created_at'])) ?></small>
                        </p>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info mt-4">No grades posted yet.</div>
        <?php endif; ?>
    </div>
</body>
</html>