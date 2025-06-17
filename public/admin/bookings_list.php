<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php';

$template_vars = [
    'page_title' => get_translation('admin_bookings', 'list_page_title', '予約管理'),
    'active_menu' => 'bookings',
    'bookings' => [],
    'errors' => [],
    'success_message' => '',
    'filter_status' => $_GET['status'] ?? '',
    'filter_date_from' => $_GET['date_from'] ?? '',
    'filter_date_to' => $_GET['date_to'] ?? '',
    'search_query' => $_GET['q'] ?? '',
    'csrf_token' => '', // Initialize
];

// Retrieve flash message from session if available (e.g., after redirect from edit page)
if (isset($_SESSION['admin_flash_message'])) {
    $template_vars['success_message'] = $_SESSION['admin_flash_message'];
    unset($_SESSION['admin_flash_message']);
}
// Also handle direct success message from GET param (e.g. after update on booking_edit.php)
if (isset($_GET['update_success_id'])) {
    $template_vars['success_message'] = str_replace('%id%', h($_GET['update_success_id']), get_translation('admin_bookings', 'update_success_flash', '予約 (ID: %id%) の情報が更新されました。'));
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_cancel_booking') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        $booking_id_to_cancel = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        if ($booking_id_to_cancel) {
            $conn_cancel = null;
            try {
                $conn_cancel = get_db_connection();
                $stmt_cancel = $conn_cancel->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status NOT IN ('cancelled', 'completed', 'rejected')");
                if (!$stmt_cancel) throw new Exception('Cancel query prep failed'); // This message will be generic, specific one below
                $stmt_cancel->bind_param("i", $booking_id_to_cancel);
                if ($stmt_cancel->execute()) {
                    if ($stmt_cancel->affected_rows > 0) {
                        $template_vars['success_message'] = str_replace('%id%', h($booking_id_to_cancel), get_translation('admin_bookings', 'cancel_success', '予約 (ID: %id%) をキャンセルしました。'));
                    } else {
                        $template_vars['errors'][] = str_replace('%id%', h($booking_id_to_cancel), get_translation('admin_bookings', 'cancel_failed_or_done', '予約 (ID: %id%) のキャンセル失敗または処理済み。'));
                    }
                } else {
                    throw new Exception('Cancel execute failed'); // Generic, specific one below
                }
                $stmt_cancel->close();
            } catch (Exception $e) {
                // Use more specific error messages if possible, or a general one
                $template_vars['errors'][] = get_translation('admin_bookings', 'cancel_exception', '予約キャンセル中にエラー発生。') . ' ' . h($e->getMessage());
                error_log("Admin Booking Cancel Error (List): AdminID {$_SESSION['admin_id']}, BookingID {$booking_id_to_cancel} - " . $e->getMessage());
            } finally {
                if ($conn_cancel) $conn_cancel->close();
            }
        } else {
            $template_vars['errors'][] = get_translation('admin_bookings', 'cancel_invalid_id', 'キャンセル対象の予約IDが無効です。');
        }
    }
}
$template_vars['csrf_token'] = generate_csrf_token(); // Generate for cancel forms on the list

$conn_list = null;
try {
    $conn_list = get_db_connection();
    $sql = "SELECT b.id as booking_id, b.check_in_date, b.check_out_date, b.num_adults, b.num_children, b.total_price, b.status as booking_status, b.created_at as booking_created_at, b.guest_name as booking_guest_name, b.guest_email, u.name as user_name, u.email as user_email, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') as room_names, GROUP_CONCAT(DISTINCT rt.name ORDER BY rt.name SEPARATOR ', ') as room_type_names, b.payment_status FROM bookings b LEFT JOIN users u ON b.user_id = u.id LEFT JOIN booking_rooms br ON b.id = br.booking_id LEFT JOIN rooms r ON br.room_id = r.id LEFT JOIN room_types rt ON r.room_type_id = rt.id";
    $where_clauses = []; $_params = []; $types = ""; // Renamed $params to $_params to avoid conflict with $template_vars
    if (!empty($template_vars['filter_status'])) { $where_clauses[] = "b.status = ?"; $_params[] = $template_vars['filter_status']; $types .= "s"; }
    if (!empty($template_vars['filter_date_from'])) { $where_clauses[] = "b.check_in_date >= ?"; $_params[] = $template_vars['filter_date_from']; $types .= "s"; }
    if (!empty($template_vars['filter_date_to'])) { $where_clauses[] = "b.check_out_date <= ?"; $_params[] = $template_vars['filter_date_to']; $types .= "s"; }
    if (!empty($template_vars['search_query'])) {
        $where_clauses[] = "(b.guest_name LIKE ? OR u.name LIKE ? OR b.guest_email LIKE ? OR u.email LIKE ? OR CAST(b.id AS CHAR) LIKE ?)";
        $search_like = "%" . $template_vars['search_query'] . "%";
        for ($i=0; $i<5; $i++) { $_params[] = $search_like; $types .= "s"; }
    }
    if (!empty($where_clauses)) { $sql .= " WHERE " . implode(" AND ", $where_clauses); }
    $sql .= " GROUP BY b.id ORDER BY b.check_in_date DESC, b.id DESC";
    // TODO: Implement pagination
    $stmt_list = $conn_list->prepare($sql);
    if (!$stmt_list) throw new Exception(get_translation('admin_bookings', 'error_list_query_prep_failed', '一覧クエリ準備失敗.') . ' SQL: ' . $sql . ' Error: ' . $conn_list->error);
    if (!empty($_params)) { $stmt_list->bind_param($types, ...$_params); }
    $stmt_list->execute();
    $result = $stmt_list->get_result();
    while ($row = $result->fetch_assoc()) { $template_vars['bookings'][] = $row; }
    $stmt_list->close();
} catch (Exception $e) {
    $template_vars['errors'][] = get_translation('admin_bookings', 'list_fetch_exception', '予約一覧取得エラー。') . ' ' . h($e->getMessage());
    error_log("Admin Bookings List Fetch Error: " . $e->getMessage());
} finally {
    if ($conn_list) $conn_list->close();
}

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('admin/bookings_list.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for admin/bookings_list.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured for admin bookings list or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
