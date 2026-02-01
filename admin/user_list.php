<?php
include_once '../inc/header.php';
include_once '../inc/nav.php';
require_once '../inc/auth.php';

require_admin();

include_once '../inc/db.php';
?>

<div class="content-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
        <div>
            <h2 class="h4 mb-0">사용자 관리</h2>
        </div>
        <div class="mt-3 mt-sm-0">
            <a href="user_register.php" class="btn btn-primary">신규 사용자 등록</a>
        </div>
    </div>

    <table class="table table-hover align-middle file-list-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>아이디</th>
                <th>권한</th>
                <th>작업</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $result = $conn->query("SELECT id, username, role FROM users ORDER BY id ASC");
            while ($row = $result->fetch_assoc()) {
                ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo htmlspecialchars($row['username']); ?></td>
                    <td><?php echo htmlspecialchars($row['role']); ?></td>
                    <td>
                        <?php if ($row['username'] !== 'admin'): // Prevent admin from deleting themselves ?>
                            <a href="user_delete.php?id=<?php echo $row['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('이 사용자를 삭제하시겠습니까?');">삭제</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>

<?php include_once '../inc/footer.php'; ?>
