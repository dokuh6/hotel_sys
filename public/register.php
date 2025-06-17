<?php
require_once __DIR__ . '/../config/config.php';

$template_vars = [
    'page_title' => get_translation('register', 'page_title', '会員登録 - ホテル予約システム'),
    'errors' => [],
    'success_message' => '',
    'form_values' => [ // POSTされた値を保持
        'name' => '',
        'email' => '',
    ],
    'csrf_token' => generate_csrf_token(),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $template_vars['form_values']['name'] = trim($_POST['name'] ?? '');
    $template_vars['form_values']['email'] = trim($_POST['email'] ?? '');

    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $agree_terms = isset($_POST['agree_terms']);

        if (empty($name)) {
            $template_vars['errors'][] = get_translation('register', 'error_name_required', '氏名は必須です。');
        }
        if (empty($email)) {
            $template_vars['errors'][] = get_translation('register', 'error_email_required', 'メールアドレスは必須です。');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $template_vars['errors'][] = get_translation('register', 'error_email_invalid', '有効なメールアドレスを入力してください。');
        }
        if (empty($password)) {
            $template_vars['errors'][] = get_translation('register', 'error_password_required', 'パスワードは必須です。');
        } elseif (strlen($password) < 8) {
            $template_vars['errors'][] = get_translation('register', 'error_password_minlength_8', 'パスワードは8文字以上で入力してください。');
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $template_vars['errors'][] = get_translation('register', 'error_password_uppercase_required', 'パスワードには少なくとも1つの大文字を含めてください。');
        } elseif (!preg_match('/[a-z]/', $password)) {
            $template_vars['errors'][] = get_translation('register', 'error_password_lowercase_required', 'パスワードには少なくとも1つの小文字を含めてください。');
        } elseif (!preg_match('/[0-9]/', $password)) {
            $template_vars['errors'][] = get_translation('register', 'error_password_digit_required', 'パスワードには少なくとも1つの数字を含めてください。');
        }
        // オプション: 記号の要求
        // elseif (!preg_match('/[\W_]/', $password)) { // \W は非単語構成文字 (記号やスペースなど) _ は単語構成文字なので追加
        //    $template_vars['errors'][] = get_translation('register', 'error_password_symbol_required', 'パスワードには少なくとも1つの記号を含めてください。');
        // }

        if ($password !== $password_confirm) {
            $template_vars['errors'][] = get_translation('register', 'error_password_mismatch', 'パスワードと確認用パスワードが一致しません。');
        }
        if (!$agree_terms) {
            $template_vars['errors'][] = get_translation('register', 'error_agree_terms_required', '利用規約への同意が必要です。');
        }

        if (empty($template_vars['errors'])) {
            $conn = null;
            try {
                $conn = get_db_connection();
                $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if (!$stmt_check) throw new Exception(get_translation('register', 'error_db_prepare_failed', 'データベースエラーが発生しました。'));
                $stmt_check->bind_param("s", $email);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows > 0) {
                    $template_vars['errors'][] = get_translation('register', 'error_email_exists', 'このメールアドレスは既に使用されています。');
                }
                $stmt_check->close();

                if (empty($template_vars['errors'])) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    if (!$password_hash) throw new Exception(get_translation('register', 'error_password_hash_failed', 'パスワードの処理に失敗しました。'));

                    $stmt_insert = $conn->prepare("INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, TRUE, NOW(), NOW())");
                    if (!$stmt_insert) throw new Exception(get_translation('register', 'error_db_insert_failed', 'データベースエラーが発生しました。'));
                    $stmt_insert->bind_param("sss", $name, $email, $password_hash);

                    if ($stmt_insert->execute()) {
                        $template_vars['success_message'] = get_translation('register', 'success_registration', '会員登録が完了しました。ログインページからログインしてください。');
                        // Clear form values on success
                        $template_vars['form_values']['name'] = '';
                        $template_vars['form_values']['email'] = '';
                    } else {
                        throw new Exception(get_translation('register', 'error_db_insert_failed_detail', 'ユーザー情報の保存に失敗しました。'));
                    }
                    $stmt_insert->close();
                }
            } catch (Exception $e) {
                 // Check for duplicate entry SQL error (errno 1062)
                if ($conn && $conn->errno === 1062) {
                     $template_vars['errors'][] = get_translation('register', 'error_email_exists', 'このメールアドレスは既に使用されています。');
                } else {
                    $template_vars['errors'][] = get_translation('register', 'error_registration_exception', '登録処理中にエラーが発生しました。');
                }
                error_log("User Registration Error: Email {$email} - " . $e->getMessage());
            } finally {
                if ($conn) $conn->close();
            }
        }
    }
    if (!empty($template_vars['errors']) || !empty($template_vars['success_message'])) { // Regenerate token if errors or success (to prevent re-submission after success)
        $template_vars['csrf_token'] = generate_csrf_token();
    }
}

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('register.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for register.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
