<?php
session_start();
include("db/config.php");

// Check if teacher is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: login.php");
    exit();
}

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $course_id = $_POST['course_id'];
    $grade = $_POST['grade'];
    
    $stmt = $conn->prepare("INSERT INTO grades (student_id, course_id, grade) VALUES (?, ?, ?)");
    $stmt->bind_param("iid", $student_id, $course_id, $grade);
    
    if ($stmt->execute()) {
        $success = "Grade submitted successfully!";
    } else {
        $error = "Error submitting grade: " . $conn->error;
    }
}

// Fetch students and courses
$students = $conn->query("SELECT id, full_name FROM studyante WHERE role='student'");
$courses = $conn->query("SELECT id, course_code, course_name FROM courses WHERE teacher_id = {$_SESSION['user_id']}");
?>

<!DOCTYPE html>
<html>
<head>
    <title>Teacher Grade Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Assign Grades</h2>
        <?php if(isset($success)): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label class="form-label">Student</label>
                <select name="student_id" class="form-select" required>
                    <?php while($student = $students->fetch_assoc()): ?>
                        <option value="<?= $student['id'] ?>"><?= htmlspecialchars($student['full_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Course</label>
                <select name="course_id" class="form-select" required>
                    <?php while($course = $courses->fetch_assoc()): ?>
                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div class="mb-3">
                <label class="form-label">Grade</label>
                <input type="number" step="0.01" min="0" max="100" name="grade" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary">Submit Grade</button>
        </form>
    </div>
</body>
</html>