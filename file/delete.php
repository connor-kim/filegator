<?php
session_start();
include_once '../inc/db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to delete files.");
}

if (isset($_GET['file_id'])) {
    $user_id = $_SESSION['user_id'];
    $file_id = $_GET['file_id'];

    function delete_file_or_folder($conn, $user_id, $file_id) {
        $stmt = $conn->prepare("SELECT filename, filepath, is_folder FROM files WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $file_id, $user_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($filename, $filepath, $is_folder);
            $stmt->fetch();

            if ($is_folder) {
                // Recursively delete sub-files and sub-folders
                $stmt_select_children = $conn->prepare("SELECT id FROM files WHERE parent_id = ? AND user_id = ?");
                $stmt_select_children->bind_param("ii", $file_id, $user_id);
                $stmt_select_children->execute();
                $result_children = $stmt_select_children->get_result();
                while ($row = $result_children->fetch_assoc()) {
                    delete_file_or_folder($conn, $user_id, $row['id']);
                }
                $stmt_select_children->close();

                // Delete the folder itself
                $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
                $stmt_delete->bind_param("ii", $file_id, $user_id);
                $stmt_delete->execute();
                $stmt_delete->close();

            } else {
                // Delete the file from the server
                if (file_exists($filepath)) {
                    unlink($filepath);
                }

                // Delete the file from the database
                $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
                $stmt_delete->bind_param("ii", $file_id, $user_id);
                $stmt_delete->execute();
                $stmt_delete->close();
            }
        }
        $stmt->close();
    }

    delete_file_or_folder($conn, $user_id, $file_id);

    header("Location: list.php");

} else {
    echo "No file specified.";
}

$conn->close();
?>
