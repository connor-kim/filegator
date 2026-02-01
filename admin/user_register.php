<?php
include_once '../inc/header.php';
include_once '../inc/nav.php';
require_once '../inc/auth.php';

require_admin();
?>

<div class="content-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-0">신규 사용자 등록</h2>
        </div>
        <!-- 뒤로가기: 사용자 목록으로 이동 -->
        <a href="user_list.php" class="btn btn-outline-secondary btn-sm">
            ← 사용자 목록으로
        </a>
    </div>

    <form action="user_register_process.php" method="post">
        <div class="mb-3">
            <label for="username" class="form-label">아이디</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="mb-3">
            <label for="password" class="form-label">비밀번호</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <div class="mb-3">
            <label for="role" class="form-label">권한</label>
            <select class="form-select" id="role" name="role">
                <option value="user" selected>일반 사용자</option>
                <option value="admin">관리자</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">등록</button>
    </form>
</div>

<?php include_once '../inc/footer.php'; ?>
