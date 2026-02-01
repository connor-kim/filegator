<?php
include_once '../inc/db.php';
require_once '../inc/auth.php';

require_admin();

if (isset($_GET['id'])) {
    $user_id_to_delete = (int)$_GET['id'];

    // Get the username of the user to be deleted
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id_to_delete);
    $stmt->execute();
    $stmt->bind_result($username_to_delete);
    $stmt->fetch();
    $stmt->close();

    if ($username_to_delete === 'admin') {
        die("The 'admin' user cannot be deleted.");
    }

    if ($user_id_to_delete > 0) {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id_to_delete);

        if ($stmt->execute()) {
            header("Location: user_list.php");
        } else {
            echo "Error deleting user: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Invalid user ID.";
    }
} else {
    echo "No user ID specified.";
}

$conn->close();
?>
