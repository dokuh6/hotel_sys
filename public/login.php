<?php
require_once __DIR__ . '/../config/config.php'; // 設定ファイル読み込み (session_start() もここに含まれる)

$errors = [];
// CSRFトークン生成 (config.phpの関数を利用)
$csrf_token = generate_csrf_token();

// 既にログインしている場合はマイページなどにリダイレクト (オプション)
// if (isset($_SESSION['user_id'])) {
//    header('Location: mypage.php');
//    exit;
// }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = '無効なリクエストです。ページを再読み込みして再度お試しください。';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // バリデーション
        if (empty($email)) {
            $errors[] = 'メールアドレスを入力してください。';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '有効なメールアドレスを入力してください。';
        }
        if (empty($password)) {
            $errors[] = 'パスワードを入力してください。';
        }

        if (empty($errors)) {
            $conn = null;
            try {
                $conn = get_db_connection();
                $stmt = $conn->prepare("SELECT id, name, email, password_hash, is_active FROM users WHERE email = ?");
                if (!$stmt) throw new Exception("ログインクエリ準備失敗: " . $conn->error);
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if ($user['is_active'] && password_verify($password, $user['password_hash'])) {
                        // 認証成功
                        session_regenerate_id(true); // セッション固定化対策
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['name'];
                        $_SESSION['user_email'] = $user['email'];

                        // ログイン後のリダイレクト先 (例: マイページまたはホームページ)
                        // header('Location: mypage.php');
                        header('Location: index.php'); // 今回はトップページへ
                        exit;
                    } else {
                        // アカウント非アクティブまたはパスワード不一致
                        $errors[] = 'メールアドレスまたはパスワードが正しくありません。';
                        error_log("Login failed (inactive or wrong pass): Email {$email}");
                    }
                } else {
                    // ユーザーが存在しない
                    $errors[] = 'メールアドレスまたはパスワードが正しくありません。';
                    error_log("Login failed (user not found): Email {$email}");
                }
                $stmt->close();
            } catch (Exception $e) {
                $errors[] = 'ログイン処理中にエラーが発生しました。';
                error_log("Login Exception: Email {$email} - " . $e->getMessage());
            } finally {
                if ($conn) $conn->close();
            }
        }
    }
    // エラーで再表示の場合、新しいCSRFトークンを生成
    $csrf_token = generate_csrf_token();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ログイン - ホテル予約システム</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="index.php">トップページ</a> |
            <a href="register.php">会員登録</a>
        </nav>
    </header>

    <main>
        <h2>ログイン</h2>

        <?php if (!empty($errors)): ?>
            <div style="color: red;">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <div>
                <label for="email">メールアドレス:</label>
                <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>
            <div>
                <label for="password">パスワード:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <button type="submit">ログイン</button>
            </div>
            <p><a href="#">パスワードをお忘れですか？</a> (未実装)</p>
        </form>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ホテル予約システム</p>
    </footer>
</body>
</html>
