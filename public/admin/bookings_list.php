<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php'; // 認証チェック

$page_title = "予約管理";
$bookings = [];
$errors = [];
$success_message = ''; // キャンセル成功時など

// フィルタリング条件 (将来的に実装)
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$search_query = $_GET['q'] ?? '';


// 予約キャンセル処理 (簡易的なもの。詳細な処理は booking_edit.php や専用処理で行うことも)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_cancel_booking') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $errors[] = '無効なリクエストです。';
    } else {
        $booking_id_to_cancel = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        if ($booking_id_to_cancel) {
            $conn_cancel = null;
            try {
                $conn_cancel = get_db_connection();
                // 管理者は user_id のチェックは不要（または権限による）
                $stmt_cancel = $conn_cancel->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status NOT IN ('cancelled', 'completed', 'rejected')");
                if (!$stmt_cancel) throw new Exception("キャンセルクエリ準備失敗: " . $conn_cancel->error);
                $stmt_cancel->bind_param("i", $booking_id_to_cancel);
                if ($stmt_cancel->execute()) {
                    if ($stmt_cancel->affected_rows > 0) {
                        $success_message = "予約 (ID: " . h($booking_id_to_cancel) . ") を管理者権限でキャンセルしました。";
                    } else {
                        $errors[] = "予約 (ID: " . h($booking_id_to_cancel) . ") のキャンセルに失敗したか、既に処理済みです。";
                    }
                } else {
                    throw new Exception("予約キャンセル処理失敗: " . $stmt_cancel->error);
                }
                $stmt_cancel->close();
            } catch (Exception $e) {
                $errors[] = "予約キャンセル中にエラーが発生しました。" . h($e->getMessage());
                error_log("Admin Booking Cancel Error: AdminID {$_SESSION['admin_id']}, BookingID {$booking_id_to_cancel} - " . $e->getMessage());
            } finally {
                if ($conn_cancel) $conn_cancel->close();
            }
        } else {
            $errors[] = 'キャンセル対象の予約IDが無効です。';
        }
    }
}


