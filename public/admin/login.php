<?php
// 管理者ページでは、通常ユーザーのセッションとは別に管理するか、
// セッション変数で権限を明確に区別する必要がある。
// ここでは、config.php を読み込み、セッションを開始する。
// 管理者専用のセッションキー（例: $_SESSION['admin_id']）を使用する。
require_once __DIR__ . '/../../config/config.php';

$errors = [];
$csrf_token = generate_csrf_token(); // config.phpの関数

// 既に管理者としてログインしている場合はダッシュボードへ
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = '無効なリクエストです。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username)) {
            $errors[] = 'ユーザー名を入力してください。';
        }
        if (empty($password)) {
            $errors[] = 'パスワードを入力してください。';
        }

        if (empty($errors)) {
            $conn = null;
            try {
                $conn = get_db_connection();
                // admins テーブルからユーザー名で検索
                $stmt = $conn->prepare("SELECT id, username, password_hash, is_active FROM admins WHERE username = ?");
                if (!$stmt) throw new Exception("管理者ログインクエリ準備失敗: " . $conn->error);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $admin = $result->fetch_assoc();
                    if ($admin['is_active'] && password_verify($password, $admin['password_hash'])) {
                        // 認証成功
                        session_regenerate_id(true); // セッション固定化対策
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_username'] = $admin['username'];

                        header('Location: dashboard.php'); // 管理者ダッシュボードへ
                        exit;
                    } else {
                        $errors[] = 'ユーザー名またはパスワードが正しくありません。';
                        error_log("Admin login failed (inactive or wrong pass): Username {$username}");
                    }
                } else {
                    $errors[] = 'ユーザー名またはパスワードが正しくありません。';
                    error_log("Admin login failed (user not found): Username {$username}");
                }
                $stmt->close();
            } catch (Exception $e) {
                $errors[] = 'ログイン処理中にエラーが発生しました。';
                error_log("Admin Login Exception: Username {$username} - " . $e->getMessage());
            } finally {
                if ($conn) $conn->close();
            }
        }
    }
    $csrf_token = generate_csrf_token(); // エラー時再生成
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理者ログイン - ホテル予約システム</title>
    <link rel="stylesheet" href="../css/style.css"> {/* CSSパスを調整 */}
    <style>
        body { display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: #f0f0f0; }
        .login-container { background-color: #fff; padding: 2em; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 320px; }
        .login-container h2 { text-align: center; margin-bottom: 1em; }
        .login-container div { margin-bottom: 1em; }
        .login-container label { display: block; margin-bottom: 0.5em; }
        .login-container input[type="text"],
        .login-container input[type="password"] { width: 100%; padding: 0.8em; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .login-container button { width: 100%; padding: 0.8em; background-color: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .login-container button:hover { background-color: #0056b3; }
        .errors { color: red; margin-bottom: 1em; padding: 0.5em; border: 1px solid red; border-radius: 4px; background-color: #ffebeb; list-style-type: none;}
        .errors li { margin:0; padding:0; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>管理者ログイン</h2>

        <?php if (!empty($errors)): ?>
            <ul class="errors">
                <?php foreach ($errors as $error): ?>
                    <li><?= h($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <div>
                <label for="username">ユーザー名:</label>
                <input type="text" id="username" name="username" value="<?= h($_POST['username'] ?? '') ?>" required autofocus>
            </div>
            <div>
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">ログイン</button>
            </div>
        </form>
    </div>
</body>
</html>
