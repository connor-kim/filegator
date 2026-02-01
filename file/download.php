<?php
session_start();
include_once '../inc/db.php';

if (!isset($_GET['file_id'])) {
    http_response_code(400);
    die('Bad request.');
}

$file_id = (int)$_GET['file_id'];

$stmt = $conn->prepare("SELECT user_id, filename, filepath, is_public FROM files WHERE id = ?");
$stmt->bind_param("i", $file_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die('File not found.');
}

$row = $result->fetch_assoc();
$owner_id = $row['user_id'];
$filename = $row['filename'];
$filepath = $row['filepath'];
$is_public = $row['is_public'];
$stmt->close();

// 접근 권한 확인
// 1. 공개 파일: 누구나 다운로드 가능
// 2. 비공개 파일: 로그인한 사용자만 다운로드 가능
$can_access = false;

if ($is_public) {
    // 공개 파일은 누구나 다운로드 가능
    $can_access = true;
} elseif (isset($_SESSION['user_id'])) {
    // 비공개 파일은 로그인한 사용자만 다운로드 가능
    $can_access = true;
}

if (!$can_access) {
    // 비공개 파일인데 로그인하지 않은 경우
    header('Location: ../error.php?code=403');
    exit;
}

// 파일 존재 확인
if (empty($filepath) || !file_exists($filepath)) {
    http_response_code(404);
    die('File not found.');
}

// 다운로드 헤더 설정
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filepath));
readfile($filepath);
exit;

$conn->close();
?>
