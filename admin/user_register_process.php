<?php
include_once '../inc/db.php';
require_once '../inc/auth.php';

require_admin();

$username = $_POST['username'];
$password = $_POST['password'];
$role = $_POST['role'];

if (empty($username) || empty($password) || empty($role)) {
    die("Username, password, and role are required.");
}

// Check if username already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    die("Username already exists.");
}
$stmt->close();


$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $hashed_password, $role);

if ($stmt->execute()) {
    header("Location: user_list.php");
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
