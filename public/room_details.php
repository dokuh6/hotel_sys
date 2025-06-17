<?php
require_once __DIR__ . '/../config/config.php';

$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$room = null;
$error_message = '';

// 検索パラメータも引き継ぐ (予約フォームへの遷移用)
$check_in_date = filter_input(INPUT_GET, 'check_in', FILTER_SANITIZE_STRING);
$check_out_date = filter_input(INPUT_GET, 'check_out', FILTER_SANITIZE_STRING);
$num_adults = filter_input(INPUT_GET, 'adults', FILTER_VALIDATE_INT);


if (!$room_id) {
    $error_message = '部屋IDが無効です。';
} else {
    $conn = null;
    try {
        $conn = get_db_connection();
        // roomsテーブルとroom_typesテーブルをJOINして部屋情報を取得
        $stmt = $conn->prepare("
            SELECT r.*, rt.name as room_type_name, rt.description as room_type_description
            FROM rooms r
            JOIN room_types rt ON r.room_type_id = rt.id
            WHERE r.id = ? AND r.is_active = TRUE
        ");
        if (!$stmt) {
            throw new Exception("SQL準備エラー: " . $conn->error);
        }
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $room = $result->fetch_assoc();
        } else {
            $error_message = '指定された部屋が見つからないか、現在利用できません。';
        }
        $stmt->close();
    } catch (Exception $e) {
        $error_message = '部屋情報の取得中にエラーが発生しました。';
        error_log("Room Details Error: ID {$room_id} - " . $e->getMessage());
    } finally {
        if ($conn) {
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $room ? h($room['name']) : '部屋詳細'; ?> - ホテル予約システム</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="index.php">空室検索に戻る</a>
        </nav>
    </header>

    <main>
        <?php if ($error_message): ?>
            <p style="color: red;"><?= h($error_message) ?></p>
        <?php elseif ($room): ?>
            <h1><?= h($room['name']) ?></h1>
            <p><strong>部屋タイプ:</strong> <?= h($room['room_type_name']) ?></p>
            <?php if (!empty($room['image_path'])): ?>
                <img src="<?= h($room['image_path']) ?>" alt="<?= h($room['name']) ?>" style="max-width: 400px; height: auto;">
            <?php else: ?>
                <img src="images/placeholder_room.png" alt="部屋の画像はありません" style="max-width: 400px; height: auto; border: 1px solid #ccc;">
                 <p>(画像プレースホルダー)</p>
            <?php endif; ?>

            <h2>詳細</h2>
            <p><?= nl2br(h($room['description'] ?? '詳細情報はありません。')) ?></p>
            <p><strong>定員:</strong> <?= h($room['capacity']) ?> 名様</p>
            <p><strong>1泊料金:</strong> &yen;<?= h(number_format($room['price_per_night'])) ?></p>

            <h2>設備など</h2>
            <p><?= nl2br(h($room['room_type_description'] ?? '設備情報はありません。')) ?></p>

            <hr>
            <h3>この部屋を予約する</h3>
            <form action="booking_form.php" method="GET">
                <input type="hidden" name="room_id" value="<?= h($room['id']) ?>">
                <div>
                    <label for="detail_check_in">チェックイン日:</label>
                    <input type="date" id="detail_check_in" name="check_in" value="<?= h($check_in_date) ?>" required>
                </div>
                <div>
                    <label for="detail_check_out">チェックアウト日:</label>
                    <input type="date" id="detail_check_out" name="check_out" value="<?= h($check_out_date) ?>" required>
                </div>
                <div>
                    <label for="detail_adults">大人の人数:</label>
                    <input type="number" id="detail_adults" name="adults" value="<?= h($num_adults) ?>" min="1" max="<?=h($room['capacity'])?>" required>
                </div>
                <button type="submit">予約手続きへ進む</button>
            </form>
            <p><a href="index.php?check_in_date=<?=h($check_in_date)?>&check_out_date=<?=h($check_out_date)?>&num_adults=<?=h($num_adults)?>&action=search_rooms">他の部屋も見る（検索結果に戻る）</a></p>

        <?php else: ?>
            <p>部屋情報が見つかりませんでした。</p>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ホテル予約システム</p>
    </footer>
</body>
</html>
