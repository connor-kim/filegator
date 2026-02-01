<?php
session_start();
include_once '../inc/db.php';
include_once '../inc/file_helpers.php';

if (!isset($_SESSION['user_id'])) {
    die("파일을 삭제하려면 로그인해야 합니다.");
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? 'user';

if (isset($_GET['file_id'])) {
    $file_id = (int)$_GET['file_id'];

    /**
     * 파일 또는 폴더를 재귀적으로 삭제
     * 업로더 또는 관리자만 삭제 가능
     */
    function delete_file_or_folder($conn, $file_id, $current_user_id, $current_user_role) {
        // 파일/폴더 정보 조회
        $stmt = $conn->prepare("SELECT user_id, filename, filepath, is_folder FROM files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return ['success' => false, 'message' => '파일을 찾을 수 없습니다.'];
        }

        $row = $result->fetch_assoc();
        $owner_id = $row['user_id'];
        $filename = $row['filename'];
        $filepath = $row['filepath'];
        $is_folder = $row['is_folder'];
        $stmt->close();

        // 삭제 권한 확인 (업로더 또는 관리자만)
        if (!can_delete_file($owner_id, $current_user_id, $current_user_role)) {
            return ['success' => false, 'message' => '삭제 권한이 없습니다. 업로더 또는 관리자만 삭제할 수 있습니다.'];
        }

        if ($is_folder) {
            // 폴더인 경우: 하위 파일/폴더 먼저 삭제
            $stmt_children = $conn->prepare("SELECT id FROM files WHERE parent_id = ?");
            $stmt_children->bind_param("i", $file_id);
            $stmt_children->execute();
            $result_children = $stmt_children->get_result();
            
            while ($child = $result_children->fetch_assoc()) {
                // 하위 항목은 부모 권한으로 삭제 (부모 삭제 권한이 있으면 하위도 삭제)
                delete_file_or_folder_force($conn, $child['id']);
            }
            $stmt_children->close();

            // 실제 디렉토리 삭제 (비어있어야 함)
            if (!empty($filepath) && is_dir($filepath)) {
                // 디렉토리가 비어있으면 삭제
                $files_in_dir = @scandir($filepath);
                if ($files_in_dir !== false && count(array_diff($files_in_dir, ['.', '..'])) === 0) {
                    @rmdir($filepath);
                }
            }

            // DB에서 폴더 삭제
            $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ?");
            $stmt_delete->bind_param("i", $file_id);
            $stmt_delete->execute();
            $stmt_delete->close();

        } else {
            // 파일인 경우: 실제 파일 삭제
            if (!empty($filepath) && file_exists($filepath)) {
                unlink($filepath);
            }

            // DB에서 파일 삭제
            $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ?");
            $stmt_delete->bind_param("i", $file_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        }

        return ['success' => true, 'message' => '삭제 완료'];
    }

    /**
     * 권한 체크 없이 강제 삭제 (하위 항목 삭제용)
     */
    function delete_file_or_folder_force($conn, $file_id) {
        $stmt = $conn->prepare("SELECT filepath, is_folder FROM files WHERE id = ?");
        $stmt->bind_param("i", $file_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $stmt->close();
            return;
        }

        $row = $result->fetch_assoc();
        $filepath = $row['filepath'];
        $is_folder = $row['is_folder'];
        $stmt->close();

        if ($is_folder) {
            // 하위 항목 재귀 삭제
            $stmt_children = $conn->prepare("SELECT id FROM files WHERE parent_id = ?");
            $stmt_children->bind_param("i", $file_id);
            $stmt_children->execute();
            $result_children = $stmt_children->get_result();
            
            while ($child = $result_children->fetch_assoc()) {
                delete_file_or_folder_force($conn, $child['id']);
            }
            $stmt_children->close();

            // 실제 디렉토리 삭제
            if (!empty($filepath) && is_dir($filepath)) {
                $files_in_dir = @scandir($filepath);
                if ($files_in_dir !== false && count(array_diff($files_in_dir, ['.', '..'])) === 0) {
                    @rmdir($filepath);
                }
            }
        } else {
            // 실제 파일 삭제
            if (!empty($filepath) && file_exists($filepath)) {
                unlink($filepath);
            }
        }

        // DB에서 삭제
        $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ?");
        $stmt_delete->bind_param("i", $file_id);
        $stmt_delete->execute();
        $stmt_delete->close();
    }

    $result = delete_file_or_folder($conn, $file_id, $current_user_id, $current_user_role);
    
    if (!$result['success']) {
        die($result['message']);
    }

    // 리다이렉트 (referer가 있으면 그쪽으로, 없으면 list.php로)
    $redirect_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'list.php';
    header("Location: " . $redirect_url);
    exit();

} else {
    die("파일이 지정되지 않았습니다.");
}

$conn->close();
?>
