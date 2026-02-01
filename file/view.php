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
// 1. 공개 파일: 누구나 뷰 가능
// 2. 비공개 파일: 로그인한 사용자만 뷰 가능
$can_access = false;

if ($is_public) {
    // 공개 파일은 누구나 뷰 가능
    $can_access = true;
} elseif (isset($_SESSION['user_id'])) {
    // 비공개 파일은 로그인한 사용자만 뷰 가능
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

// MIME 타입 확인
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

// 뷰어 가능한 MIME 타입
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
    'application/pdf'
];

if (in_array($mime_type, $viewable_mime_types)) {
    header('Content-Type: ' . $mime_type);
    readfile($filepath);
} else {
    // 뷰어 지원하지 않는 타입은 다운로드로 리다이렉트
    header("Location: download.php?file_id=" . $file_id);
}
exit;

$conn->close();
?>
