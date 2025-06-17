<?php
// データベース接続情報
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'hotel_booking_system');

// サイト設定
define('SITE_URL', 'http://localhost/hotel_booking_system'); // 例: 環境に合わせて変更してください
define('DEBUG_MODE', true); // 開発中はtrue、本番環境ではfalse

// メール設定 (PHPMailer)
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_USER', 'user@example.com');
define('SMTP_PASS', 'password');
define('SMTP_PORT', 587);
define('MAIL_FROM', 'noreply@example.com');
define('MAIL_FROM_NAME', 'Hotel Booking System');

// Twig 設定
define('TWIG_TEMPLATE_DIR', __DIR__ . '/../templates');
define('TWIG_CACHE_DIR', __DIR__ . '/../cache/twig'); // 必要に応じて作成

// セッション設定
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// エラーハンドリング
if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    // 本番環境ではエラーログをファイルに記録するなどの設定を推奨
}

// 文字コード設定
mb_internal_encoding('UTF-8');

// 共通関数の読み込み
require_once __DIR__ . '/../src/functions.php';

// CSRFトークン生成・検証用 (後ほど実装)
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// HTMLエスケープ関数 h()
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
EOL
