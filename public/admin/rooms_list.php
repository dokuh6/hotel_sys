<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php';

$template_vars = [
    'page_title' => get_translation('admin_rooms', 'list_page_title', '部屋管理'),
    'active_menu' => 'rooms',
    'rooms' => [],
    'errors' => [],
    'success_message' => '',
    'csrf_token' => '', // Initialize
];

if (isset($_SESSION['admin_flash_message'])) {
    $template_vars['success_message'] = $_SESSION['admin_flash_message'];
    unset($_SESSION['admin_flash_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_room') { // Action is deactivation
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        $room_id_to_deactivate = filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT);
        if ($room_id_to_deactivate) {
            $conn_action = null; // Renamed variable
            try {
                $conn_action = get_db_connection();
                $stmt_action = $conn_action->prepare("UPDATE rooms SET is_active = FALSE, updated_at = NOW() WHERE id = ?");
                if (!$stmt_action) throw new Exception(get_translation('admin_rooms', 'error_deactivate_prepare_failed', '部屋非表示クエリ準備失敗'));
                $stmt_action->bind_param("i", $room_id_to_deactivate);
                if ($stmt_action->execute()) {
                    if ($stmt_action->affected_rows > 0) {
                        $template_vars['success_message'] = str_replace('%id%', h($room_id_to_deactivate), get_translation('admin_rooms', 'deactivate_success', '部屋 (ID: %id%) を非表示にしました。'));
                    } else {
                        $template_vars['errors'][] = str_replace('%id%', h($room_id_to_deactivate), get_translation('admin_rooms', 'deactivate_failed_or_done', '部屋 (ID: %id%) の非表示失敗または処理済み。'));
                    }
                } else {
                    throw new Exception(get_translation('admin_rooms', 'error_deactivate_execute_failed', '部屋非表示処理失敗'));
                }
                $stmt_action->close();
            } catch (Exception $e) {
                $template_vars['errors'][] = get_translation('admin_rooms', 'deactivate_exception', '部屋非表示処理中にエラー発生。') . ' ' . h($e->getMessage());
                error_log("Admin Room Deactivate Error (List): AdminID {$_SESSION['admin_id']}, RoomID {$room_id_to_deactivate} - " . $e->getMessage());
            } finally {
                if ($conn_action) $conn_action->close();
            }
        } else {
            $template_vars['errors'][] = get_translation('admin_rooms', 'deactivate_invalid_id', '非表示対象の部屋IDが無効です。');
        }
    }
}
$template_vars['csrf_token'] = generate_csrf_token(); // For deactivation forms on the list

$conn_list = null;
try {
    $conn_list = get_db_connection();
    $sql = "SELECT r.id, r.name as room_name, r.price_per_night, r.capacity, r.is_active, rt.name as room_type_name, r.updated_at FROM rooms r LEFT JOIN room_types rt ON r.room_type_id = rt.id ORDER BY r.is_active DESC, rt.name ASC, r.name ASC";
    $stmt_list = $conn_list->prepare($sql);
    if (!$stmt_list) throw new Exception(get_translation('admin_rooms', 'error_list_query_prep_failed', '部屋一覧取得クエリ準備失敗'));
    $stmt_list->execute();
    $result = $stmt_list->get_result();
    while ($row = $result->fetch_assoc()) { $template_vars['rooms'][] = $row; }
    $stmt_list->close();
} catch (Exception $e) {
    $template_vars['errors'][] = get_translation('admin_rooms', 'list_fetch_exception', '部屋一覧取得エラー。') . ' ' . h($e->getMessage());
    error_log("Admin Rooms List Fetch Error: " . $e->getMessage());
} finally {
    if ($conn_list) $conn_list->close();
}

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('admin/rooms_list.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for admin/rooms_list.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured for admin rooms list or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
