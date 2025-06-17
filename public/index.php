<?php
require_once __DIR__ . '/../config/config.php';

// 検索結果を格納する変数
$available_rooms = [];
$search_error = '';
$search_params = [
    'check_in_date' => '',
    'check_out_date' => '',
    'num_adults' => 1,
];

// 検索が実行された場合
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_rooms') {
    $search_params['check_in_date'] = trim($_POST['check_in_date'] ?? '');
    $search_params['check_out_date'] = trim($_POST['check_out_date'] ?? '');
    $search_params['num_adults'] = (int)($_POST['num_adults'] ?? 1);

    if (empty($search_params['check_in_date']) || empty($search_params['check_out_date'])) {
        $search_error = 'チェックイン日とチェックアウト日を入力してください。';
    } elseif (strtotime($search_params['check_in_date']) >= strtotime($search_params['check_out_date'])) {
        $search_error = 'チェックアウト日はチェックイン日より後の日付を選択してください。';
    } elseif (strtotime($search_params['check_in_date']) < time() - 86400) {
        $search_error = 'チェックイン日には本日以降の日付を選択してください。';
    } elseif ($search_params['num_adults'] < 1) {
        $search_error = '大人の人数は1名以上を選択してください。';
    } else {
        $conn = null;
        try {
            $conn = get_db_connection();
            $search_result = search_available_rooms($conn, $search_params['check_in_date'], $search_params['check_out_date'], $search_params['num_adults']);
            if (isset($search_result['error'])) {
                $search_error = $search_result['error'];
            } else {
                $available_rooms = $search_result;
            }
        } catch (Exception $e) {
            $search_error = 'データベース接続または検索中にエラーが発生しました。詳細はログを確認してください。';
            error_log('Search Error: ' . $e->getMessage());
        } finally {
            if ($conn) {
                $conn->close();
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>空室検索 - ホテル予約システム</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="./">ホーム</a>
        </nav>
    </header>

    <main>
        <h2>空室検索</h2>
        <form method="POST" action="index.php">
            <input type="hidden" name="action" value="search_rooms">
            <div>
                <label for="check_in_date">チェックイン日:</label>
                <input type="date" id="check_in_date" name="check_in_date" value="<?= h($search_params['check_in_date']) ?>" required>
            </div>
            <div>
                <label for="check_out_date">チェックアウト日:</label>
                <input type="date" id="check_out_date" name="check_out_date" value="<?= h($search_params['check_out_date']) ?>" required>
            </div>
            <div>
                <label for="num_adults">大人の人数:</label>
                <input type="number" id="num_adults" name="num_adults" value="<?= h($search_params['num_adults']) ?>" min="1" required>
            </div>
            <div>
                <button type="submit">検索</button>
            </div>
        </form>

        <?php if (!empty($search_error)): ?>
            <p style="color: red;"><?= h($search_error) ?></p>
        <?php endif; ?>

        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($search_error) && isset($_POST['action']) && $_POST['action'] === 'search_rooms'): ?>
            <h3>検索結果</h3>
            <?php if (!empty($available_rooms)): ?>
                <ul>
                    <?php foreach ($available_rooms as $room): ?>
                        <li>
                            <strong><?= h($room['name']) ?></strong> (<?= h($room['room_type_name']) ?>) - <?= h($room['capacity']) ?>名様まで - &yen;<?= h(number_format($room['price_per_night'])) ?>/泊
                            <a href="booking_form.php?room_id=<?= h($room['id']) ?>&check_in=<?=h($search_params['check_in_date'])?>&check_out=<?=h($search_params['check_out_date'])?>&adults=<?=h($search_params['num_adults'])?>">この部屋を予約する（仮）</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>該当する空室が見つかりませんでした。条件を変更してお試しください。</p>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ホテル予約システム</p>
    </footer>
</body>
</html>
