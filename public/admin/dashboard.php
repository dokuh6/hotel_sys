<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php'; // Includes session_admin_username

$template_vars = [
    'page_title' => get_translation('admin_dashboard', 'page_title', 'ダッシュボード'),
    'active_menu' => 'dashboard', // For highlighting active menu in sidebar
    'total_bookings' => 0,
    'total_rooms' => 0,
    'total_users' => 0,
    'recent_bookings' => [],
    'dashboard_error' => '', // For displaying errors related to dashboard data fetching
];

$conn = null;
try {
    $conn = get_db_connection();
    $result_bookings = $conn->query("SELECT COUNT(*) as count FROM bookings WHERE status != 'cancelled'");
    if ($result_bookings) $template_vars['total_bookings'] = $result_bookings->fetch_assoc()['count'];

    $result_rooms = $conn->query("SELECT COUNT(*) as count FROM rooms WHERE is_active = TRUE");
    if ($result_rooms) $template_vars['total_rooms'] = $result_rooms->fetch_assoc()['count'];

    $result_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = TRUE");
    if ($result_users) $template_vars['total_users'] = $result_users->fetch_assoc()['count'];

    $stmt_recent = $conn->query("SELECT b.id, b.guest_name, b.check_in_date, b.total_price, b.status FROM bookings b ORDER BY b.created_at DESC LIMIT 5");
    if ($stmt_recent) {
        while($row = $stmt_recent->fetch_assoc()) {
            $template_vars['recent_bookings'][] = $row;
        }
        $stmt_recent->close(); // Close the statement
    } else {
        // Handle query error for recent bookings if necessary
        error_log("Admin Dashboard: Recent bookings query failed - " . $conn->error);
    }
} catch (Exception $e) {
    error_log("Admin Dashboard Data Error: " . $e->getMessage());
    $template_vars['dashboard_error'] = get_translation('admin_dashboard', 'error_data_fetch', 'ダッシュボードデータの取得に失敗しました。');
} finally {
    if ($conn) $conn->close();
}

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        // $current_admin_username is set in admin_auth_check.php, Twig gets it from session global
        echo $twig->render('admin/dashboard.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for admin/dashboard.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured for admin dashboard or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
