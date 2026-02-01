<?php
include_once '../inc/header.php';
include_once '../inc/nav.php';
include_once '../inc/db.php';
include_once '../inc/file_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['role'] ?? 'user';
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

// 정렬 파라미터 (화이트리스트 방식 - SQL 인젝션 방지)
$allowed_sort_columns = ['filename', 'upload_date', 'uploader'];
$sort_column = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_columns) ? $_GET['sort'] : 'filename';
$sort_order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'DESC' : 'ASC';

// 검색 파라미터
$search_keyword = isset($_GET['search']) ? trim($_GET['search']) : '';

// 페이지네이션 설정
$items_per_page = 50;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Breadcrumb navigation
$breadcrumbs = get_breadcrumbs($conn, $parent_id);

// 검색 모드일 때: 현재 폴더 + 하위 폴더 재귀 검색
$search_parent_ids = [];
if (!empty($search_keyword)) {
    $search_parent_ids = get_recursive_parent_ids($conn, $parent_id);
}

// 정렬 컬럼 매핑 (SQL용)
$sql_sort_column = $sort_column;
if ($sort_column === 'uploader') {
    $sql_sort_column = 'u.username';
} elseif ($sort_column === 'filename') {
    $sql_sort_column = 'f.filename';
} elseif ($sort_column === 'upload_date') {
    $sql_sort_column = 'f.upload_date';
}

// 전체 항목 수 계산
if (!empty($search_keyword)) {
    // 검색 모드: 현재 폴더 + 하위 폴더에서 검색
    $search_like = '%' . $search_keyword . '%';
    if (count($search_parent_ids) === 1 && $search_parent_ids[0] === null) {
        // Root만 검색
        $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM files WHERE parent_id IS NULL AND filename LIKE ?");
        $stmt_count->bind_param("s", $search_like);
    } else {
        // 여러 parent_id에서 검색
        $placeholders = implode(',', array_map(function($id) { return $id === null ? 'NULL' : '?'; }, $search_parent_ids));
        $non_null_ids = array_filter($search_parent_ids, function($id) { return $id !== null; });
        
        if (in_array(null, $search_parent_ids)) {
            $sql = "SELECT COUNT(*) as total FROM files WHERE (parent_id IS NULL OR parent_id IN (" . implode(',', array_fill(0, count($non_null_ids), '?')) . ")) AND filename LIKE ?";
        } else {
            $sql = "SELECT COUNT(*) as total FROM files WHERE parent_id IN (" . implode(',', array_fill(0, count($non_null_ids), '?')) . ") AND filename LIKE ?";
        }
        $stmt_count = $conn->prepare($sql);
        $types = str_repeat('i', count($non_null_ids)) . 's';
        $params = array_merge($non_null_ids, [$search_like]);
        $stmt_count->bind_param($types, ...$params);
    }
} else {
    // 일반 모드: 현재 폴더만
    $stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM files WHERE parent_id <=> ?");
    $stmt_count->bind_param("i", $parent_id);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_items = $count_result->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_items / $items_per_page);

// 이동 결과 메시지
$move_success = isset($_SESSION['move_success']) ? $_SESSION['move_success'] : null;
$move_error = isset($_SESSION['move_error']) ? $_SESSION['move_error'] : null;
$move_errors = isset($_SESSION['move_errors']) ? $_SESSION['move_errors'] : [];
unset($_SESSION['move_success'], $_SESSION['move_error'], $_SESSION['move_errors']);

// 업로드 메시지 표시
$upload_success = isset($_SESSION['upload_success']) ? $_SESSION['upload_success'] : null;
$upload_errors = isset($_SESSION['upload_errors']) ? $_SESSION['upload_errors'] : [];
unset($_SESSION['upload_success'], $_SESSION['upload_errors']);

// 정렬 토글 URL 생성 함수
function get_sort_url($column, $current_sort, $current_order) {
    $params = $_GET;
    $params['sort'] = $column;
    $params['order'] = ($current_sort === $column && $current_order === 'ASC') ? 'desc' : 'asc';
    unset($params['page']); // 정렬 변경 시 첫 페이지로
    return '?' . http_build_query($params);
}

