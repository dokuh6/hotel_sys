<?php
require_once __DIR__ . '/../../config/config.php'; // セッション開始

// 管理者セッション関連のキーをクリア
unset($_SESSION['admin_id']);
unset($_SESSION['admin_username']);

// 必要であれば、ユーザーセッションとは別にセッション全体を破棄する判断も可能
// session_destroy(); // これを行うとユーザーセッションも消えるので注意

// ログインページへリダイレクト
header('Location: login.php?logout=success');
exit;
?>
