<?php
/**
 * 파일/폴더 경로 관련 헬퍼 함수들
 */

/**
 * 폴더명을 파일시스템에 안전한 이름으로 변환
 * 한글, 영문, 숫자, 하이픈, 언더스코어만 허용
 * 
 * @param string $folderName 원본 폴더명
 * @return string 안전한 폴더명
 */
function sanitize_folder_name($folderName) {
    // 공백을 언더스코어로 변환
    $safe = str_replace(' ', '_', $folderName);
    // 허용되지 않는 문자 제거 (한글, 영문, 숫자, 하이픈, 언더스코어만 허용)
    $safe = preg_replace('/[^\p{L}\p{N}_\-]/u', '', $safe);
    // 빈 문자열이면 기본값
    if (empty($safe)) {
        $safe = 'folder_' . time();
    }
    return $safe;
}

/**
 * 폴더 ID를 기반으로 실제 파일시스템 경로를 계산
 * parent_id를 따라가며 전체 경로를 구성
 * 
 * @param mysqli $conn DB 연결
 * @param int|null $folder_id 폴더 ID (null이면 루트)
 * @return string 실제 파일시스템 경로 (예: ../repository/프로젝트A/문서)
 */
function get_folder_path($conn, $folder_id) {
    $base_path = '../repository';
    
    if ($folder_id === null || $folder_id === 0 || $folder_id === '') {
        return $base_path;
    }
    
    $path_parts = [];
    $current_id = (int)$folder_id;
    $max_depth = 50; // 무한 루프 방지
    $depth = 0;
    
    while ($current_id !== null && $current_id !== 0 && $depth < $max_depth) {
        $stmt = $conn->prepare("SELECT filename, parent_id FROM files WHERE id = ? AND is_folder = 1");
        $stmt->bind_param("i", $current_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // 폴더명을 안전하게 변환하여 경로에 추가
            array_unshift($path_parts, sanitize_folder_name($row['filename']));
            $current_id = $row['parent_id'];
        } else {
            break;
        }
        $stmt->close();
        $depth++;
    }
    
    if (empty($path_parts)) {
        return $base_path;
    }
    
    return $base_path . '/' . implode('/', $path_parts);
}

/**
 * 폴더 경로가 존재하지 않으면 생성
 * 
 * @param string $path 생성할 경로
 * @return bool 성공 여부
 */
function ensure_directory_exists($path) {
    if (!is_dir($path)) {
        return mkdir($path, 0755, true);
    }
    return true;
}

/**
 * 디렉토리와 그 내용을 재귀적으로 삭제
 * 
 * @param string $dir 삭제할 디렉토리 경로
 * @return bool 성공 여부
 */
