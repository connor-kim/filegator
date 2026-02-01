<?php
ini_set('upload_max_filesize', '1024M');
ini_set('post_max_size', '1024M');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);

session_start();
include_once '../inc/db.php';
include_once '../inc/file_helpers.php';

if (!isset($_SESSION['user_id'])) {
    die("파일을 업로드하려면 로그인해야 합니다.");
}

if (isset($_FILES['files'])) {
    $user_id = $_SESSION['user_id'];
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $parent_id = (isset($_POST['parent_id']) && $_POST['parent_id'] !== '') ? (int)$_POST['parent_id'] : null;

    $total_files = count($_FILES['files']['name']);

    if ($total_files === 0) {
        die("업로드된 파일이 없습니다.");
    }

    // 부모 폴더의 실제 경로 계산
    $target_folder_path = get_folder_path($conn, $parent_id);
    
    // 폴더가 존재하지 않으면 생성
    if (!ensure_directory_exists($target_folder_path)) {
        die("대상 폴더를 생성할 수 없습니다: " . $target_folder_path);
    }

    $success_count = 0;
    $error_messages = [];

    for ($i = 0; $i < $total_files; $i++) {
        $filename = basename($_FILES['files']['name'][$i]);
        
        // 빈 파일명 건너뛰기
        if (empty($filename)) {
            continue;
        }
        
        // Generate a unique stored filename to avoid collisions
        $safeFilename = preg_replace('/[^\w\-.]+/', '_', $filename);
        $uniquePrefix = date('YmdHis') . '_' . bin2hex(random_bytes(4));
        $storedFilename = $uniquePrefix . '_' . $safeFilename;
        $filepath = $target_folder_path . '/' . $storedFilename;

        // Check if file with the same name already exists in the same folder (모든 사용자 대상)
        $stmt = $conn->prepare("SELECT id FROM files WHERE filename = ? AND parent_id <=> ?");
        $stmt->bind_param("si", $filename, $parent_id);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error_messages[] = "'{$filename}' 파일이 이미 존재합니다.";
            $stmt->close();
            continue;
        }
        $stmt->close();

        // PHP 업로드 에러 코드 체크
        $error_code = $_FILES['files']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($error_code !== UPLOAD_ERR_OK) {
            $error_messages[] = "'{$filename}' 업로드 중 오류 발생 (코드: {$error_code})";
            continue;
        }

        // 실제 업로드된 파일인지 확인
        if (!is_uploaded_file($_FILES['files']['tmp_name'][$i])) {
            $error_messages[] = "'{$filename}' 임시 파일을 찾을 수 없습니다.";
            continue;
        }

        if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $filepath)) {
            $stmt = $conn->prepare("INSERT INTO files (user_id, filename, filepath, is_public, parent_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issii", $user_id, $filename, $filepath, $is_public, $parent_id);

            if ($stmt->execute()) {
                $success_count++;
            } else {
                // DB 저장 실패 시 업로드된 파일 삭제
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                $error_messages[] = "'{$filename}' DB 저장 실패: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_messages[] = "'{$filename}' 파일 이동 실패";
        }
    }

    // 리다이렉트
    $redirect_url = "list.php";
    if ($parent_id) {
        $redirect_url .= "?parent_id=" . $parent_id;
    }
    
    // 에러가 있으면 세션에 저장 (선택적)
    if (!empty($error_messages)) {
        $_SESSION['upload_errors'] = $error_messages;
    }
    if ($success_count > 0) {
        $_SESSION['upload_success'] = $success_count . "개 파일 업로드 완료";
    }
    
    header("Location: " . $redirect_url);
    exit();

} else {
    die("업로드된 파일이 없습니다.");
}

$conn->close();
?>