// 予約一覧を取得
$conn = null;
try {
    $conn = get_db_connection();
    // bookings を主軸に、関連情報をJOIN
    // users (会員情報), booking_rooms, rooms, room_types
    // payment_status なども表示項目として検討
    $sql = "
        SELECT
            b.id as booking_id, b.check_in_date, b.check_out_date,
            b.num_adults, b.num_children, b.total_price, b.status as booking_status,
            b.created_at as booking_created_at, b.guest_name as booking_guest_name, b.guest_email,
            u.name as user_name, u.email as user_email, -- 会員の場合
            GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') as room_names,
            GROUP_CONCAT(DISTINCT rt.name ORDER BY rt.name SEPARATOR ', ') as room_type_names,
            b.payment_status
        FROM bookings b
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN booking_rooms br ON b.id = br.booking_id
        LEFT JOIN rooms r ON br.room_id = r.id
        LEFT JOIN room_types rt ON r.room_type_id = rt.id
    ";

    // WHERE句の構築 (フィルタリング)
    $where_clauses = [];
    $params = [];
    $types = "";

    if (!empty($filter_status)) {
        $where_clauses[] = "b.status = ?";
        $params[] = $filter_status;
        $types .= "s";
    }
    if (!empty($filter_date_from)) {
        $where_clauses[] = "b.check_in_date >= ?";
        $params[] = $filter_date_from;
        $types .= "s";
    }
    if (!empty($filter_date_to)) {
        $where_clauses[] = "b.check_out_date <= ?"; // チェックアウト日が範囲内
        $params[] = $filter_date_to;
        $types .= "s";
    }
    if (!empty($search_query)) {
        $where_clauses[] = "(b.guest_name LIKE ? OR u.name LIKE ? OR b.guest_email LIKE ? OR u.email LIKE ? OR CAST(b.id AS CHAR) LIKE ?)";
        $search_like = "%" . $search_query . "%";
        for ($i=0; $i<5; $i++) {
            $params[] = $search_like;
            $types .= "s";
        }
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql .= " GROUP BY b.id ORDER BY b.check_in_date DESC, b.id DESC";
    // TODO: ページネーション LIMIT ?, ?

    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception("予約一覧取得クエリ準備失敗: " . $conn->error . " SQL: " . $sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    $stmt->close();

} catch (Exception $e) {
    $errors[] = '予約一覧の取得中にエラーが発生しました: ' . h($e->getMessage());
    error_log("Admin Bookings List Error: " . $e->getMessage());
} finally {
    if ($conn) $conn->close();
}

$csrf_token = generate_csrf_token(); // キャンセルフォーム用

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?> - 管理画面</title>
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>
    <header class="admin-header">
        <h1>ホテル予約システム 管理画面</h1>
        <nav>
            <span>ようこそ、<?= h($current_admin_username) ?> 様</span>
            <a href="logout.php">ログアウト</a>
        </nav>
    </header>

    <div class="admin-container">
        <aside class="admin-sidebar">
            <h2>メニュー</h2>
            <ul>
                <li><a href="dashboard.php">ダッシュボード</a></li>
                <li><a href="bookings_list.php" class="active">予約管理</a></li>
                <li><a href="rooms_list.php">部屋管理</a></li>
                <li><a href="room_types_list.php">部屋タイプ管理</a></li>
                <li><a href="users_list.php">顧客管理</a></li>
                <li><a href="admin_users_list.php">管理者管理</a></li>
                <li><a href="settings.php">サイト設定</a></li>
            </ul>
        </aside>

        <main class="admin-main-content">
            <h2><?= h($page_title) ?></h2>

            <?php if (!empty($errors)): ?>
            <div class="message error">
                <ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>
            <?php if ($success_message): ?>
            <div class="message success"><?= h($success_message) ?></div>
            <?php endif; ?>

            <!-- フィルタリングフォーム (将来的に) -->
            <form method="GET" action="bookings_list.php" class="admin-form" style="display:flex; gap:10px; align-items:flex-end; background-color:#fff; padding:15px; border-radius:5px; margin-bottom:20px;">
                <div>
                    <label for="q">検索:</label>
                    <input type="text" name="q" id="q" value="<?= h($search_query) ?>" placeholder="ID, 名前, メール">
                </div>
                <div>
                    <label for="status">ステータス:</label>
                    <select name="status" id="status">
                        <option value="">すべて</option>
                        <option value="pending" <?= ($filter_status === 'pending' ? 'selected' : '') ?>>Pending</option>
                        <option value="confirmed" <?= ($filter_status === 'confirmed' ? 'selected' : '') ?>>Confirmed</option>
                        <option value="cancelled" <?= ($filter_status === 'cancelled' ? 'selected' : '') ?>>Cancelled</option>
                        <option value="completed" <?= ($filter_status === 'completed' ? 'selected' : '') ?>>Completed</option>
                        <option value="rejected" <?= ($filter_status === 'rejected' ? 'selected' : '') ?>>Rejected</option>
                    </select>
                </div>
                <div>
                    <label for="date_from">チェックイン開始:</label>
                    <input type="date" name="date_from" id="date_from" value="<?= h($filter_date_from) ?>">
                </div>
                <div>
                    <label for="date_to">チェックアウト終了:</label>
                    <input type="date" name="date_to" id="date_to" value="<?= h($filter_date_to) ?>">
                </div>
                <div><button type="submit">絞り込み</button></div>
                 <div><a href="bookings_list.php" style="padding: .5rem 1rem; background-color:#6c757d; color:white; text-decoration:none; border-radius:.25rem;">リセット</a></div>
            </form>


            <?php if (!empty($bookings)): ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>顧客名/ゲスト名</th>
                        <th>部屋名 (タイプ)</th>
                        <th>IN/OUT</th>
                        <th>人数(大人/子供)</th>
                        <th>合計金額</th>
                        <th>予約日</th>
                        <th>支払状況</th>
                        <th>ステータス</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td><?= h($booking['booking_id']) ?></td>
                        <td>
                            <?php if ($booking['user_name']): ?>
                                <?= h($booking['user_name']) ?> (会員)<br><small><?= h($booking['user_email']) ?></small>
                            <?php else: ?>
                                <?= h($booking['booking_guest_name']) ?> (ゲスト)<br><small><?= h($booking['guest_email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= h($booking['room_names']) ?> <small>(<?= h($booking['room_type_names']) ?>)</small></td>
                        <td><?= h($booking['check_in_date']) ?><br>～<br><?= h($booking['check_out_date']) ?></td>
                        <td><?= h($booking['num_adults']) ?> / <?= h($booking['num_children'] ?? 0) ?></td>
                        <td>&yen;<?= h(number_format($booking['total_price'])) ?></td>
                        <td><?= h(date('Y-m-d H:i', strtotime($booking['booking_created_at']))) ?></td>
                        <td><?= h(ucfirst($booking['payment_status'])) ?></td>
                        <td class="status-<?= strtolower(h($booking['booking_status'])) ?>"><?= h(ucfirst($booking['booking_status'])) ?></td>
                        <td>
                            <a href="booking_edit.php?id=<?= h($booking['booking_id']) ?>" class="view-btn" style="background-color:#007bff;">詳細/編集</a>
                            <?php if ($booking['booking_status'] !== 'cancelled' && $booking['booking_status'] !== 'completed' && $booking['booking_status'] !== 'rejected'): ?>
                            <form method="POST" action="bookings_list.php" style="display:inline;" onsubmit="return confirm('本当にこの予約 (ID: <?=h($booking['booking_id'])?>) をキャンセルしますか？');">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                                <input type="hidden" name="action" value="admin_cancel_booking">
                                <input type="hidden" name="booking_id" value="<?= h($booking['booking_id']) ?>">
                                <button type="submit" class="delete-btn">キャンセル</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p>該当する予約情報はありません。</p>
            <?php endif; ?>

            <!-- TODO: ページネーション -->

        </main>
    </div>
</body>
</html>