function delete_directory_recursive($dir) {
    if (!is_dir($dir)) {
        return true;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            delete_directory_recursive($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

/**
 * 사용자가 파일/폴더를 삭제할 권한이 있는지 확인
 * 업로더 또는 관리자만 삭제 가능
 * 
 * @param int $file_owner_id 파일 소유자 ID
 * @param int $current_user_id 현재 로그인 사용자 ID
 * @param string $current_user_role 현재 사용자 역할
 * @return bool 삭제 권한 여부
 */
function can_delete_file($file_owner_id, $current_user_id, $current_user_role) {
    // 관리자는 모든 파일 삭제 가능
    if ($current_user_role === 'admin') {
        return true;
    }
    // 업로더만 자신의 파일 삭제 가능
    return $file_owner_id == $current_user_id;
}

/**
 * breadcrumb 경로를 가져옴 (user_id 조건 없음 - 모든 사용자가 볼 수 있음)
 * 
 * @param mysqli $conn DB 연결
 * @param int|null $parent_id 현재 폴더 ID
 * @return array breadcrumb 배열 [['id' => 1, 'name' => '폴더명'], ...]
 */
function get_breadcrumbs($conn, $parent_id) {
    $breadcrumbs = [];
    $current_parent = $parent_id;
    $max_depth = 50;
    $depth = 0;
    
    while ($current_parent !== null && $current_parent !== 0 && $depth < $max_depth) {
        $stmt = $conn->prepare("SELECT id, parent_id, filename FROM files WHERE id = ? AND is_folder = 1");
        $stmt->bind_param("i", $current_parent);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            array_unshift($breadcrumbs, ['id' => $row['id'], 'name' => $row['filename']]);
            $current_parent = $row['parent_id'];
        } else {
            break;
        }
        $stmt->close();
        $depth++;
    }
    
    return $breadcrumbs;
}

/**
 * 모든 폴더 목록을 flat list로 가져옴 (이동 기능용)
 * 경로 형태로 표시: "Root / 프로젝트A / 문서"
 * 
 * @param mysqli $conn DB 연결
 * @param int|null $exclude_id 제외할 폴더 ID (자기 자신 또는 하위 폴더로 이동 방지)
 * @return array 폴더 배열 [['id' => 1, 'path' => 'Root / 폴더명'], ...]
 */
function get_all_folders($conn, $exclude_id = null) {
    $folders = [];
    
    // Root 폴더 추가
    $folders[] = ['id' => null, 'path' => 'Root (최상위)'];
    
    // 제외할 폴더의 모든 하위 폴더 ID 가져오기
    $exclude_ids = [];
    if ($exclude_id !== null) {
        $exclude_ids = get_descendant_folder_ids($conn, $exclude_id);
        $exclude_ids[] = $exclude_id; // 자기 자신도 제외
    }
    
    // 모든 폴더 가져오기
    $stmt = $conn->prepare("SELECT id, filename, parent_id FROM files WHERE is_folder = 1 ORDER BY filename ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $all_folders = [];
    while ($row = $result->fetch_assoc()) {
        $all_folders[$row['id']] = $row;
    }
    $stmt->close();
    
    // 각 폴더의 전체 경로 구성
    foreach ($all_folders as $folder) {
        // 제외 목록에 있으면 건너뛰기
        if (in_array($folder['id'], $exclude_ids)) {
            continue;
        }
        
        $path_parts = [$folder['filename']];
        $current_parent = $folder['parent_id'];
        $max_depth = 50;
        $depth = 0;
        
        while ($current_parent !== null && isset($all_folders[$current_parent]) && $depth < $max_depth) {
            array_unshift($path_parts, $all_folders[$current_parent]['filename']);
            $current_parent = $all_folders[$current_parent]['parent_id'];
            $depth++;
        }
        
        $folders[] = [
            'id' => $folder['id'],
            'path' => 'Root / ' . implode(' / ', $path_parts)
        ];
    }
    
    return $folders;
}

/**
 * 특정 폴더의 모든 하위 폴더 ID를 재귀적으로 가져옴
 * 
 * @param mysqli $conn DB 연결
 * @param int $folder_id 폴더 ID
 * @return array 하위 폴더 ID 배열
 */
function get_descendant_folder_ids($conn, $folder_id) {
    $descendants = [];
    
    $stmt = $conn->prepare("SELECT id FROM files WHERE parent_id = ? AND is_folder = 1");
    $stmt->bind_param("i", $folder_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $descendants[] = $row['id'];
        // 재귀적으로 하위 폴더 가져오기
        $sub_descendants = get_descendant_folder_ids($conn, $row['id']);
        $descendants = array_merge($descendants, $sub_descendants);
    }
    $stmt->close();
    
    return $descendants;
}

/**
 * 특정 폴더가 다른 폴더의 하위인지 확인
 * (이동 시 순환 참조 방지용)
 * 
 * @param mysqli $conn DB 연결
 * @param int $folder_id 확인할 폴더 ID
 * @param int $potential_parent_id 잠재적 부모 폴더 ID
 * @return bool 하위 폴더이면 true
 */
function is_descendant_folder($conn, $folder_id, $potential_parent_id) {
    if ($folder_id === $potential_parent_id) {
        return true;
    }
    
    $descendants = get_descendant_folder_ids($conn, $folder_id);
    return in_array($potential_parent_id, $descendants);
}

/**
 * 특정 폴더와 그 하위 폴더의 모든 파일/폴더 ID를 재귀적으로 가져옴 (검색용)
 * 
 * @param mysqli $conn DB 연결
 * @param int|null $parent_id 시작 폴더 ID (null이면 Root)
 * @return array 모든 하위 파일/폴더의 parent_id 목록 (검색 시 WHERE parent_id IN (...) 용)
 */
function get_recursive_parent_ids($conn, $parent_id) {
    $parent_ids = [$parent_id]; // 현재 폴더 포함
    
    // 현재 폴더의 하위 폴더들 가져오기
    if ($parent_id === null) {
        $stmt = $conn->prepare("SELECT id FROM files WHERE parent_id IS NULL AND is_folder = 1");
    } else {
        $stmt = $conn->prepare("SELECT id FROM files WHERE parent_id = ? AND is_folder = 1");
        $stmt->bind_param("i", $parent_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // 재귀적으로 하위 폴더 추가
        $sub_parent_ids = get_recursive_parent_ids($conn, $row['id']);
        $parent_ids = array_merge($parent_ids, $sub_parent_ids);
    }
    $stmt->close();
    
    return $parent_ids;
}

/**
 * 사용자가 파일/폴더를 이동할 권한이 있는지 확인
 * 업로더 또는 관리자만 이동 가능
 * 
 * @param int $file_owner_id 파일 소유자 ID
 * @param int $current_user_id 현재 로그인 사용자 ID
 * @param string $current_user_role 현재 사용자 역할
 * @return bool 이동 권한 여부
 */
function can_move_file($file_owner_id, $current_user_id, $current_user_role) {
    // 관리자는 모든 파일 이동 가능
    if ($current_user_role === 'admin') {
        return true;
    }
    // 업로더만 자신의 파일 이동 가능
    return $file_owner_id == $current_user_id;
}
?>
