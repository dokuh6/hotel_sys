<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php';

$template_vars = [
    'page_title' => get_translation('admin_room_types', 'list_page_title', '部屋タイプ管理'),
    'active_menu' => 'room_types',
    'room_types' => [],
    'errors' => [],
    'success_message' => '',
    'csrf_token' => '', // Initialize
];

if (isset($_SESSION['admin_flash_message'])) {
    // Check if it's an array (for error type flash messages) or string
    if (is_array($_SESSION['admin_flash_message'])) {
        if (($_SESSION['admin_flash_message']['type'] ?? '') === 'error') {
            $template_vars['errors'][] = $_SESSION['admin_flash_message']['message'];
        } else {
            $template_vars['success_message'] = $_SESSION['admin_flash_message']['message'];
        }
    } else {
        $template_vars['success_message'] = $_SESSION['admin_flash_message'];
    }
    unset($_SESSION['admin_flash_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_room_type') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        $room_type_id_to_delete = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
        if ($room_type_id_to_delete) {
            $conn_delete = null;
            try {
                $conn_delete = get_db_connection();
                $conn_delete->begin_transaction();
                $stmt_check = $conn_delete->prepare("SELECT COUNT(*) as count FROM rooms WHERE room_type_id = ?");
                if (!$stmt_check) throw new Exception(get_translation('admin_room_types', 'error_check_usage_prepare_failed','使用状況チェック準備失敗'));
                $stmt_check->bind_param("i", $room_type_id_to_delete);
                $stmt_check->execute();
                $usage_count = $stmt_check->get_result()->fetch_assoc()['count'];
                $stmt_check->close();

                if ($usage_count > 0) {
                    $template_vars['errors'][] = str_replace(['%id%', '%count%'], [h($room_type_id_to_delete), h($usage_count)], get_translation('admin_room_types', 'error_delete_in_use', '部屋タイプ (ID: %id%) は%count%件の部屋で使用中のため削除不可。'));
                } else {
                    $stmt_delete = $conn_delete->prepare("DELETE FROM room_types WHERE id = ?");
                    if (!$stmt_delete) throw new Exception(get_translation('admin_room_types', 'error_delete_prepare_failed','削除クエリ準備失敗'));
                    $stmt_delete->bind_param("i", $room_type_id_to_delete);
                    if ($stmt_delete->execute()) {
                        if ($stmt_delete->affected_rows > 0) {
                            $template_vars['success_message'] = str_replace('%id%', h($room_type_id_to_delete), get_translation('admin_room_types', 'delete_success', '部屋タイプ (ID: %id%) を削除しました。'));
                        } else {
                            $template_vars['errors'][] = str_replace('%id%', h($room_type_id_to_delete), get_translation('admin_room_types', 'delete_failed_not_found', '部屋タイプ (ID: %id%) 削除失敗または存在せず。'));
                        }
                    } else {
                        throw new Exception(get_translation('admin_room_types', 'error_delete_execute_failed','削除実行失敗'));
                    }
                    $stmt_delete->close();
                }
                $conn_delete->commit();
            } catch (Exception $e) {
                if ($conn_delete) $conn_delete->rollback();
                $template_vars['errors'][] = get_translation('admin_room_types', 'delete_exception', '部屋タイプ削除エラー。') . ' ' . h($e->getMessage());
                error_log("Admin Room Type Delete Error: AdminID {$_SESSION['admin_id']}, RoomTypeID {$room_type_id_to_delete} - " . $e->getMessage());
            } finally {
                if ($conn_delete) $conn_delete->close();
            }
        } else {
            $template_vars['errors'][] = get_translation('admin_room_types', 'delete_invalid_id', '削除対象の部屋タイプIDが無効です。');
        }
    }
}
$template_vars['csrf_token'] = generate_csrf_token();

$conn_list = null;
try {
    $conn_list = get_db_connection();
    $sql = "SELECT rt.id, rt.name, rt.description, rt.created_at, rt.updated_at, COUNT(r.id) as room_count FROM room_types rt LEFT JOIN rooms r ON rt.id = r.room_type_id GROUP BY rt.id, rt.name, rt.description, rt.created_at, rt.updated_at ORDER BY rt.name ASC";
    $stmt_list = $conn_list->prepare($sql);
    if (!$stmt_list) throw new Exception(get_translation('admin_room_types', 'error_list_prepare_failed','一覧クエリ準備失敗'));
    $stmt_list->execute();
    $result = $stmt_list->get_result();
    while ($row = $result->fetch_assoc()) { $template_vars['room_types'][] = $row; }
    $stmt_list->close();
} catch (Exception $e) {
    $template_vars['errors'][] = get_translation('admin_room_types', 'list_fetch_exception', '部屋タイプ一覧取得エラー。') . ' ' . h($e->getMessage());
    error_log("Admin Room Types List Fetch Error: " . $e->getMessage());
} finally {
    if ($conn_list) $conn_list->close();
}

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('admin/room_types_list.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for admin/room_types_list.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured for admin room types list or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
