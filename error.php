<?php
// 공통 에러 페이지
// ?code=403, 404 등으로 에러 코드를 전달할 수 있습니다.

$code = isset($_GET['code']) ? (int)$_GET['code'] : 0;

switch ($code) {
    case 403:
        $title = '접근 권한이 없습니다.';
        $message = '이 파일에 접근할 수 있는 권한이 없습니다.';
        break;
    case 404:
        $title = '파일을 찾을 수 없습니다.';
        $message = '요청하신 파일을 찾을 수 없습니다.';
        break;
    default:
        $title = '에러가 발생했습니다.';
        $message = '요청을 처리하는 중 문제가 발생했습니다.';
        break;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }
        .error-container {
            background-color: #ffffff;
            padding: 24px 32px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
        }
        .error-code {
            font-size: 18px;
            color: #888;
            margin-bottom: 8px;
        }
        .error-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 12px;
        }
        .error-message {
            font-size: 14px;
            color: #555;
            margin-bottom: 20px;
        }
        a.button {
            display: inline-block;
            padding: 8px 16px;
            background-color: #007bff;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        a.button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<div class="error-container">
    <div class="error-code">
        <?php echo $code ? 'ERROR ' . htmlspecialchars((string)$code, ENT_QUOTES, 'UTF-8') : 'ERROR'; ?>
    </div>
    <div class="error-title">
        <?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <div class="error-message">
        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <a href="index.php" class="button">홈으로 이동</a>
</div>
</body>
</html>


