<?php
include_once '../inc/db.php';
require_once '../inc/auth.php';

require_login();

$current_password = $_POST['current_password'];
$new_password = $_POST['new_password'];
$confirm_new_password = $_POST['confirm_new_password'];

if (empty($current_password) || empty($new_password) || empty($confirm_new_password)) {
    die("All password fields are required.");
}

if ($new_password !== $confirm_new_password) {
    die("New passwords do not match.");
}

$user_id = $_SESSION['user_id'];

// Get current password hash
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($hashed_password);
$stmt->fetch();
$stmt->close();

if (password_verify($current_password, $hashed_password)) {
    // Current password is correct, update to new password
    $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        echo "Password changed successfully.";
        // Optionally, redirect to a success page or back to mypage
        header("refresh:2;url=mypage.php");
    } else {
        echo "Error updating password: " . $update_stmt->error;
    }
    $update_stmt->close();

} else {
    die("Incorrect current password.");
}

$conn->close();
?>
