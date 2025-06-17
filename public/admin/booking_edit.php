<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/admin_auth_check.php';

$booking_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$template_vars = [
    'page_title' => get_translation('admin_booking_edit', 'default_page_title', '予約編集'),
    'active_menu' => 'bookings',
    'booking_id' => $booking_id,
    'booking' => null,
    'rooms_list' => [],
    'errors' => [],
    'success_message' => '', // Not typically used here if redirecting with flash
    'csrf_token' => '',
];

if (!$booking_id) {
    // Using session flash for error before redirecting or exiting
    $_SESSION['admin_flash_message_error'] = get_translation('admin_booking_edit', 'error_no_id', '予約IDが指定されていません。');
    header('Location: bookings_list.php'); // Redirect if no ID
    exit;
}

$template_vars['page_title'] = str_replace('%id%', $booking_id, get_translation('admin_booking_edit', 'page_title_with_id', '予約編集 (ID: %id%)'));
$conn = null;
try {
    $conn = get_db_connection();
    $sql_booking_details = "SELECT b.*, u.name as user_name, u.email as user_email, GROUP_CONCAT(DISTINCT r.id ORDER BY r.id SEPARATOR ',') as current_room_ids, GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') as current_room_names, GROUP_CONCAT(DISTINCT rt.name ORDER BY rt.name SEPARATOR ', ') as current_room_type_names FROM bookings b LEFT JOIN users u ON b.user_id = u.id LEFT JOIN booking_rooms br ON b.id = br.booking_id LEFT JOIN rooms r ON br.room_id = r.id LEFT JOIN room_types rt ON r.room_type_id = rt.id WHERE b.id = ? GROUP BY b.id";
    $stmt_details = $conn->prepare($sql_booking_details);
    if (!$stmt_details) throw new Exception(get_translation('admin_booking_edit', 'error_db_prepare_failed', "DB準備エラー(詳細)"));
    $stmt_details->bind_param("i", $booking_id);
    $stmt_details->execute();
    $result_details = $stmt_details->get_result();
    if ($result_details->num_rows === 1) {
        $template_vars['booking'] = $result_details->fetch_assoc();
    } else {
        $_SESSION['admin_flash_message_error'] = str_replace('%id%', $booking_id, get_translation('admin_booking_edit', 'error_booking_not_found', '指定予約 (ID: %id%) が見つかりません。'));
        header('Location: bookings_list.php'); // Redirect if booking not found
        exit;
    }
    $stmt_details->close();

    $result_rooms = $conn->query("SELECT id, name, capacity FROM rooms WHERE is_active = TRUE ORDER BY name");
    if ($result_rooms) { while ($row = $result_rooms->fetch_assoc()) { $template_vars['rooms_list'][] = $row; } }
    else { throw new Exception(get_translation('admin_booking_edit', 'error_fetch_rooms_failed', "部屋リスト取得失敗")); }

} catch (Exception $e) {
    // Store error in session flash and redirect to list page
    $_SESSION['admin_flash_message_error'] = get_translation('admin_booking_edit', 'error_fetch_exception', '予約情報取得エラー。') . ' ' . h($e->getMessage());
    error_log("Admin Booking Edit (Load) Error: BookingID {$booking_id} - " . $e->getMessage());
    header('Location: bookings_list.php');
    exit;
} finally {
    if ($conn) $conn->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
    } else {
        // Repopulate form values for re-display in case of error
        $template_vars['booking']['guest_name'] = trim($_POST['guest_name'] ?? $template_vars['booking']['guest_name']);
        $template_vars['booking']['guest_email'] = trim($_POST['guest_email'] ?? $template_vars['booking']['guest_email']);
        $template_vars['booking']['guest_phone'] = trim($_POST['guest_phone'] ?? $template_vars['booking']['guest_phone']);
        $template_vars['booking']['check_in_date'] = trim($_POST['check_in_date'] ?? '');
        $template_vars['booking']['check_out_date'] = trim($_POST['check_out_date'] ?? '');
        $template_vars['booking']['num_adults'] = filter_input(INPUT_POST, 'num_adults', FILTER_VALIDATE_INT);
        $template_vars['booking']['num_children'] = filter_input(INPUT_POST, 'num_children', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
        // Selected room ID for the form, not the actual current_room_ids
        // $template_vars['booking']['current_room_ids'] = filter_input(INPUT_POST, 'room_id_new', FILTER_VALIDATE_INT);
        $template_vars['booking']['total_price'] = filter_input(INPUT_POST, 'total_price', FILTER_VALIDATE_FLOAT);
        $template_vars['booking']['status'] = trim($_POST['status'] ?? '');
        $template_vars['booking']['payment_status'] = trim($_POST['payment_status'] ?? '');
        $template_vars['booking']['special_requests'] = trim($_POST['special_requests'] ?? '');

        $new_room_id_selected = filter_input(INPUT_POST, 'room_id_new', FILTER_VALIDATE_INT);


        if (empty($template_vars['booking']['check_in_date']) || empty($template_vars['booking']['check_out_date']) || strtotime($template_vars['booking']['check_out_date']) <= strtotime($template_vars['booking']['check_in_date'])) {
            $template_vars['errors'][] = get_translation('admin_booking_edit', 'error_invalid_dates', '日付が無効です。');
        }
        if ($template_vars['booking']['num_adults'] === false || $template_vars['booking']['num_adults'] < 1) {
            $template_vars['errors'][] = get_translation('admin_booking_edit', 'error_invalid_adults', '大人の人数は1名以上で入力してください。');
        }
        if ($template_vars['booking']['num_children'] === false || $template_vars['booking']['num_children'] < 0) {
            $template_vars['errors'][] = get_translation('admin_booking_edit', 'error_invalid_children', '子供の人数は0名以上で入力してください。');
        }
         if ($template_vars['booking']['total_price'] === false || $template_vars['booking']['total_price'] < 0) {
            $template_vars['errors'][] = get_translation('admin_booking_edit', 'error_invalid_price', '合計金額は0以上で入力してください。');
        }


        if (empty($template_vars['errors'])) {
            $conn_update = null;
            try {
                $conn_update = get_db_connection();
                $conn_update->begin_transaction();

                $sql_update_booking = "UPDATE bookings SET check_in_date = ?, check_out_date = ?, num_adults = ?, num_children = ?, total_price = ?, status = ?, payment_status = ?, special_requests = ?, updated_at = NOW()";
                $bind_types = "ssiidfsss";
                $_params_update = [
                    $template_vars['booking']['check_in_date'], $template_vars['booking']['check_out_date'],
                    $template_vars['booking']['num_adults'], $template_vars['booking']['num_children'],
                    $template_vars['booking']['total_price'], $template_vars['booking']['status'],
                    $template_vars['booking']['payment_status'], $template_vars['booking']['special_requests']
                ];

                // Only update guest details if it's not a registered user's booking
                $original_booking_data_for_guest_fields = $result_details->fetch_assoc() ?: $template_vars['booking']; // Use initially fetched data if possible

                if (!$original_booking_data_for_guest_fields['user_id']) {
                    $sql_update_booking .= ", guest_name = ?, guest_email = ?, guest_phone = ?";
                    $bind_types .= "sss";
                    $_params_update[] = $template_vars['booking']['guest_name'];
                    $_params_update[] = $template_vars['booking']['guest_email'];
                    $_params_update[] = $template_vars['booking']['guest_phone'];
                }
                $sql_update_booking .= " WHERE id = ?";
                $bind_types .= "i"; $_params_update[] = $booking_id;

                $stmt_update_booking = $conn_update->prepare($sql_update_booking);
                if (!$stmt_update_booking) throw new Exception(get_translation('admin_booking_edit', 'error_update_booking_prep_failed', '予約更新準備失敗'));
                $stmt_update_booking->bind_param($bind_types, ...$_params_update);
                $stmt_update_booking->execute();
                $stmt_update_booking->close();

                $current_room_id_array = explode(',', $original_booking_data_for_guest_fields['current_room_ids'] ?? '');
                $first_current_room_id = !empty($current_room_id_array) ? trim($current_room_id_array[0]) : null;

                if ($new_room_id_selected && $new_room_id_selected != $first_current_room_id) {
                    $new_room_price = 0;
                    $stmt_price = $conn_update->prepare("SELECT price_per_night FROM rooms WHERE id = ?");
                    if (!$stmt_price) throw new Exception(get_translation('admin_booking_edit', 'error_fetch_price_prep_failed','部屋価格取得準備失敗'));
                    $stmt_price->bind_param("i", $new_room_id_selected); $stmt_price->execute(); $res_price = $stmt_price->get_result();
                    if($r_price = $res_price->fetch_assoc()) $new_room_price = $r_price['price_per_night']; else throw new Exception(get_translation('admin_booking_edit', 'error_fetch_price_failed', '部屋価格取得失敗'));
                    $stmt_price->close();

                    $stmt_del_br = $conn_update->prepare("DELETE FROM booking_rooms WHERE booking_id = ?");
                    if (!\$stmt_del_br) throw new Exception(get_translation('admin_booking_edit', 'error_delete_br_prep_failed','予約部屋削除準備失敗'));
                    $stmt_del_br->bind_param("i", $booking_id); $stmt_del_br->execute(); $stmt_del_br->close();

                    $stmt_ins_br = $conn_update->prepare("INSERT INTO booking_rooms (booking_id, room_id, price_at_booking, created_at) VALUES (?, ?, ?, NOW())");
                    if (!\$stmt_ins_br) throw new Exception(get_translation('admin_booking_edit', 'error_insert_br_prep_failed','予約部屋登録準備失敗'));
                    $stmt_ins_br->bind_param("iid", $booking_id, $new_room_id_selected, $new_room_price); $stmt_ins_br->execute(); $stmt_ins_br->close();
                }
                $conn_update->commit();
                $_SESSION['admin_flash_message'] = str_replace('%id%', $booking_id, get_translation('admin_booking_edit', 'update_success', '予約 (ID: %id%) が更新されました。'));
                header("Location: bookings_list.php?update_success_id=" . $booking_id);
                exit;
            } catch (Exception $e) {
                if ($conn_update) $conn_update->rollback();
                $template_vars['errors'][] = get_translation('admin_booking_edit', 'update_exception', '予約更新エラー。') . ' ' . h($e->getMessage());
                error_log("Admin Booking Update Error: BookingID {$booking_id} - " . $e->getMessage());
            } finally {
                if ($conn_update) $conn_update->close();
            }
        }
    }
    $template_vars['csrf_token'] = generate_csrf_token(); // Regenerate CSRF token if there were errors
}


if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('admin/booking_edit.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log('Twig Render Error for admin/booking_edit.html.twig: ' . $e->getMessage());
        die ('テンプレートのレンダリング中にエラーが発生しました。管理者に連絡してください。');
    }
} else {
    error_log('Twig is not configured for admin booking edit or not an instance of Twig\\Environment.');
    die('テンプレートエンジンが正しく設定されていません。管理者に連絡してください。');
}
?>
