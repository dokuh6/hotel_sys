<?php
require_once __DIR__ . '/../config/config.php'; // 設定ファイル読み込み

$errors = [];
$success_message = '';

// CSRFトークン生成
$csrf_token = generate_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = '無効なリクエストです。ページを再読み込みして再度お試しください。';
    } else {
        // フォームデータの取得
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        $agree_terms = isset($_POST['agree_terms']);

        // バリデーション
        if (empty($name)) {
            $errors[] = '氏名は必須です。';
        }
        if (empty($email)) {
            $errors[] = 'メールアドレスは必須です。';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = '有効なメールアドレスを入力してください。';
        }
        if (empty($password)) {
            $errors[] = 'パスワードは必須です。';
        } elseif (strlen($password) < 8) { // 簡単なパスワード強度チェック
            $errors[] = 'パスワードは8文字以上で入力してください。';
        }
        if ($password !== $password_confirm) {
            $errors[] = 'パスワードと確認用パスワードが一致しません。';
        }
        if (!$agree_terms) {
            $errors[] = '利用規約への同意が必要です。';
        }

        // メールアドレスの重複チェックとDB保存
        if (empty($errors)) {
            $conn = null;
            try {
                $conn = get_db_connection();

                // メールアドレスの重複チェック
                $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                if (!$stmt_check) throw new Exception("メール重複チェッククエリ準備失敗: " . $conn->error);
                $stmt_check->bind_param("s", $email);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows > 0) {
                    $errors[] = 'このメールアドレスは既に使用されています。';
                }
                $stmt_check->close();

                if (empty($errors)) {
                    // パスワードのハッシュ化
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    if (!$password_hash) {
                        throw new Exception("パスワードのハッシュ化に失敗しました。");
                    }

                    // users テーブルに保存
                    $stmt_insert = $conn->prepare("
                        INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at)
                        VALUES (?, ?, ?, TRUE, NOW(), NOW())
                    ");
                    // is_active はデフォルトTRUEだが明示。email_verified_at はNULLのまま
                    if (!$stmt_insert) throw new Exception("ユーザー情報保存クエリ準備失敗: " . $conn->error);
                    $stmt_insert->bind_param("sss", $name, $email, $password_hash);

                    if ($stmt_insert->execute()) {
                        $new_user_id = $conn->insert_id;
                        $success_message = '会員登録が完了しました。ログインページからログインしてください。';
                        // TODO: 登録完了メールの送信 (オプション)
                        // TODO: 自動ログイン処理 (オプション)
                        // header('Location: login.php'); // ログインページへリダイレクト
                        // exit;
                    } else {
                        throw new Exception("ユーザー情報の保存に失敗しました。" . $stmt_insert->error);
                    }
                    $stmt_insert->close();
                }
            } catch (Exception $e) {
                $errors[] = '登録処理中にエラーが発生しました。しばらくしてから再度お試しください。';
                error_log("User Registration Error: Email {$email} - " . $e->getMessage());
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
    <title>会員登録 - ホテル予約システム</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="index.php">トップページ</a> |
            <a href="login.php">ログイン</a>
        </nav>
    </header>

    <main>
        <h2>会員登録</h2>

        <?php if (!empty($errors)): ?>
            <div style="color: red;">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <p style="color: green;"><?= h($success_message) ?></p>
            <p><a href="login.php">ログインページへ</a></p>
        <?php else: ?>
            <form method="POST" action="register.php">
                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                <div>
                    <label for="name">氏名 <span style="color:red;">*</span>:</label>
                    <input type="text" id="name" name="name" value="<?= h($_POST['name'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="email">メールアドレス <span style="color:red;">*</span>:</label>
                    <input type="email" id="email" name="email" value="<?= h($_POST['email'] ?? '') ?>" required>
                </div>
                <div>
                    <label for="password">パスワード (8文字以上) <span style="color:red;">*</span>:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div>
                    <label for="password_confirm">パスワード (確認用) <span style="color:red;">*</span>:</label>
                    <input type="password" id="password_confirm" name="password_confirm" required>
                </div>
                <div>
                    <input type="checkbox" id="agree_terms" name="agree_terms" value="1">
                    <label for="agree_terms">利用規約に同意する <span style="color:red;">*</span></label>
                    <p><small>(利用規約のページへのリンクをここに設置)</small></p>
                </div>
                <div>
                    <button type="submit">登録する</button>
                </div>
            </form>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ホテル予約システム</p>
    </footer>
</body>
</html>
