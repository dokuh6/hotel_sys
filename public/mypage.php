<?php
require_once __DIR__ . '/../config/config.php'; // session_start() を含む

// ログインチェック
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect_to=mypage.php'); // ログイン後にマイページに戻るように
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? 'ゲスト';
$user_email = $_SESSION['user_email'] ?? '';

$bookings = [];
$errors = [];
$success_message = ''; // キャンセル成功時など

// 予約キャンセル処理 (簡易)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = '無効なリクエストです。';
    } else {
        $booking_id_to_cancel = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        if ($booking_id_to_cancel) {
            $conn_cancel = null;
            try {
                $conn_cancel = get_db_connection();
                // 本人確認のため user_id も条件に加える
                $stmt_cancel = $conn_cancel->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ? AND status NOT IN ('cancelled', 'completed', 'rejected')");
                if (!$stmt_cancel) throw new Exception("キャンセルクエリ準備失敗: " . $conn_cancel->error);
                $stmt_cancel->bind_param("ii", $booking_id_to_cancel, $user_id);
                if ($stmt_cancel->execute()) {
                    if ($stmt_cancel->affected_rows > 0) {
                        $success_message = "予約 (ID: " . h($booking_id_to_cancel) . ") をキャンセルしました。";
                    } else {
                        $errors[] = "予約 (ID: " . h($booking_id_to_cancel) . ") のキャンセルに失敗したか、既に処理済み、またはキャンセルできない状態です。";
                    }
                } else {
                    throw new Exception("予約キャンセル処理失敗: " . $stmt_cancel->error);
                }
                $stmt_cancel->close();
            } catch (Exception $e) {
                $errors[] = "予約キャンセル中にエラーが発生しました。" . h($e->getMessage());
                error_log("Booking Cancel Error: UserID {$user_id}, BookingID {$booking_id_to_cancel} - " . $e->getMessage());
            } finally {
                if ($conn_cancel) $conn_cancel->close();
            }
        } else {
            $errors[] = 'キャンセル対象の予約IDが無効です。';
        }
    }
}


// ユーザーの予約履歴を取得
$conn = null;
try {
    $conn = get_db_connection();
    $stmt_bookings = $conn->prepare("
        SELECT
            b.id as booking_id, b.check_in_date, b.check_out_date, b.num_adults,
            b.total_price, b.status as booking_status, b.created_at as booking_created_at,
            r.name as room_name, rt.name as room_type_name
        FROM bookings b
        JOIN booking_rooms br ON b.id = br.booking_id
        JOIN rooms r ON br.room_id = r.id
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE b.user_id = ?
        ORDER BY b.check_in_date DESC, b.id DESC
    ");

    if (!$stmt_bookings) throw new Exception("予約履歴取得クエリ準備失敗: " . $conn->error);
    $stmt_bookings->bind_param("i", $user_id);
    $stmt_bookings->execute();
    $result = $stmt_bookings->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt_bookings->close();
} catch (Exception $e) {
    $errors[] = '予約履歴の取得中にエラーが発生しました。';
    error_log("Mypage Booking History Error: UserID {$user_id} - " . $e->getMessage());
} finally {
    if ($conn) $conn->close();
}

// 新しいCSRFトークンを生成 (キャンセルフォーム用)
$csrf_token = generate_csrf_token();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>マイページ - ホテル予約システム</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .booking-history table { width: 100%; border-collapse: collapse; margin-top: 1em; }
        .booking-history th, .booking-history td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .booking-history th { background-color: #f2f2f2; }
        .status-cancelled { color: red; text-decoration: line-through; }
        .status-confirmed { color: green; }
        .status-pending { color: orange; }
        .status-completed { color: blue; }
        .status-rejected { color: grey; }
    </style>
</head>
<body>
    <header>
        <nav>
            <a href="index.php">トップページ</a> |
            <a href="logout.php">ログアウト</a>
        </nav>
    </header>

    <main>
        <h2>マイページ</h2>
        <p>ようこそ、<?= h($user_name) ?> 様</p>

        <h3>登録情報</h3>
        <p>氏名: <?= h($user_name) ?></p>
        <p>メールアドレス: <?= h($user_email) ?></p>
        <p><a href="#">登録情報を変更する</a> (未実装)</p>
        <p><a href="#">パスワードを変更する</a> (未実装)</p>

        <hr>

        <h3>予約履歴</h3>
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
        <?php endif; ?>

        <?php if (!empty($bookings)): ?>
            <div class="booking-history">
                <table>
                    <thead>
                        <tr>
                            <th>予約番号</th>
                            <th>ホテル・部屋</th>
                            <th>チェックイン</th>
                            <th>チェックアウト</th>
                            <th>人数</th>
                            <th>合計金額</th>
                            <th>予約日</th>
                            <th>ステータス</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td><?= h($booking['booking_id']) ?></td>
                                <td><?= h($booking['room_name']) ?> (<?= h($booking['room_type_name']) ?>)</td>
                                <td><?= h($booking['check_in_date']) ?></td>
                                <td><?= h($booking['check_out_date']) ?></td>
                                <td>大人 <?= h($booking['num_adults']) ?>名</td>
                                <td>&yen;<?= h(number_format($booking['total_price'])) ?></td>
                                <td><?= h(date('Y-m-d H:i', strtotime($booking['booking_created_at']))) ?></td>
                                <td class="status-<?= strtolower(h($booking['booking_status'])) ?>">
                                    <?= h(ucfirst($booking['booking_status'])) ?>
                                </td>
                                <td>
                                    <?php if (!in_array($booking['booking_status'], ['cancelled', 'completed', 'rejected'])): ?>
                                    <form method="POST" action="mypage.php" style="display:inline;" onsubmit="return confirm('本当にこの予約をキャンセルしますか？');">
                                        <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                        <input type="hidden" name="action" value="cancel_booking">
                                        <input type="hidden" name="booking_id" value="<?= h($booking['booking_id']) ?>">
                                        <button type="submit">キャンセル</button>
                                    </form>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p>現在、予約履歴はありません。</p>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ホテル予約システム</p>
    </footer>
</body>
</html>
