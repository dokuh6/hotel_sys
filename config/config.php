<?php
// Composer Autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // Fallback or error if Twig won't be available
    // error_log("vendor/autoload.php not found. Twig might not be available.");
}

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

// Global Exception and Error Handling (Basic)
// これより前に DEBUG_MODE が定義されていること
// これより前に SITE_URL が定義されていること (500ページへのリダイレクト用)
// これより前に get_translation が使えるように functions.php が読み込まれていること
// これより前に Twig ($twig) が初期化されていること (500ページレンダリング用)

// function custom_exception_handler($exception) {
//     error_log("Uncaught Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine() . "\nStack trace: " . $exception->getTraceAsString());
//     if (!headers_sent()) {
//         // http_response_code(500); // 500.php側で設定
//         // 致命的なエラーなので、Twigが使えるかどうかも怪しい場合がある
//         // $_SESSION['last_error_for_500'] = ['message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()];
//         // header('Location: ' . rtrim(SITE_URL ?? '', '/') . '/public/500.php'); // SITE_URLの定義に依存
//         // exit;
//         // 代わりに、直接500ページの内容を出力するか、シンプルなエラー表示
//         if (DEBUG_MODE) {
//             echo "<h1>Unhandled Exception</h1><pre>" . htmlspecialchars($exception->__toString()) . "</pre>";
//         } else {
//             echo "<h1>Internal Server Error</h1><p>An unexpected error occurred. Please try again later.</p>";
//         }
//         exit;
//     }
// }
// set_exception_handler('custom_exception_handler');

// function custom_error_handler($errno, $errstr, $errfile, $errline) {
//     if (!(error_reporting() & $errno)) { // error_reportingディレクティブを尊重
//         return false;
//     }
//     $log_message = "Error [$errno]: $errstr in $errfile:$errline";
//     error_log($log_message);
//     // E_USER_ERROR などの致命的なエラーの場合、ここで処理を中断することもできる
//     // set_exception_handler が Throwable をキャッチするので、PHP7以降はErrorもそこで捕捉される。
//     // PHP5 の E_ERROR などは shutdown function で捕捉する必要がある場合も。
//     return true; // PHP標準エラーハンドラを実行させない
// }
// set_error_handler('custom_error_handler');

// function custom_shutdown_handler() {
//     $last_error = error_get_last();
//     if ($last_error && in_array($last_error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR, E_PARSE])) {
//         error_log("Fatal Error (Shutdown): " . $last_error['message'] . " in " . $last_error['file'] . ":" . $last_error['line']);
//         if (!headers_sent() && !DEBUG_MODE) { // 本番モードでヘッダー未送信なら500ページへ
//             // http_response_code(500); // 500.php側で設定
//             // $_SESSION['last_error_for_500'] = $last_error;
//             // header('Location: ' . rtrim(SITE_URL ?? '', '/') . '/public/500.php');
//             // exit;
//         }
//         // DEBUG_MODEなら詳細表示、本番なら汎用メッセージ表示 (custom_exception_handlerと同様)
//     }
// }
// register_shutdown_function('custom_shutdown_handler');

// 文字コード設定
mb_internal_encoding('UTF-8');

// 共通関数の読み込み
require_once __DIR__ . '/../src/functions.php';

// Twig テンプレートエンジンの設定
// vendor/autoload.php はこのファイルの上部で読み込まれている前提
if (class_exists('\\Twig\\Loader\\FilesystemLoader')) {
    try {
        $loader = new \\Twig\\Loader\\FilesystemLoader(TWIG_TEMPLATE_DIR);
        $twig_options = [
            'cache' => DEBUG_MODE ? false : TWIG_CACHE_DIR, // デバッグモード時はキャッシュ無効
            'debug' => DEBUG_MODE, // デバッグモード有効
            'auto_reload' => DEBUG_MODE, // デバッグモード時はテンプレート変更を自動リロード
        ];
        $twig = new \\Twig\\Environment($loader, $twig_options);

        if (DEBUG_MODE) {
            $twig->addExtension(new \\Twig\\Extension\\DebugExtension());
        }

        // グローバル変数の設定 (例: SITE_URL、現在の言語、セッション情報など)
        $twig->addGlobal('site_url', SITE_URL);
        $twig->addGlobal('current_language', get_current_language()); // functions.phpの関数
        $twig->addGlobal('session_user_id', $_SESSION['user_id'] ?? null);
        $twig->addGlobal('session_user_name', $_SESSION['user_name'] ?? null);
        $twig->addGlobal('session_admin_id', $_SESSION['admin_id'] ?? null);
        $twig->addGlobal('session_admin_username', $_SESSION['admin_username'] ?? null);

        // カスタム関数として get_translation を登録
        $function_translate = new \\Twig\\TwigFunction('t', function ($group_key, $item_key, $default_text = '') {
            return get_translation($group_key, $item_key, $default_text);
        });
        $twig->addFunction($function_translate);

        // ページタイトルを渡すためのグローバル変数（各PHPで設定）
        $twig->addGlobal('page_title_default', 'ホテル予約システム'); // Default page title

    } catch (Exception $e) {
        // Twigの初期化に失敗した場合のフォールバック処理
        error_log('Twig Initialization Error: ' . $e->getMessage());
        // ここでサイトを停止させるか、限定的な表示をするかなど検討
        die('テンプレートエンジンの初期化に失敗しました。');
    }
} else {
    // Twigがロードできない場合（Composer依存関係が解決していないなど）
    error_log('Twig library not found. Please run composer install/update. Ensure TWIG_TEMPLATE_DIR is correct.');
    // die('テンプレートエンジンライブラリが見つかりません。');
    // Twigがない場合は以降の処理で $twig 変数が未定義になるので注意。
    // このサブタスクでは、Twigが存在する前提で進む。
    // For safety, define $twig as null if not initialized.
    if (!isset($twig)) {
        $twig = null;
    }
}

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

// Handle language change request
handle_language_change();

// HTTP Security Headers
// これらのヘッダーはWebサーバー側 (Apache/Nginx) で設定することも推奨されます。
if (!headers_sent()) { // ヘッダーがまだ送信されていなければ設定
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN'); // DENY または SAMEORIGIN (サイトの要件による)
    header('X-XSS-Protection: 1; mode=block'); // 古いブラウザ向けだが、CSPとの併用も考慮
    // header('Content-Security-Policy: default-src \'self\''); // CSPは慎重に設定が必要。最初はReport-Onlyモードから。
    // header('Strict-Transport-Security: max-age=31536000; includeSubDomains'); // HTTPS強制時。localhostでは注意。
}
EOL
