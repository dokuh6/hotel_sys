<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php';

$room_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$make_active = filter_input(INPUT_GET, 'make_active', FILTER_VALIDATE_INT);

$template_vars = [
    'page_title' => $room_id ? get_translation('admin_room_form', 'edit_page_title', '部屋情報編集') : get_translation('admin_room_form', 'create_page_title', '新規部屋登録'),
    'active_menu' => 'rooms',
    'room_id' => $room_id,
    'room' => [ // Initialize with defaults
        'id' => null, 'name' => '', 'room_type_id' => '', 'description' => '',
        'price_per_night' => '', 'capacity' => '', 'image_path' => '', 'is_active' => 1, // Default to active for new rooms
    ],
    'room_types_list' => [],
    'errors' => [],
    'csrf_token' => '',
];

if ($room_id) {
    // Construct specific page title for editing
    $template_vars['page_title'] = str_replace('%id%', $room_id, get_translation('admin_room_form', 'edit_page_title_with_id', '部屋情報編集 (ID: %id%)'));
}

$conn_init = null;
try {
    $conn_init = get_db_connection();
    $result_rt = $conn_init->query("SELECT id, name FROM room_types ORDER BY name");
    if ($result_rt) { while ($row_rt = $result_rt->fetch_assoc()) { $template_vars['room_types_list'][] = $row_rt; } }
    else { throw new Exception(get_translation('admin_room_form', 'error_fetch_room_types_failed', '部屋タイプリスト取得失敗')); }


    if ($room_id) { // If editing, load existing room data
        $stmt_load = $conn_init->prepare("SELECT * FROM rooms WHERE id = ?");
        if (!$stmt_load) throw new Exception(get_translation('admin_room_form', 'error_load_room_prep_failed', '部屋情報読込準備失敗'));
        $stmt_load->bind_param("i", $room_id);
        $stmt_load->execute();
        $result_load = $stmt_load->get_result();
        if ($result_load->num_rows === 1) {
            $template_vars['room'] = $result_load->fetch_assoc(); // Overwrite default with loaded data
            if ($make_active && !$template_vars['room']['is_active']) { // If re-activating a currently inactive room
                $template_vars['room']['is_active'] = 1; // Pre-check "is_active" on the form
            }
        } else {
            $_SESSION['admin_flash_message_error'] = str_replace('%id%', $room_id, get_translation('admin_room_form', 'error_room_not_found', '指定部屋 (ID: %id%) が見つかりません。'));
            header('Location: rooms_list.php');
            exit;
        }
        $stmt_load->close();
    }
} catch (Exception $e) {
    // For critical load errors, redirecting might be better than showing a broken form
    $_SESSION['admin_flash_message_error'] = get_translation('admin_room_form', 'error_load_exception', '情報取得エラー。') . ' ' . h($e->getMessage());
    error_log("Admin Room Form (Load) Error: RoomID " . ($room_id ?? 'N/A') . " - " . $e->getMessage());
    header('Location: rooms_list.php');
    exit;
} finally {
    if ($conn_init) $conn_init->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        // Populate $template_vars['room'] with POST data for re-display in case of error
        $template_vars['room']['name'] = trim($_POST['name'] ?? '');
        $template_vars['room']['room_type_id'] = filter_input(INPUT_POST, 'room_type_id', FILTER_VALIDATE_INT);
        $template_vars['room']['description'] = trim($_POST['description'] ?? '');
        $template_vars['room']['price_per_night'] = filter_input(INPUT_POST, 'price_per_night', FILTER_VALIDATE_FLOAT, ['flags' => FILTER_NULL_ON_FAILURE]);
        $template_vars['room']['capacity'] = filter_input(INPUT_POST, 'capacity', FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
        $template_vars['room']['image_path'] = trim($_POST['image_path'] ?? '');
        $template_vars['room']['is_active'] = isset($_POST['is_active']) ? 1 : 0;

        if (empty($template_vars['room']['name'])) $template_vars['errors'][] = get_translation('admin_room_form', 'error_name_required', '部屋名は必須です。');
        if (empty($template_vars['room']['room_type_id'])) $template_vars['errors'][] = get_translation('admin_room_form', 'error_type_required', '部屋タイプを選択してください。');
        if ($template_vars['room']['price_per_night'] === null || $template_vars['room']['price_per_night'] < 0) $template_vars['errors'][] = get_translation('admin_room_form', 'error_price_invalid', '価格は0以上の有効な数値で。');
        if ($template_vars['room']['capacity'] === null || $template_vars['room']['capacity'] < 1) $template_vars['errors'][] = get_translation('admin_room_form', 'error_capacity_invalid', '定員は1以上の有効な数値で。');

        if (empty($template_vars['errors'])) {
            $conn_save = null;
            try {
                $conn_save = get_db_connection();
                $conn_save->begin_transaction();
                // Use $room_id (from GET) for existing record ID, not $template_vars['room']['id']
                if ($room_id) {
                    $stmt_save = $conn_save->prepare("UPDATE rooms SET name = ?, room_type_id = ?, description = ?, price_per_night = ?, capacity = ?, image_path = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                    if (!\$stmt_save) throw new Exception(get_translation('admin_room_form', 'error_update_prep_failed','更新クエリ準備失敗'));
                    $stmt_save->bind_param("sisdiisi", $template_vars['room']['name'], $template_vars['room']['room_type_id'], $template_vars['room']['description'], $template_vars['room']['price_per_night'], $template_vars['room']['capacity'], $template_vars['room']['image_path'], $template_vars['room']['is_active'], $room_id);
                } else {
                    $stmt_save = $conn_save->prepare("INSERT INTO rooms (name, room_type_id, description, price_per_night, capacity, image_path, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    if (!\$stmt_save) throw new Exception(get_translation('admin_room_form', 'error_insert_prep_failed','登録クエリ準備失敗'));
                    $stmt_save->bind_param("sisdiis", $template_vars['room']['name'], $template_vars['room']['room_type_id'], $template_vars['room']['description'], $template_vars['room']['price_per_night'], $template_vars['room']['capacity'], $template_vars['room']['image_path'], $template_vars['room']['is_active']);
                }
                $stmt_save->execute();
                $current_processed_id = $room_id ?: $conn_save->insert_id;
                $stmt_save->close();
                $conn_save->commit();

                $success_msg_key = $room_id ? 'update_success' : 'create_success';
                $base_success_msg = get_translation('admin_room_form', $success_msg_key, '部屋情報が保存されました。');
                $_SESSION['admin_flash_message'] = str_replace('%id%', $current_processed_id, $base_success_msg . " (ID: %id%)");

                header("Location: rooms_list.php");
                exit;
            } catch (Exception $e) {
                if ($conn_save) $conn_save->rollback();
                $template_vars['errors'][] = get_translation('admin_room_form', 'save_exception', '保存エラー。') . ' ' . h($e->getMessage());
                error_log("Admin Room Form (Save) Error: RoomID " . ($room_id ?? 'NEW') . " - " . $e->getMessage());
            } finally {
                if ($conn_save) $conn_save->close();
            }
        }
    }
}
$template_vars['csrf_token'] = generate_csrf_token(); // Always ensure CSRF token is set for the form


if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('admin/room_form.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for admin/room_form.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured for admin room form or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
