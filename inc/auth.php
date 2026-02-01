<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: /user/login.php');
        exit();
    }
}

function require_admin() {
    if (!is_admin()) {
        http_response_code(403);
        die('Forbidden: You do not have permission to access this page.');
    }
}
?>