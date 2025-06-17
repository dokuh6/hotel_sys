<?php
require_once __DIR__ . '/../config/config.php';

$template_vars = [
    'page_title' => get_translation('login', 'page_title', 'ログイン - ホテル予約システム'),
    'errors' => [],
    'email_value' => '', // POSTされた値を保持するため
    'csrf_token' => generate_csrf_token(),
];

if (isset($_SESSION['user_id'])) {
    header('Location: mypage.php'); // or index.php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_vars['email_value'] = trim($_POST['email'] ?? ''); // POSTされたemailを保持
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email)) {
            $template_vars['errors'][] = get_translation('login', 'error_email_required', 'メールアドレスを入力してください。');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $template_vars['errors'][] = get_translation('login', 'error_email_invalid', '有効なメールアドレスを入力してください。');
        }
        if (empty($password)) {
            $template_vars['errors'][] = get_translation('login', 'error_password_required', 'パスワードを入力してください。');
        }

        if (empty($template_vars['errors'])) {
            $conn = null;
            try {
                $conn = get_db_connection();
                $stmt = $conn->prepare("SELECT id, name, email, password_hash, is_active FROM users WHERE email = ?");
                if (!$stmt) throw new Exception(get_translation('login', 'error_db_prepare_failed', 'データベースエラーが発生しました。'));
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if ($user['is_active'] && password_verify($password, $user['password_hash'])) {
                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];
                        header('Location: index.php'); // Redirect to a logged-in area or index
                        exit;
                    } else {
                        $template_vars['errors'][] = get_translation('login', 'error_auth_failed', 'メールアドレスまたはパスワードが正しくありません。');
                        error_log("Login failed (inactive or wrong pass): Email {$email}");
                    }
                } else {
                    $template_vars['errors'][] = get_translation('login', 'error_auth_failed', 'メールアドレスまたはパスワードが正しくありません。');
                    error_log("Login failed (user not found): Email {$email}");
                }
                $stmt->close();
            } catch (Exception $e) {
                $template_vars['errors'][] = get_translation('login', 'error_login_exception', 'ログイン処理中にエラーが発生しました。');
                error_log("Login Exception: Email {$email} - " . $e->getMessage());
            } finally {
                if ($conn) $conn->close();
            }
        }
    }
    // エラー時はCSRFトークンを再生成してフォームに渡す
    if (!empty($template_vars['errors'])) { // Check if errors exist before regenerating token
        $template_vars['csrf_token'] = generate_csrf_token();
    }
}

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('login.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for login.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
