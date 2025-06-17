<?php
require_once __DIR__ . '/../config/config.php'; // session_start() を含む

// セッション変数をすべて解除する
$_SESSION = array();

// セッションを切断するにはセッションクッキーも削除する。
// Note: セッション情報だけでなくセッションを破壊する。
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 最終的に、セッションを破壊する
session_destroy();

// ログインページまたはトップページへリダイレクト
header('Location: login.php?logout=success'); // ログアウト成功のメッセージを出すなら
// header('Location: index.php');
exit;
?>
