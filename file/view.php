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
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $filepath);
                finfo_close($finfo);

                // Define viewable MIME types
                $viewable_mime_types = [
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/bmp',
                    'image/webp',
                    'video/mp4',
                    'video/webm',
                    'audio/mpeg',
                    'audio/wav',
                    'audio/ogg',
                    'application/pdf' // Added PDF as a viewable type
                ];

                if (in_array($mime_type, $viewable_mime_types)) {
                    header('Content-Type: ' . $mime_type);
                    readfile($filepath);
                } else {
                    // If not a viewable type, redirect to download
                    header("Location: download.php?file_id=" . $file_id);
                }
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
