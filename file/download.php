<?php
session_start();
include_once '../inc/db.php';

if (isset($_GET['file_id'])) {
    $file_id = $_GET['file_id'];

    $stmt = $conn->prepare("SELECT user_id, filename, filepath, is_public FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($owner_id, $filename, $filepath, $is_public);
        $stmt->fetch();

        $can_access = false;
        if ($is_public) {
            $can_access = true;
        } elseif (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $owner_id) {
            $can_access = true;
        }

        if ($can_access) {
            if (file_exists($filepath)) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Expires: 0');
                header('Cache-Control: must-revalidate');
                header('Pragma: public');
                header('Content-Length: ' . filesize($filepath));
                readfile($filepath);
                exit;
            } else {
                http_response_code(404);
                die('File not found.');
            }
        } else {
            // private 파일인데 세션이 없거나 소유자가 아닐 경우 에러 페이지로 이동
            header('Location: ../error.php?code=403');
            exit;
        }
    } else {
        http_response_code(404);
        die('File not found.');
    }

    $stmt->close();
} else {
    http_response_code(400);
    die('Bad request.');
}

$conn->close();
?>
