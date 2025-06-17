<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php';

$room_type_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$template_vars = [
    'page_title' => $room_type_id ? get_translation('admin_room_type_form', 'edit_page_title', '部屋タイプ編集') : get_translation('admin_room_type_form', 'create_page_title', '新規部屋タイプ登録'),
    'active_menu' => 'room_types',
    'room_type_id' => $room_type_id,
    'room_type' => ['id' => null, 'name' => '', 'description' => ''], // Initialize with defaults
    'errors' => [],
    'csrf_token' => '',
];

if ($room_type_id) {
    $template_vars['page_title'] = str_replace('%id%', $room_type_id, get_translation('admin_room_type_form', 'edit_page_title_with_id', '部屋タイプ編集 (ID: %id%)'));
    $conn_load = null;
    try {
        $conn_load = get_db_connection();
        $stmt_load = $conn_load->prepare("SELECT * FROM room_types WHERE id = ?");
        if (!$stmt_load) throw new Exception(get_translation('admin_room_type_form', 'error_load_prepare_failed', '情報取得準備失敗'));
        $stmt_load->bind_param("i", $room_type_id);
        $stmt_load->execute();
        $result_load = $stmt_load->get_result();
        if ($result_load->num_rows === 1) {
            $template_vars['room_type'] = $result_load->fetch_assoc();
        } else {
            // Set flash message and redirect
            $_SESSION['admin_flash_message'] = ['type' => 'error', 'message' => str_replace('%id%', $room_type_id, get_translation('admin_room_type_form', 'error_not_found_flash', '指定部屋タイプ (ID: %id%) が見つかりません。'))];
            header('Location: room_types_list.php');
            exit;
        }
        $stmt_load->close();
    } catch (Exception $e) {
        $_SESSION['admin_flash_message'] = ['type' => 'error', 'message' => get_translation('admin_room_type_form', 'error_load_exception', '情報取得エラー。') . ' ' . h($e->getMessage())];
        error_log("Admin Room Type Form (Load) Error: RoomTypeID {$room_type_id} - " . $e->getMessage());
        header('Location: room_types_list.php');
        exit;
    } finally {
        if ($conn_load) $conn_load->close();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        // Repopulate $template_vars['room_type'] with POST data for form redisplay on error
        $template_vars['room_type']['name'] = trim($_POST['name'] ?? '');
        $template_vars['room_type']['description'] = trim($_POST['description'] ?? '');

        if (empty($template_vars['room_type']['name'])) {
            $template_vars['errors'][] = get_translation('admin_room_type_form', 'error_name_required', '部屋タイプ名は必須です。');
        }

        // Optional: Uniqueness check (if a unique constraint is on `name` in DB, catch specific SQL error)
        if (empty($template_vars['errors'])) {
            $conn_check_name = get_db_connection();
            $sql_check = "SELECT id FROM room_types WHERE name = ?";
            $params_check = [$template_vars['room_type']['name']];
            if ($room_type_id) {
                $sql_check .= " AND id != ?";
                $params_check[] = $room_type_id;
            }
            $stmt_check_name = $conn_check_name->prepare($sql_check);
            $types_check = $room_type_id ? "si" : "s";
            $stmt_check_name->bind_param($types_check, ...$params_check);
            $stmt_check_name->execute();
            if ($stmt_check_name->get_result()->num_rows > 0) {
                $template_vars['errors'][] = get_translation('admin_room_type_form', 'error_name_exists', 'この部屋タイプ名は既に使用されています。');
            }
            $stmt_check_name->close();
            $conn_check_name->close();
        }


        if (empty($template_vars['errors'])) {
            $conn_save = null;
            try {
                $conn_save = get_db_connection();
                $conn_save->begin_transaction();

                if ($room_type_id) {
                    $stmt_save = $conn_save->prepare("UPDATE room_types SET name = ?, description = ?, updated_at = NOW() WHERE id = ?");
                    if (!\$stmt_save) throw new Exception(get_translation('admin_room_type_form', 'error_update_prepare_failed', '更新クエリ準備失敗'));
                    $stmt_save->bind_param("ssi", $template_vars['room_type']['name'], $template_vars['room_type']['description'], $room_type_id);
                } else {
                    $stmt_save = $conn_save->prepare("INSERT INTO room_types (name, description, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
                    if (!\$stmt_save) throw new Exception(get_translation('admin_room_type_form', 'error_insert_prepare_failed', '登録クエリ準備失敗'));
                    $stmt_save->bind_param("ss", $template_vars['room_type']['name'], $template_vars['room_type']['description']);
                }

                $stmt_save->execute();
                $current_processed_id = $room_type_id ?: $conn_save->insert_id;
                $stmt_save->close();
                $conn_save->commit();

                $success_msg_key = $room_type_id ? 'update_success' : 'create_success';
                $base_success_msg = get_translation('admin_room_type_form', $success_msg_key, '部屋タイプ情報が保存されました。');
                $_SESSION['admin_flash_message'] = str_replace('%id%', $current_processed_id, $base_success_msg . " (ID: %id%)");

                header("Location: room_types_list.php");
                exit;

            } catch (Exception $e) {
                if ($conn_save) $conn_save->rollback();
                 if ($conn_save && $conn_save->errno === 1062) { // MySQL duplicate entry
                    $template_vars['errors'][] = get_translation('admin_room_type_form', 'error_name_exists', 'この部屋タイプ名は既に使用されています。');
                } else {
                    $template_vars['errors'][] = get_translation('admin_room_type_form', 'save_exception', '保存エラー。') . ' ' . h($e->getMessage());
                }
                error_log("Admin Room Type Form (Save) Error: RoomTypeID " . ($room_type_id ?? 'NEW') . " - " . $e->getMessage());
            } finally {
                if ($conn_save) $conn_save->close();
            }
        }
    }
}
$template_vars['csrf_token'] = generate_csrf_token(); // Always ensure token is fresh for form display

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('admin/room_type_form.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for admin/room_type_form.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured for admin room type form or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
