<?php
session_start();
include_once '../inc/db.php';
include_once '../inc/file_helpers.php';

if (!isset($_SESSION['user_id'])) {
    die("폴더를 생성하려면 로그인해야 합니다.");
}

if (isset($_POST['folder_name'])) {
    $user_id = $_SESSION['user_id'];
    $folder_name = trim($_POST['folder_name']);
    $is_folder = 1;
    $parent_id = (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') ? (int)$_POST['parent_id'] : null;

    if ($folder_name === '') {
        die("폴더 이름을 입력해야 합니다.");
    }

    // Check if folder with the same name already exists in the same directory (모든 사용자 대상)
    $stmt = $conn->prepare("SELECT id FROM files WHERE filename = ? AND is_folder = 1 AND parent_id <=> ?");
    $stmt->bind_param("si", $folder_name, $parent_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        die("이 디렉터리에 같은 이름의 폴더가 이미 존재합니다.");
    }
    $stmt->close();

    // 부모 폴더의 실제 경로 계산
    $parent_path = get_folder_path($conn, $parent_id);
    $safe_folder_name = sanitize_folder_name($folder_name);
    $new_folder_path = $parent_path . '/' . $safe_folder_name;

    // 실제 디렉토리 생성
    if (!ensure_directory_exists($new_folder_path)) {
        die("디렉토리 생성에 실패했습니다: " . $new_folder_path);
    }

    // DB에 폴더 정보 저장 (filepath에 실제 경로 저장)
    $stmt = $conn->prepare("INSERT INTO files (user_id, filename, filepath, is_folder, parent_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $user_id, $folder_name, $new_folder_path, $is_folder, $parent_id);

    if ($stmt->execute()) {
        $redirect_url = "list.php";
        if ($parent_id !== null) {
            $redirect_url .= "?parent_id=" . $parent_id;
        }
        header("Location: " . $redirect_url);
    } else {
        // DB 저장 실패 시 생성된 디렉토리 삭제
        if (is_dir($new_folder_path)) {
            rmdir($new_folder_path);
        }
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "폴더 이름이 지정되지 않았습니다.";
}

$conn->close();
?>
