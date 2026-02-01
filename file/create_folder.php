<?php
session_start();
include_once '../inc/db.php';

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

    // Check if folder with the same name already exists in the same directory
    $stmt = $conn->prepare("SELECT id FROM files WHERE user_id = ? AND filename = ? AND is_folder = 1 AND parent_id <=> ?");
    $stmt->bind_param("isi", $user_id, $folder_name, $parent_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        die("이 디렉터리에 같은 이름의 폴더가 이미 존재합니다.");
    }
    $stmt->close();

    // For folders, filepath is not actually used but is required (NOT NULL), so store empty string
    $dummy_path = '';
    $stmt = $conn->prepare("INSERT INTO files (user_id, filename, filepath, is_folder, parent_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issii", $user_id, $folder_name, $dummy_path, $is_folder, $parent_id);

    if ($stmt->execute()) {
        $redirect_url = "list.php";
        if ($parent_id !== null) {
            $redirect_url .= "?parent_id=" . $parent_id;
        }
        header("Location: " . $redirect_url);
    } else {
        echo "Error: " . $stmt->error;
    }
    $stmt->close();
} else {
    echo "폴더 이름이 지정되지 않았습니다.";
}

$conn->close();
?>
