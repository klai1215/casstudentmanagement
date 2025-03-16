<?php
session_start();
include("db/config.php");

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_id = isset($_POST['teacher_id']) ? intval($_POST['teacher_id']) : 0;
    $subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];

    // Validate teacher_id
    if ($teacher_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid teacher ID']);
        exit();
    }

    try {
        $conn->begin_transaction();

        // First delete existing assignments
        $delete_sql = "DELETE FROM teacher_subjects WHERE teacher_id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $teacher_id);
        $delete_stmt->execute();

        // Then insert new assignments
        if (!empty($subjects)) {
            $insert_sql = "INSERT INTO teacher_subjects (teacher_id, course_id) VALUES (?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            
            foreach ($subjects as $course_id) {
                $course_id = intval($course_id);
                $insert_stmt->bind_param("ii", $teacher_id, $course_id);
                if (!$insert_stmt->execute()) {
                    throw new Exception("Error inserting subject: " . $insert_stmt->error);
                }
            }
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
} 