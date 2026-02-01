<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . '/auth.php';
?>
<header>
  <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid px-4">
      <a class="navbar-brand fw-semibold" href="/">
        PNC 웹하드
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="navbarSupportedContent">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
          <?php if (is_logged_in()): ?>
            <li class="nav-item">
              <a class="nav-link" href="/file/list.php">파일 목록</a>
            </li>
            <?php if (is_admin()): ?>
              <li class="nav-item">
                <a class="nav-link" href="/admin/user_list.php">사용자 관리</a>
              </li>
            <?php endif; ?>
          <?php endif; ?>
        </ul>
        <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
          <?php if (is_logged_in()): ?>
            <li class="nav-item d-flex align-items-center me-2 text-white-50 small">
              <span class="me-1">안녕하세요,</span>
              <span class="fw-semibold">
                <?php echo htmlspecialchars($_SESSION['username'] ?? 'user'); ?>
              </span>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/user/mypage.php">마이페이지</a>
            </li>
            <li class="nav-item">
              <a class="nav-link" href="/user/logout.php">로그아웃</a>
            </li>
          <?php else: ?>
            <li class="nav-item">
              <a class="nav-link" href="/user/login.php">로그인</a>
            </li>
          <?php endif; ?>
        </ul>
      </div>
    </div>
  </nav>
</header>
<main class="app-main flex-grow-1 py-4">
  <div class="container-xl">