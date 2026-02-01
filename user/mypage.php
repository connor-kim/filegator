<?php
include_once '../inc/header.php';
include_once '../inc/nav.php';
require_once '../inc/auth.php';

require_login();
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h2 class="text-center">마이페이지</h2>
            <h4 class="text-center mb-4">비밀번호 변경</h4>
            <form action="change_password_process.php" method="post">
                <div class="mb-3">
                    <label for="current_password" class="form-label">현재 비밀번호</label>
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
                <div class="mb-3">
                    <label for="new_password" class="form-label">새 비밀번호</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_new_password" class="form-label">새 비밀번호 확인</label>
                    <input type="password" class="form-control" id="confirm_new_password" name="confirm_new_password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">비밀번호 변경</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../inc/footer.php'; ?>
