<?php
session_start();
include_once '../inc/db.php';
include_once '../inc/file_helpers.php';

if (!isset($_SESSION['user_id'])) {
    die("로그인이 필요합니다.");
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';

/**
 * 파일 또는 폴더(하위 포함) 삭제
 * 업로더 또는 관리자만 삭제 가능
 */
function delete_file_or_folder_recursive($conn, $file_id, $current_user_id, $current_user_role) {
    // 파일/폴더 정보 조회 (user_id 조건 없음)
    $stmt = $conn->prepare("SELECT user_id, filename, filepath, is_folder FROM files WHERE id = ?");
    $stmt->bind_param("i", $file_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return false; // 파일이 존재하지 않음
    }

    $row = $result->fetch_assoc();
    $owner_id = $row['user_id'];
    $filename = $row['filename'];
    $filepath = $row['filepath'];
    $is_folder = $row['is_folder'];
    $stmt->close();

    // 권한 확인: 업로더 또는 관리자만 삭제 가능
    if (!can_delete_file($owner_id, $current_user_id, $current_user_role)) {
        return false; // 권한 없음
    }

    if ($is_folder) {
        // 하위 파일/폴더 재귀 삭제
        $stmt_children = $conn->prepare("SELECT id FROM files WHERE parent_id = ?");
        $stmt_children->bind_param("i", $file_id);
        $stmt_children->execute();
        $result_children = $stmt_children->get_result();
        
        while ($child = $result_children->fetch_assoc()) {
            delete_file_or_folder_recursive($conn, $child['id'], $current_user_id, $current_user_role);
        }
        $stmt_children->close();

        // 실제 디렉토리 삭제
        if (!empty($filepath) && is_dir($filepath)) {
            delete_directory_recursive($filepath);
        }

        // DB 레코드 삭제
        $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ?");
        $stmt_delete->bind_param("i", $file_id);
        $stmt_delete->execute();
        $stmt_delete->close();
    } else {
        // 실제 파일 삭제
        if (!empty($filepath) && file_exists($filepath)) {
            @unlink($filepath);
        }

        // DB 레코드 삭제
        $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ?");
        $stmt_delete->bind_param("i", $file_id);
        $stmt_delete->execute();
        $stmt_delete->close();
    }

    return true;
}

if (isset($_POST['file_ids']) && isset($_POST['action'])) {
    $file_ids = $_POST['file_ids'];
    $action = $_POST['action'];
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!empty($file_ids)) {
        // file_ids를 정수로 변환
        $file_ids = array_map('intval', $file_ids);

        if ($action === 'delete') {
            // 선택 삭제 (각 파일별로 권한 확인)
            foreach ($file_ids as $file_id) {
                delete_file_or_folder_recursive($conn, $file_id, $current_user_id, $current_user_role);
            }
        } else {
            // 공개 / 비공개 설정 (업로더 또는 관리자만 가능)
            $is_public = ($action === 'public') ? 1 : 0;

            foreach ($file_ids as $file_id) {
                // 파일 소유자 확인
                $stmt_check = $conn->prepare("SELECT user_id FROM files WHERE id = ?");
                $stmt_check->bind_param("i", $file_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                
                if ($result_check->num_rows > 0) {
                    $row = $result_check->fetch_assoc();
                    $owner_id = $row['user_id'];
                    
                    // 권한 확인: 업로더 또는 관리자만 권한 변경 가능
                    if (can_delete_file($owner_id, $current_user_id, $current_user_role)) {
                        $stmt_update = $conn->prepare("UPDATE files SET is_public = ? WHERE id = ?");
                        $stmt_update->bind_param("ii", $is_public, $file_id);
                        $stmt_update->execute();
                        $stmt_update->close();
                    }
                }
                $stmt_check->close();
            }
        }
    }

    $redirect_url = "list.php";
    if ($parent_id) {
        $redirect_url .= "?parent_id=" . $parent_id;
    }
    header("Location: " . $redirect_url);
    exit;

} else {
    echo "선택된 파일이 없거나 작업이 지정되지 않았습니다.";
}

$conn->close();
?>
