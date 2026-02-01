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

// 페이지네이션 설정
$items_per_page = 50;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($current_page - 1) * $items_per_page;

// Breadcrumb navigation (user_id 조건 제거)
$breadcrumbs = get_breadcrumbs($conn, $parent_id);

// 전체 항목 수 계산 (폴더 + 파일)
$stmt_count = $conn->prepare("SELECT COUNT(*) as total FROM files WHERE parent_id <=> ?");
$stmt_count->bind_param("i", $parent_id);
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$total_items = $count_result->fetch_assoc()['total'];
$stmt_count->close();

$total_pages = ceil($total_items / $items_per_page);

// 업로드 메시지 표시
$upload_success = isset($_SESSION['upload_success']) ? $_SESSION['upload_success'] : null;
$upload_errors = isset($_SESSION['upload_errors']) ? $_SESSION['upload_errors'] : [];
unset($_SESSION['upload_success'], $_SESSION['upload_errors']);
?>

<div class="content-card">
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

    <form action="update_permission.php" method="post">
        <input type="hidden" name="parent_id" value="<?php echo $parent_id; ?>">
        <table class="table table-hover align-middle file-list-table mb-3">
            <thead>
                <tr>
                    <th style="width:40px"><input type="checkbox" id="select_all"></th>
                    <th>파일명</th>
                    <th style="width:100px">업로더</th>
                    <th style="width:100px">공개 여부</th>
                    <th style="width:100px">크기</th>
                    <th style="width:160px">업로드 날짜</th>
                    <th style="width:260px">작업</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // List Folders (user_id 조건 제거, 업로더 정보 JOIN)
                $stmt = $conn->prepare("
                    SELECT f.id, f.filename, f.upload_date, f.is_public, f.user_id, u.username as uploader
                    FROM files f
                    LEFT JOIN users u ON f.user_id = u.id
                    WHERE f.is_folder = 1 AND f.parent_id <=> ?
                    ORDER BY f.filename ASC
                    LIMIT ? OFFSET ?
                ");
                $stmt->bind_param("iii", $parent_id, $items_per_page, $offset);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $can_delete = can_delete_file($row['user_id'], $current_user_id, $current_user_role);
                    ?>
                    <tr>
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

                // List Files (user_id 조건 제거, 업로더 정보 JOIN)
                $stmt = $conn->prepare("
                    SELECT f.id, f.filename, f.filepath, f.upload_date, f.is_public, f.user_id, u.username as uploader
                    FROM files f
                    LEFT JOIN users u ON f.user_id = u.id
                    WHERE f.is_folder = 0 AND f.parent_id <=> ?
                    ORDER BY f.filename ASC
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
                    <tr>
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
                ?>
            </tbody>
        </table>
        
        <!-- 페이지네이션 -->
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
        <div class="text-center text-muted small mb-3">
            총 <?php echo $total_items; ?>개 항목 중 <?php echo $offset + 1; ?>-<?php echo min($offset + $items_per_page, $total_items); ?>개 표시 (페이지 <?php echo $current_page; ?>/<?php echo $total_pages; ?>)
        </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-end">
            <button type="submit"
                    name="action"
                    value="delete"
                    class="btn btn-outline-danger me-2"
                    onclick="return confirm('선택한 파일/폴더를 모두 삭제하시겠습니까?');">
                선택 삭제
            </button>
            <button type="submit" name="action" value="public" class="btn btn-success me-2">공개로 설정</button>
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
</script>

<?php include_once '../inc/footer.php'; ?>
