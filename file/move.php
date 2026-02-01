<?php
session_start();
include_once '../inc/db.php';
include_once '../inc/file_helpers.php';

// 인증 확인
if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? 'user';

// POST 요청만 허용
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: list.php");
    exit();
}

// 필수 파라미터 확인
if (!isset($_POST['file_ids']) || empty($_POST['file_ids'])) {
    $_SESSION['move_error'] = '이동할 파일/폴더를 선택해주세요.';
    header("Location: list.php");
    exit();
}

// target_parent_id: '' 또는 'null' 문자열이면 null로 처리 (Root로 이동)
$target_parent_id = null;
if (isset($_POST['target_parent_id']) && $_POST['target_parent_id'] !== '' && $_POST['target_parent_id'] !== 'null') {
    $target_parent_id = (int)$_POST['target_parent_id'];
}

// 원래 위치 (리다이렉트용)
$original_parent_id = isset($_POST['original_parent_id']) ? (int)$_POST['original_parent_id'] : null;

// 이동할 파일 ID 배열
$file_ids = array_map('intval', $_POST['file_ids']);

// 대상 폴더가 유효한지 확인 (null이 아닌 경우)
if ($target_parent_id !== null) {
    $stmt = $conn->prepare("SELECT id, is_folder FROM files WHERE id = ?");
    $stmt->bind_param("i", $target_parent_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['move_error'] = '대상 폴더가 존재하지 않습니다.';
        $stmt->close();
        $redirect_url = $original_parent_id ? "list.php?parent_id=" . $original_parent_id : "list.php";
        header("Location: " . $redirect_url);
        exit();
    }
    
    $target_folder = $result->fetch_assoc();
    $stmt->close();
    
    if (!$target_folder['is_folder']) {
        $_SESSION['move_error'] = '대상이 폴더가 아닙니다.';
        $redirect_url = $original_parent_id ? "list.php?parent_id=" . $original_parent_id : "list.php";
        header("Location: " . $redirect_url);
        exit();
    }
}

$success_count = 0;
$error_messages = [];

foreach ($file_ids as $file_id) {
    // 파일/폴더 정보 조회
    $stmt = $conn->prepare("SELECT id, user_id, filename, filepath, is_folder, parent_id FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $error_messages[] = "ID {$file_id}: 파일/폴더를 찾을 수 없습니다.";
        $stmt->close();
        continue;
    }
    
    $file = $result->fetch_assoc();
    $stmt->close();
    
    // 권한 확인 (업로더 또는 관리자만 이동 가능)
    if (!can_move_file($file['user_id'], $current_user_id, $current_user_role)) {
        $error_messages[] = htmlspecialchars($file['filename']) . ": 이동 권한이 없습니다.";
        continue;
    }
    
    // 같은 위치로 이동하는지 확인
    if ($file['parent_id'] == $target_parent_id || 
        ($file['parent_id'] === null && $target_parent_id === null)) {
        $error_messages[] = htmlspecialchars($file['filename']) . ": 이미 해당 위치에 있습니다.";
        continue;
    }
    
    // 폴더인 경우 순환 참조 확인 (자기 자신 또는 하위 폴더로 이동 불가)
    if ($file['is_folder'] && $target_parent_id !== null) {
        if ($file_id == $target_parent_id || is_descendant_folder($conn, $file_id, $target_parent_id)) {
            $error_messages[] = htmlspecialchars($file['filename']) . ": 폴더를 자기 자신 또는 하위 폴더로 이동할 수 없습니다.";
            continue;
        }
    }
    
    // 대상 폴더에 같은 이름의 파일/폴더가 있는지 확인
    if ($target_parent_id === null) {
        $stmt_check = $conn->prepare("SELECT id FROM files WHERE parent_id IS NULL AND filename = ? AND id != ?");
        $stmt_check->bind_param("si", $file['filename'], $file_id);
    } else {
        $stmt_check = $conn->prepare("SELECT id FROM files WHERE parent_id = ? AND filename = ? AND id != ?");
        $stmt_check->bind_param("isi", $target_parent_id, $file['filename'], $file_id);
    }
    $stmt_check->execute();
    $check_result = $stmt_check->get_result();
    
    if ($check_result->num_rows > 0) {
        $error_messages[] = htmlspecialchars($file['filename']) . ": 대상 폴더에 같은 이름의 항목이 이미 존재합니다.";
        $stmt_check->close();
        continue;
    }
    $stmt_check->close();
    
    // 실제 파일 시스템에서 이동 (파일인 경우)
    if (!$file['is_folder'] && !empty($file['filepath']) && file_exists($file['filepath'])) {
        // 새 경로 계산
        $new_folder_path = get_folder_path($conn, $target_parent_id);
        ensure_directory_exists($new_folder_path);
        
        // 원본 파일명 추출
        $original_filename = basename($file['filepath']);
        $new_filepath = $new_folder_path . '/' . $original_filename;
        
        // 파일 이동
        if (!rename($file['filepath'], $new_filepath)) {
            $error_messages[] = htmlspecialchars($file['filename']) . ": 파일 이동에 실패했습니다.";
            continue;
        }
        
        // DB에 새 경로 업데이트
        $stmt_update = $conn->prepare("UPDATE files SET parent_id = ?, filepath = ? WHERE id = ?");
        $stmt_update->bind_param("isi", $target_parent_id, $new_filepath, $file_id);
    } else {
        // 폴더이거나 파일 경로가 없는 경우 parent_id만 업데이트
        // 폴더의 경우 실제 파일시스템 이동은 복잡하므로 DB만 업데이트
        // (하위 파일들의 filepath는 상대 경로이므로 폴더 이동 시 재계산 필요)
        
        if ($file['is_folder']) {
            // 폴더 내 모든 파일의 filepath 업데이트 필요
            // 간단한 구현: DB의 parent_id만 변경 (실제 파일 경로는 get_folder_path로 동적 계산)
            $stmt_update = $conn->prepare("UPDATE files SET parent_id = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $target_parent_id, $file_id);
        } else {
            $stmt_update = $conn->prepare("UPDATE files SET parent_id = ? WHERE id = ?");
            $stmt_update->bind_param("ii", $target_parent_id, $file_id);
        }
    }
    
    if ($stmt_update->execute()) {
        $success_count++;
    } else {
        $error_messages[] = htmlspecialchars($file['filename']) . ": DB 업데이트에 실패했습니다.";
    }
    $stmt_update->close();
}

$conn->close();

// 결과 메시지 설정
if ($success_count > 0) {
    $_SESSION['move_success'] = "{$success_count}개 항목이 이동되었습니다.";
}

if (!empty($error_messages)) {
    $_SESSION['move_errors'] = $error_messages;
}

// 리다이렉트 (원래 위치로)
$redirect_url = $original_parent_id ? "list.php?parent_id=" . $original_parent_id : "list.php";
header("Location: " . $redirect_url);
exit();
?>
