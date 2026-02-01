<?php
include_once '../inc/header.php';
include_once '../inc/nav.php';
include_once '../inc/db.php';
include_once '../inc/file_helpers.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php");
    exit();
}

$parent_id = isset($_GET['parent_id']) ? (int)$_GET['parent_id'] : null;

// Breadcrumb navigation (user_id 조건 제거)
$breadcrumbs = get_breadcrumbs($conn, $parent_id);
?>

<div class="content-card mt-2">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-1">파일 업로드</h2>
            <!-- 현재 위치 표시 -->
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0 small">
                    <li class="breadcrumb-item">
                        <a href="list.php">Root</a>
                    </li>
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
        <!-- 뒤로가기: 현재 폴더 기준 파일 목록으로 이동 -->
        <a href="list.php<?php echo $parent_id !== null ? '?parent_id=' . $parent_id : ''; ?>" class="btn btn-outline-secondary btn-sm">
            ← 파일 목록으로
        </a>
    </div>

    <form action="upload_process.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="parent_id" value="<?php echo $parent_id !== null ? (int)$parent_id : ''; ?>">
        <div class="mb-3">
            <label for="files" class="form-label">파일 선택</label>
            <input class="form-control" type="file" id="files" name="files[]" multiple required>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_public" name="is_public" value="1">
            <label class="form-check-label" for="is_public">파일을 공개로 설정</label>
        </div>
        <button type="submit" class="btn btn-primary">업로드</button>
    </form>
</div>

<?php include_once '../inc/footer.php'; ?>
