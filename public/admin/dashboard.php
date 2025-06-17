<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php'; // 認証チェック

// ダッシュボード用のデータを取得 (例)
$conn = null;
$total_bookings = 0;
$total_rooms = 0;
$total_users = 0;

try {
    $conn = get_db_connection();

    // 総予約数 (キャンセル以外)
    $result_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status != 'cancelled'");
    if ($result_bookings) $total_bookings = $result_bookings->fetch_assoc()['count'];

    // 総部屋数
    $result_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE is_active = TRUE");
    if ($result_rooms) $total_rooms = $result_rooms->fetch_assoc()['count'];

    // 総ユーザー数 (会員)
    $result_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE");
    if ($result_users) $total_users = $result_users->fetch_assoc()['count'];

} catch (Exception $e) {
    error_log("Admin Dashboard Data Error: " . $e->getMessage());
    // エラーメッセージをダッシュボードに表示することも検討
} finally {
    if ($conn) $conn->close();
}

$page_title = "ダッシュボード";
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
                <li><a href="dashboard.php" class="active">ダッシュボード</a></li>
                <li><a href="bookings_list.php">予約管理</a></li>
                <li><a href="rooms_list.php">部屋管理</a></li>
                <li><a href="room_types_list.php">部屋タイプ管理</a></li>
                <li><a href="users_list.php">顧客管理</a></li>
                <li><a href="admin_users_list.php">管理者管理</a></li>
                <li><a href="settings.php">サイト設定</a></li>
            </ul>
        </aside>

        <main class="admin-main-content">
            <h2><?= h($page_title) ?></h2>
            <p>管理画面へようこそ。左のメニューから各管理機能をご利用ください。</p>

            <div class="widget-container" style="display: flex; gap: 20px; flex-wrap: wrap;">
                 <div class="widget" style="flex-basis: 200px;">
                    <h3>総予約数</h3>
                    <p style="font-size: 2rem; margin:0;"><?= h($total_bookings) ?></p>
                    <small>(キャンセルを除く)</small>
                </div>
                 <div class="widget" style="flex-basis: 200px;">
                    <h3>総部屋数</h3>
                    <p style="font-size: 2rem; margin:0;"><?= h($total_rooms) ?></p>
                    <small>(アクティブな部屋)</small>
                </div>
                <div class="widget" style="flex-basis: 200px;">
                    <h3>総顧客数</h3>
                    <p style="font-size: 2rem; margin:0;"><?= h($total_users) ?></p>
                    <small>(アクティブな会員)</small>
                </div>
            </div>

            <div class="widget">
                <h3>最近の予約 (上位5件)</h3>
                <?php
                $recent_bookings = [];
                $conn_recent = null;
                try {
                    $conn_recent = get_db_connection();
                    $stmt_recent = $conn_recent->query("
                        SELECT b.id, b.guest_name, b.check_in_date, b.total_price, b.status
                        FROM bookings b
                        ORDER BY b.created_at DESC LIMIT 5
                    ");
                    if ($stmt_recent) {
                        while($row = $stmt_recent->fetch_assoc()) {
                            $recent_bookings[] = $row;
                        }
                        $stmt_recent->close(); // Close the statement
                    }
                } catch (Exception $e) { error_log("Recent bookings fetch error: ". $e->getMessage()); }
                if ($conn_recent) $conn_recent->close();

                if (!empty($recent_bookings)):
                ?>
                <table class="admin-table">
                    <thead><tr><th>ID</th><th>ゲスト名</th><th>チェックイン</th><th>合計金額</th><th>ステータス</th></tr></thead>
                    <tbody>
                    <?php foreach($recent_bookings as $rb): ?>
                        <tr>
                            <td><?=h($rb['id'])?></td>
                            <td><?=h($rb['guest_name'])?></td>
                            <td><?=h($rb['check_in_date'])?></td>
                            <td>&yen;<?=h(number_format($rb['total_price']))?></td>
                            <td><?=h($rb['status'])?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>最近の予約はありません。</p>
                <?php endif; ?>
            </div>

        </main>
    </div>
</body>
</html>
