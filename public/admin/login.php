<?php
require_once __DIR__ . '/../../config/config.php'; // $twig もここで初期化

$template_vars = [
    'page_title' => get_translation('admin_login', 'page_title', '管理者ログイン'),
    'errors' => [],
    'username_value' => '',
    'csrf_token' => generate_csrf_token(),
    'active_menu' => '', // ログインページではアクティブメニューなし
    // No need to pass session_admin_id explicitly, base template handles it
];

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_vars['username_value'] = trim($_POST['username'] ?? '');
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username)) $template_vars['errors'][] = get_translation('admin_login', 'error_username_required', 'ユーザー名を入力してください。');
        if (empty($password)) $template_vars['errors'][] = get_translation('admin_login', 'error_password_required', 'パスワードを入力してください。');

        if (empty($template_vars['errors'])) {
            $conn = null;
            try {
                $conn = get_db_connection();
                $stmt = $conn->prepare("SELECT id, username, password_hash, is_active FROM admins WHERE username = ?");
                if (!$stmt) throw new Exception(get_translation('admin_login', 'error_db_prepare_failed', 'データベースエラー(準備)')); // More specific error key
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($admin = $result->fetch_assoc()) {
                    if ($admin['is_active'] && password_verify($password, $admin['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];
                        header('Location: dashboard.php');
                        exit;
                    }
                }
                $template_vars['errors'][] = get_translation('admin_login', 'error_auth_failed', 'ユーザー名またはパスワードが正しくありません。');
                error_log("Admin login failed: Username {$username}");
                if ($stmt) $stmt->close(); // Ensure statement is closed if it was prepared
            } catch (Exception $e) {
                $template_vars['errors'][] = get_translation('admin_login', 'error_login_exception', 'ログイン処理中にエラーが発生しました。');
                error_log("Admin Login Exception: Username {$username} - " . $e->getMessage());
            } finally {
                if ($conn) $conn->close();
            }
        }
    }
    if (!empty($template_vars['errors'])) { // Regenerate token if there were errors
       $template_vars['csrf_token'] = generate_csrf_token();
    }
}

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('admin/login.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for admin/login.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured for admin login or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
