<?php
include_once '../inc/header.php';
include_once '../inc/nav.php';
include_once '../inc/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

// Breadcrumb navigation
$breadcrumbs = [];
$current_parent = $parent_id;
while ($current_parent !== null) {
    $stmt = $conn->prepare("SELECT parent_id, filename FROM files WHERE id = ? AND user_id = ? AND is_folder = 1");
    $stmt->bind_param("ii", $current_parent, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        array_unshift($breadcrumbs, ['id' => $current_parent, 'name' => $row['filename']]);
        $current_parent = $row['parent_id'];
    } else {
        $current_parent = null;
    }
    $stmt->close();
}
?>

<div class="content-card">
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
            <a href="upload.php?parent_id=<?php echo $parent_id; ?>" class="btn btn-primary me-2">파일 업로드</a>
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
                    <th style="width:120px">공개 여부</th>
                    <th style="width:120px">크기</th>
                    <th style="width:180px">업로드 날짜</th>
                    <th style="width:260px">작업</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // List Folders
                $stmt = $conn->prepare("SELECT id, filename, upload_date, is_public FROM files WHERE user_id = ? AND is_folder = 1 AND parent_id <=> ? ORDER BY filename ASC");
                $stmt->bind_param("ii", $user_id, $parent_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td><input type="checkbox" name="file_ids[]" value="<?php echo $row['id']; ?>"></td>
                        <td>
                            <a href="list.php?parent_id=<?php echo $row['id']; ?>" class="text-decoration-none">
                                [<?php echo htmlspecialchars($row['filename']); ?>]
                            </a>
                        </td>
                        <td><?php echo $row['is_public'] ? '공개' : '비공개'; ?></td>
                        <td>-</td>
                        <td><?php echo $row['upload_date']; ?></td>
                        <td>
                            <a href="delete.php?file_id=<?php echo $row['id']; ?>" 
                               class="btn btn-outline-danger btn-sm" 
                               onclick="return confirm('폴더 [<?php echo htmlspecialchars($row['filename'], ENT_QUOTES); ?>]를 삭제하시겠습니까?\n\n하위 폴더와 파일이 모두 삭제됩니다.');">삭제</a>
                        </td>
                    </tr>
                    <?php
                }
                $stmt->close();

                // List Files
                $stmt = $conn->prepare("SELECT id, filename, filepath, upload_date, is_public FROM files WHERE user_id = ? AND is_folder = 0 AND parent_id <=> ? ORDER BY filename ASC");
                $stmt->bind_param("ii", $user_id, $parent_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $filesize = file_exists($row['filepath']) ? filesize($row['filepath']) : 0;
                    ?>
                    <tr>
                        <td><input type="checkbox" name="file_ids[]" value="<?php echo $row['id']; ?>"></td>
                        <td><?php echo htmlspecialchars($row['filename']); ?></td>
                        <td><?php echo $row['is_public'] ? '공개' : '비공개'; ?></td>
                        <td><?php echo round($filesize / 1024, 2); ?> KB</td>
                        <td><?php echo $row['upload_date']; ?></td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="delete.php?file_id=<?php echo $row['id']; ?>" class="btn btn-outline-danger">삭제</a>
                                <a href="download.php?file_id=<?php echo $row['id']; ?>" class="btn btn-outline-success">다운로드</a>
                                <button type="button" class="btn btn-outline-info" onclick="copyDownloadLink(<?php echo $row['id']; ?>)">다운로드 링크</button>
                                <button type="button" class="btn btn-outline-primary" onclick="copyWebViewLink(<?php echo $row['id']; ?>)">웹뷰 링크</button>
                            </div>
                        </td>
                    </tr>
                    <?php
                }
                $stmt->close();
                ?>
            </tbody>
        </table>
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

    // 링크를 클립보드에 복사
    navigator.clipboard.writeText(link).then(function() {
        messageEl.textContent = '링크가 클립보드에 복사되었습니다.';
    }).catch(function() {
        messageEl.textContent = '클립보드 복사에 실패했습니다. 수동으로 복사해 주세요.';
    });

    // 모달 열기
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