// 정렬 아이콘 가져오기
function get_sort_icon($column, $current_sort, $current_order) {
    if ($current_sort !== $column) {
        return '<span class="text-muted">⇅</span>';
    }
    return $current_order === 'ASC' ? '▲' : '▼';
}

// 이동용 폴더 목록 가져오기 (선택된 파일들 제외는 JS에서 처리)
$all_folders = get_all_folders($conn, null);
?>

<div class="content-card">
    <?php if ($move_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($move_success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($move_error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($move_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($move_errors)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($move_errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($upload_success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($upload_success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($upload_errors)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach ($upload_errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">파일 목록</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item"><a href="list.php">Root</a></li>
                    <?php foreach ($breadcrumbs as $breadcrumb): ?>
                        <li class="breadcrumb-item">
                            <a href="list.php?parent_id=<?php echo $breadcrumb['id']; ?>">
                                <?php echo htmlspecialchars($breadcrumb['name']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        </div>
        <div class="mt-3 mt-sm-0">
            <a href="upload.php<?php echo $parent_id ? '?parent_id=' . $parent_id : ''; ?>" class="btn btn-primary me-2">파일 업로드</a>
            <form action="create_folder.php" method="post" class="d-inline-flex align-items-center">
                <input type="hidden" name="parent_id" value="<?php echo $parent_id; ?>">
                <input type="text" name="folder_name" class="form-control form-control-sm me-2" placeholder="폴더 이름" required>
                <button type="submit" class="btn btn-outline-secondary btn-sm">폴더 생성</button>
            </form>
        </div>
    </div>

    <!-- 검색 폼 -->
    <div class="mb-3">
        <form action="list.php" method="get" class="d-flex align-items-center gap-2">
            <?php if ($parent_id): ?>
                <input type="hidden" name="parent_id" value="<?php echo $parent_id; ?>">
            <?php endif; ?>
            <input type="text" 
                   name="search" 
                   class="form-control form-control-sm" 
                   style="max-width: 300px;" 
                   placeholder="파일명 검색 (현재 폴더 + 하위 폴더)" 
                   value="<?php echo htmlspecialchars($search_keyword); ?>">
            <button type="submit" class="btn btn-outline-primary btn-sm">검색</button>
            <?php if (!empty($search_keyword)): ?>
                <a href="list.php<?php echo $parent_id ? '?parent_id=' . $parent_id : ''; ?>" class="btn btn-outline-secondary btn-sm">검색 초기화</a>
            <?php endif; ?>
        </form>
        <?php if (!empty($search_keyword)): ?>
            <small class="text-muted mt-1 d-block">
                "<?php echo htmlspecialchars($search_keyword); ?>" 검색 결과: <?php echo $total_items; ?>개 항목
            </small>
        <?php endif; ?>
    </div>

    <form id="fileListForm" action="update_permission.php" method="post">
        <input type="hidden" name="parent_id" value="<?php echo $parent_id; ?>">
        <table class="table table-hover align-middle file-list-table mb-3">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="select_all"></th>
                    <th>
                        <a href="<?php echo get_sort_url('filename', $sort_column, $sort_order); ?>" class="text-decoration-none text-dark">
                            파일명 <?php echo get_sort_icon('filename', $sort_column, $sort_order); ?>
                        </a>
                    </th>
                    <th style="width:100px">
                        <a href="<?php echo get_sort_url('uploader', $sort_column, $sort_order); ?>" class="text-decoration-none text-dark">
                            업로더 <?php echo get_sort_icon('uploader', $sort_column, $sort_order); ?>
                        </a>
                    </th>
                    <th style="width:100px">공개 여부</th>
                    <th style="width:100px">크기</th>
                    <th style="width:160px">
                        <a href="<?php echo get_sort_url('upload_date', $sort_column, $sort_order); ?>" class="text-decoration-none text-dark">
                            업로드 날짜 <?php echo get_sort_icon('upload_date', $sort_column, $sort_order); ?>
                        </a>
                    </th>
                    <th style="width:260px">작업</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // 검색 모드 여부에 따라 쿼리 구성
                if (!empty($search_keyword)) {
                    // 검색 모드: 폴더와 파일 통합 검색 (현재 폴더 + 하위 폴더)
                    $search_like = '%' . $search_keyword . '%';
                    $non_null_ids = array_filter($search_parent_ids, function($id) { return $id !== null; });
                    $has_null = in_array(null, $search_parent_ids);
                    
                    if ($has_null && count($non_null_ids) > 0) {
                        $id_placeholders = implode(',', array_fill(0, count($non_null_ids), '?'));
                        $sql = "
                            SELECT f.id, f.filename, f.filepath, f.upload_date, f.is_public, f.is_folder, f.user_id, f.parent_id, u.username as uploader
                            FROM files f
                            LEFT JOIN users u ON f.user_id = u.id
                            WHERE (f.parent_id IS NULL OR f.parent_id IN ({$id_placeholders})) AND f.filename LIKE ?
                            ORDER BY f.is_folder DESC, {$sql_sort_column} {$sort_order}
                            LIMIT ? OFFSET ?
                        ";
                        $stmt = $conn->prepare($sql);
                        $types = str_repeat('i', count($non_null_ids)) . 'sii';
                        $params = array_merge($non_null_ids, [$search_like, $items_per_page, $offset]);
                        $stmt->bind_param($types, ...$params);
                    } elseif ($has_null) {
                        $sql = "
                            SELECT f.id, f.filename, f.filepath, f.upload_date, f.is_public, f.is_folder, f.user_id, f.parent_id, u.username as uploader
                            FROM files f
                            LEFT JOIN users u ON f.user_id = u.id
                            WHERE f.parent_id IS NULL AND f.filename LIKE ?
                            ORDER BY f.is_folder DESC, {$sql_sort_column} {$sort_order}
                            LIMIT ? OFFSET ?
                        ";
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param("sii", $search_like, $items_per_page, $offset);
                    } else {
                        $id_placeholders = implode(',', array_fill(0, count($non_null_ids), '?'));
                        $sql = "
                            SELECT f.id, f.filename, f.filepath, f.upload_date, f.is_public, f.is_folder, f.user_id, f.parent_id, u.username as uploader
                            FROM files f
                            LEFT JOIN users u ON f.user_id = u.id
                            WHERE f.parent_id IN ({$id_placeholders}) AND f.filename LIKE ?
                            ORDER BY f.is_folder DESC, {$sql_sort_column} {$sort_order}
                            LIMIT ? OFFSET ?
                        ";
                        $stmt = $conn->prepare($sql);
                        $types = str_repeat('i', count($non_null_ids)) . 'sii';
                        $params = array_merge($non_null_ids, [$search_like, $items_per_page, $offset]);
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $can_delete = can_delete_file($row['user_id'], $current_user_id, $current_user_role);
                        
                        if ($row['is_folder']) {
                            // 폴더 표시
                            ?>
                            <tr data-file-id="<?php echo $row['id']; ?>" data-is-folder="1">
                                <td><input type="checkbox" name="file_ids[]" value="<?php echo $row['id']; ?>"></td>
                                <td>
                                    <a href="list.php?parent_id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                        📁 <?php echo htmlspecialchars($row['filename']); ?>
                                    </a>
                                    <?php if ($row['parent_id'] != $parent_id): ?>
                                        <small class="text-muted">(<?php 
                                            $item_breadcrumbs = get_breadcrumbs($conn, $row['parent_id']);
                                            echo 'Root';
                                            foreach ($item_breadcrumbs as $b) {
                                                echo ' / ' . htmlspecialchars($b['name']);
                                            }
                                        ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['uploader'] ?? '알 수 없음'); ?></small></td>
                                <td><?php echo $row['is_public'] ? '<span class="badge bg-success">공개</span>' : '<span class="badge bg-secondary">비공개</span>'; ?></td>
                                <td>-</td>
                                <td><small><?php echo $row['upload_date']; ?></small></td>
                                <td>
                                    <?php if ($can_delete): ?>
                                        <a href="delete.php?file_id=<?php echo $row['id']; ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           onclick="return confirm('폴더 [<?php echo htmlspecialchars($row['filename'], ENT_QUOTES); ?>]를 삭제하시겠습니까?\n\n하위 폴더와 파일이 모두 삭제됩니다.');">삭제</a>
                                    <?php else: ?>
                                        <span class="text-muted small">삭제 권한 없음</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        } else {
                            // 파일 표시
                            $filesize = (!empty($row['filepath']) && file_exists($row['filepath'])) ? filesize($row['filepath']) : 0;
                            if ($filesize >= 1048576) {
                                $size_display = round($filesize / 1048576, 2) . ' MB';
                            } else {
                                $size_display = round($filesize / 1024, 2) . ' KB';
                            }
                            ?>
                            <tr data-file-id="<?php echo $row['id']; ?>" data-is-folder="0">
                                <td><input type="checkbox" name="file_ids[]" value="<?php echo $row['id']; ?>"></td>
                                <td>
                                    <?php echo htmlspecialchars($row['filename']); ?>
                                    <?php if ($row['parent_id'] != $parent_id): ?>
                                        <small class="text-muted">(<?php 
                                            $item_breadcrumbs = get_breadcrumbs($conn, $row['parent_id']);
                                            echo 'Root';
                                            foreach ($item_breadcrumbs as $b) {
                                                echo ' / ' . htmlspecialchars($b['name']);
                                            }
                                        ?>)</small>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?php echo htmlspecialchars($row['uploader'] ?? '알 수 없음'); ?></small></td>
                                <td><?php echo $row['is_public'] ? '<span class="badge bg-success">공개</span>' : '<span class="badge bg-secondary">비공개</span>'; ?></td>
                                <td><small><?php echo $size_display; ?></small></td>
                                <td><small><?php echo $row['upload_date']; ?></small></td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if ($can_delete): ?>
                                            <a href="delete.php?file_id=<?php echo $row['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('파일을 삭제하시겠습니까?');">삭제</a>
                                        <?php endif; ?>
                                        <a href="download.php?file_id=<?php echo $row['id']; ?>" class="btn btn-outline-success">다운로드</a>
                                        <button type="button" class="btn btn-outline-info" onclick="copyDownloadLink(<?php echo $row['id']; ?>)">링크</button>
                                        <button type="button" class="btn btn-outline-primary" onclick="copyWebViewLink(<?php echo $row['id']; ?>)">뷰</button>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    $stmt->close();
                } else {
                    // 일반 모드: 폴더 먼저, 파일 나중에 (기존 로직 유지하되 정렬 적용)
                    
                    // List Folders
                    $stmt = $conn->prepare("
                        SELECT f.id, f.filename, f.upload_date, f.is_public, f.user_id, u.username as uploader
                        FROM files f
                        LEFT JOIN users u ON f.user_id = u.id
                        WHERE f.is_folder = 1 AND f.parent_id <=> ?
                        ORDER BY {$sql_sort_column} {$sort_order}
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->bind_param("iii", $parent_id, $items_per_page, $offset);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $can_delete = can_delete_file($row['user_id'], $current_user_id, $current_user_role);
                        ?>
                        <tr data-file-id="<?php echo $row['id']; ?>" data-is-folder="1">
                            <td><input type="checkbox" name="file_ids[]" value="<?php echo $row['id']; ?>"></td>
                            <td>
                                <a href="list.php?parent_id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                    📁 <?php echo htmlspecialchars($row['filename']); ?>
                                </a>
                            </td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($row['uploader'] ?? '알 수 없음'); ?></small></td>
                            <td><?php echo $row['is_public'] ? '<span class="badge bg-success">공개</span>' : '<span class="badge bg-secondary">비공개</span>'; ?></td>
                            <td>-</td>
                            <td><small><?php echo $row['upload_date']; ?></small></td>
                            <td>
                                <?php if ($can_delete): ?>
                                    <a href="delete.php?file_id=<?php echo $row['id']; ?>" 
                                       class="btn btn-outline-danger btn-sm" 
                                       onclick="return confirm('폴더 [<?php echo htmlspecialchars($row['filename'], ENT_QUOTES); ?>]를 삭제하시겠습니까?\n\n하위 폴더와 파일이 모두 삭제됩니다.');">삭제</a>
                                <?php else: ?>
                                    <span class="text-muted small">삭제 권한 없음</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                    }
                    $stmt->close();

                    // List Files
                    $stmt = $conn->prepare("
                        SELECT f.id, f.filename, f.filepath, f.upload_date, f.is_public, f.user_id, u.username as uploader
                        FROM files f
                        LEFT JOIN users u ON f.user_id = u.id
                        WHERE f.is_folder = 0 AND f.parent_id <=> ?
                        ORDER BY {$sql_sort_column} {$sort_order}
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->bind_param("iii", $parent_id, $items_per_page, $offset);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        $filesize = (!empty($row['filepath']) && file_exists($row['filepath'])) ? filesize($row['filepath']) : 0;
                        $can_delete = can_delete_file($row['user_id'], $current_user_id, $current_user_role);
                        
                        // 파일 크기 포맷팅
                        if ($filesize >= 1048576) {
                            $size_display = round($filesize / 1048576, 2) . ' MB';
                        } else {
                            $size_display = round($filesize / 1024, 2) . ' KB';
                        }
                        ?>
                        <tr data-file-id="<?php echo $row['id']; ?>" data-is-folder="0">
                            <td><input type="checkbox" name="file_ids[]" value="<?php echo $row['id']; ?>"></td>
                            <td><?php echo htmlspecialchars($row['filename']); ?></td>
                            <td><small class="text-muted"><?php echo htmlspecialchars($row['uploader'] ?? '알 수 없음'); ?></small></td>
                            <td><?php echo $row['is_public'] ? '<span class="badge bg-success">공개</span>' : '<span class="badge bg-secondary">비공개</span>'; ?></td>
                            <td><small><?php echo $size_display; ?></small></td>
                            <td><small><?php echo $row['upload_date']; ?></small></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <?php if ($can_delete): ?>
                                        <a href="delete.php?file_id=<?php echo $row['id']; ?>" class="btn btn-outline-danger" onclick="return confirm('파일을 삭제하시겠습니까?');">삭제</a>
                                    <?php endif; ?>
                                    <a href="download.php?file_id=<?php echo $row['id']; ?>" class="btn btn-outline-success">다운로드</a>
                                    <button type="button" class="btn btn-outline-info" onclick="copyDownloadLink(<?php echo $row['id']; ?>)">링크</button>
                                    <button type="button" class="btn btn-outline-primary" onclick="copyWebViewLink(<?php echo $row['id']; ?>)">뷰</button>
                                </div>
                            </td>
                        </tr>
                        <?php
                    }
                    $stmt->close();
                }
                ?>
            </tbody>
        </table>
        
        <!-- 항목 수 정보 (항상 표시) -->
        <div class="text-center text-muted small mb-3">
            <?php if ($total_items > 0): ?>
                총 <?php echo $total_items; ?>개 항목 중 <?php echo min($offset + 1, $total_items); ?>-<?php echo min($offset + $items_per_page, $total_items); ?>개 표시
                <?php if ($total_pages > 1): ?>
                    (페이지 <?php echo $current_page; ?>/<?php echo $total_pages; ?>)
                <?php endif; ?>
            <?php else: ?>
                항목이 없습니다.
            <?php endif; ?>
        </div>
        
        <!-- 페이지네이션 (2페이지 이상일 때만 네비게이션 표시) -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mb-3">
            <ul class="pagination justify-content-center">
                <?php if ($current_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>">이전</a>
                    </li>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a></li>
                    <?php if ($start_page > 2): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <li class="page-item disabled"><span class="page-link">...</span></li>
                    <?php endif; ?>
                    <li class="page-item"><a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>"><?php echo $total_pages; ?></a></li>
                <?php endif; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>">다음</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
        <?php endif; ?>
        
        <div class="d-flex justify-content-end flex-wrap gap-2">
            <button type="button" class="btn btn-outline-info" onclick="openMoveModal()">선택 이동</button>
            <button type="submit"
                    name="action"
                    value="delete"
                    class="btn btn-outline-danger"
                    onclick="return confirm('선택한 파일/폴더를 모두 삭제하시겠습니까?');">
                선택 삭제
            </button>
            <button type="submit" name="action" value="public" class="btn btn-success">공개로 설정</button>
            <button type="submit" name="action" value="private" class="btn btn-warning">비공개로 설정</button>
        </div>
    </form>

    <!-- 링크 복사 모달 -->
    <div class="modal fade" id="linkModal" tabindex="-1" aria-labelledby="linkModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="linkModalLabel">링크 복사</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="linkTextBox" class="form-label">링크 주소</label>
                        <input type="text" class="form-control" id="linkTextBox" readonly>
                    </div>
                    <div id="linkCopyMessage" class="text-success fw-bold"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">확인</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 파일 이동 모달 -->
    <div class="modal fade" id="moveModal" tabindex="-1" aria-labelledby="moveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="move.php" method="post">
                    <input type="hidden" name="original_parent_id" value="<?php echo $parent_id; ?>">
                    <div id="moveFileIdsContainer"></div>
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="moveModalLabel">파일/폴더 이동</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="targetFolder" class="form-label">이동할 위치 선택</label>
                            <select class="form-select" id="targetFolder" name="target_parent_id" required>
                                <option value="">-- 폴더 선택 --</option>
                                <?php foreach ($all_folders as $folder): ?>
                                    <option value="<?php echo $folder['id'] === null ? 'null' : $folder['id']; ?>">
                                        <?php echo htmlspecialchars($folder['path']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="moveSelectedItems" class="small text-muted"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                        <button type="submit" class="btn btn-primary">이동</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('select_all').addEventListener('click', function(event) {
    var checkboxes = document.querySelectorAll('input[name="file_ids[]"]');
    for (var checkbox of checkboxes) {
        checkbox.checked = event.target.checked;
    }
});

function openLinkModal(link) {
    var linkInput = document.getElementById('linkTextBox');
    var messageEl = document.getElementById('linkCopyMessage');

    if (!linkInput || !messageEl) {
        return;
    }

    linkInput.value = link;
    messageEl.textContent = '';

    navigator.clipboard.writeText(link).then(function() {
        messageEl.textContent = '링크가 클립보드에 복사되었습니다.';
    }).catch(function() {
        messageEl.textContent = '클립보드 복사에 실패했습니다. 수동으로 복사해 주세요.';
    });

    var modalEl = document.getElementById('linkModal');
    if (modalEl) {
        var modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}

function copyDownloadLink(fileId) {
    var link = window.location.protocol + '//' + window.location.host + '/file/download.php?file_id=' + fileId;
    openLinkModal(link);
}

function copyWebViewLink(fileId) {
    var link = window.location.protocol + '//' + window.location.host + '/file/view.php?file_id=' + fileId;
    openLinkModal(link);
}

function openMoveModal() {
    var checkboxes = document.querySelectorAll('input[name="file_ids[]"]:checked');
    
    if (checkboxes.length === 0) {
        alert('이동할 파일/폴더를 선택해주세요.');
        return;
    }
    
    // 선택된 파일 ID들을 모달 폼에 추가
    var container = document.getElementById('moveFileIdsContainer');
    container.innerHTML = '';
    
    var selectedItems = [];
    checkboxes.forEach(function(checkbox) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'file_ids[]';
        input.value = checkbox.value;
        container.appendChild(input);
        
        // 파일명 찾기
        var row = checkbox.closest('tr');
        var filenameCell = row.cells[1];
        selectedItems.push(filenameCell.textContent.trim());
    });
    
    // 선택된 항목 표시
    document.getElementById('moveSelectedItems').textContent = 
        '선택된 항목: ' + selectedItems.join(', ');
    
    // 모달 열기
    var modalEl = document.getElementById('moveModal');
    var modal = new bootstrap.Modal(modalEl);
    modal.show();
}
</script>

<?php include_once '../inc/footer.php'; ?>
