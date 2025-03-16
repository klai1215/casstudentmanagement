<?php
include "db/config.php";  // Database connection

$sql = "SELECT id, password FROM users";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    $hashedPassword = password_hash($row['password'], PASSWORD_DEFAULT);
    $updateQuery = "UPDATE users SET password='$hashedPassword' WHERE id={$row['id']}";
    mysqli_query($conn, $updateQuery);
}

echo "Passwords updated successfully!";
mysqli_close($conn);
?>
