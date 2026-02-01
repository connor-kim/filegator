<?php
session_start();
include_once '../inc/db.php';

if (!isset($_SESSION['user_id'])) {
    die("You must be logged in to change file permissions.");
}

/**
 * 파일 또는 폴더(하위 포함) 삭제
 */
function delete_file_or_folder($conn, $user_id, $file_id) {
    $stmt = $conn->prepare("SELECT filename, filepath, is_folder FROM files WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $file_id, $user_id);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($filename, $filepath, $is_folder);
        $stmt->fetch();

        if ($is_folder) {
            // 하위 파일/폴더 재귀 삭제
            $stmt_select_children = $conn->prepare("SELECT id FROM files WHERE parent_id = ? AND user_id = ?");
            $stmt_select_children->bind_param("ii", $file_id, $user_id);
            $stmt_select_children->execute();
            $result_children = $stmt_select_children->get_result();
            while ($row = $result_children->fetch_assoc()) {
                delete_file_or_folder($conn, $user_id, $row['id']);
            }
            $stmt_select_children->close();

            // 폴더 자체 삭제
            $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
            $stmt_delete->bind_param("ii", $file_id, $user_id);
            $stmt_delete->execute();
            $stmt_delete->close();

        } else {
            // 실제 파일 삭제
            if (!empty($filepath) && file_exists($filepath)) {
                @unlink($filepath);
            }

            // DB 레코드 삭제
            $stmt_delete = $conn->prepare("DELETE FROM files WHERE id = ? AND user_id = ?");
            $stmt_delete->bind_param("ii", $file_id, $user_id);
            $stmt_delete->execute();
            $stmt_delete->close();
        }
    }
    $stmt->close();
}

if (isset($_POST['file_ids']) && isset($_POST['action'])) {
    $user_id = $_SESSION['user_id'];
    $file_ids = $_POST['file_ids'];
    $action = $_POST['action'];
    $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if (!empty($file_ids)) {
        // Ensure file_ids are all integers
        $file_ids = array_map('intval', $file_ids);

        if ($action === 'delete') {
            // 선택 삭제
            foreach ($file_ids as $file_id) {
                delete_file_or_folder($conn, $user_id, $file_id);
            }
        } else {
            // 공개 / 비공개 설정
            $is_public = ($action === 'public') ? 1 : 0;

            $placeholders = implode(',', array_fill(0, count($file_ids), '?'));
            $sql = "UPDATE files SET is_public = ? WHERE user_id = ? AND id IN ($placeholders)";

            $stmt = $conn->prepare($sql);

            $types = "ii" . str_repeat('i', count($file_ids));
            $params = array_merge([$is_public, $user_id], $file_ids);

            // mysqli_stmt::bind_param requires parameters by reference
            $bind_names = [];
            $bind_names[] = $types;
            for ($i=0; $i<count($params);$i++) {
                $bind_name = 'bind' . $i;
                $$bind_name = $params[$i];
                $bind_names[] = &$$bind_name;
            }
            
            call_user_func_array(array($stmt,'bind_param'), $bind_names);

            if (!$stmt->execute()) {
                echo "Error updating permissions: " . $stmt->error;
            }
            $stmt->close();
        }
    }

    $redirect_url = "list.php";
    if ($parent_id) {
        $redirect_url .= "?parent_id=" . $parent_id;
    }
    header("Location: " . $redirect_url);

} else {
    echo "No files selected or action specified.";
}

$conn->close();
?>
