<?php
require_once __DIR__ . '/../config/config.php'; // Twig環境($twig)もここで初期化される

// リクエストされたパスを取得 (末尾のスラッシュは除去、空なら 'home' とする)
$path = trim($_GET['path'] ?? 'home', '/');
if (empty($path)) {
    $path = 'home';
}

// 簡単なルーティング (正規表現やより高度なルーティングライブラリの利用も検討可)
// 各ケースで $template_vars と $template_file を設定する
$template_vars = [];
$template_file = '';

// 共通で使う変数を先に初期化 (Twigのグローバル変数と重複するものは注意)
$template_vars['page_title_default'] = get_translation('common', 'hotel_booking_system', 'ホテル予約システム');
$template_vars['errors'] = []; // 各ページでエラーがあればここに格納
$template_vars['success_message'] = ''; // 各ページで成功メッセージがあればここに格納
$template_vars['csrf_token'] = generate_csrf_token(); // 多くのフォームで必要
// active_menu should be set per route if needed for base.html.twig's navigation highlighting
$template_vars['active_menu'] = $path; // Basic assignment, might need refinement per route


// ルートに基づいて処理を分岐
switch ($path) {
    case 'home':
    case '': // ルートアクセスもhomeとして扱う
        $template_file = 'index.html.twig'; // 元の空室検索ページ
        $template_vars['page_title'] = get_translation('index', 'page_title', '空室検索 - ホテル予約システム');
        $template_vars['available_rooms'] = [];
        $template_vars['search_error'] = '';
        $template_vars['search_params'] = [
            'check_in_date' => $_REQUEST['check_in_date'] ?? '', // POST or GET
            'check_out_date' => $_REQUEST['check_out_date'] ?? '',
            'num_adults' => (int)($_REQUEST['num_adults'] ?? 1),
        ];
        $template_vars['submitted'] = false;

        if (($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'search_rooms') ||
            ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'search_rooms') ) { // GETでの検索も許容する場合
            $template_vars['submitted'] = true;
            if (empty($template_vars['search_params']['check_in_date']) || empty($template_vars['search_params']['check_out_date'])) {
                $template_vars['search_error'] = get_translation('index', 'error_dates_required', 'チェックイン日とチェックアウト日を入力してください。');
            } elseif (strtotime($template_vars['search_params']['check_in_date']) >= strtotime($template_vars['search_params']['check_out_date'])) {
                $template_vars['search_error'] = get_translation('index', 'error_checkout_after_checkin', 'チェックアウト日はチェックイン日より後の日付を選択してください。');
            } elseif (strtotime($template_vars['search_params']['check_in_date']) < strtotime('today midnight')) { // Compare with midnight to include today
                $template_vars['search_error'] = get_translation('index', 'error_checkin_not_past', 'チェックイン日には本日以降の日付を選択してください。');
            } elseif ($template_vars['search_params']['num_adults'] < 1) {
                $template_vars['search_error'] = get_translation('index', 'error_min_one_adult', '大人の人数は1名以上を選択してください。');
            } else {
                $conn = null;
                try {
                    $conn = get_db_connection();
                    $search_result = search_available_rooms($conn, $template_vars['search_params']['check_in_date'], $template_vars['search_params']['check_out_date'], $template_vars['search_params']['num_adults']);
                    if (isset($search_result['error'])) {
                        // Attempt to translate known error keys if they are returned by search_available_rooms
                        $error_key_prefix = 'error_'; // Assuming errors from search_available_rooms might be translatable
                        if (strpos($search_result['error'], $error_key_prefix) === 0) {
                             $template_vars['search_error'] = get_translation('search_errors', substr($search_result['error'], strlen($error_key_prefix)), $search_result['error']);
                        } else {
                             $template_vars['search_error'] = $search_result['error']; // Or a generic message
                        }
                    } else {
                        $template_vars['available_rooms'] = $search_result;
                    }
                } catch (Exception $e) {
                    $template_vars['search_error'] = get_translation('index', 'error_search_failed', 'データベース接続または検索中にエラーが発生しました。');
                    error_log('Search Error (index.php router): ' . $e->getMessage());
                } finally { if ($conn) $conn->close(); }
            }
        }
        break;

    case 'login':
        $template_file = 'login.html.twig';
        $template_vars['page_title'] = get_translation('login', 'page_title', 'ログイン - ホテル予約システム');
        $template_vars['email_value'] = '';

        if (isset($_SESSION['user_id'])) { header('Location: ' . rtrim(SITE_URL, '/') . '/mypage'); exit; }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $template_vars['email_value'] = trim($_POST['email'] ?? '');
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
            } else {
                $email = trim($_POST['email'] ?? ''); $password = $_POST['password'] ?? '';
                if (empty($email)) { $template_vars['errors'][] = get_translation('login', 'error_email_required', 'メールアドレスを入力してください。'); }
                elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $template_vars['errors'][] = get_translation('login', 'error_email_invalid', '有効なメールアドレスを入力してください。');}
                if (empty($password)) { $template_vars['errors'][] = get_translation('login', 'error_password_required', 'パスワードを入力してください。');}
                if (empty($template_vars['errors'])) {
                    $conn = null; try {
                        $conn = get_db_connection();
                        $stmt = $conn->prepare("SELECT id, name, email, password_hash, is_active FROM users WHERE email = ?");
                        if (!$stmt) throw new Exception(get_translation('login', 'error_db_prepare_failed', 'データベースエラーが発生しました。'));
                        $stmt->bind_param("s", $email); $stmt->execute(); $result = $stmt->get_result();
                        if ($user = $result->fetch_assoc()) {
                            if ($user['is_active'] && password_verify($password, $user['password_hash'])) {
                                session_regenerate_id(true); $_SESSION['user_id'] = $user['id']; $_SESSION['user_name'] = $user['name']; $_SESSION['user_email'] = $user['email'];
                                header('Location: ' . rtrim(SITE_URL, '/') . '/mypage'); exit;
                            }
                        }
                        $template_vars['errors'][] = get_translation('login', 'error_auth_failed', 'メールアドレスまたはパスワードが正しくありません。');
                        if($stmt) $stmt->close();
                    } catch (Exception $e) { $template_vars['errors'][] = get_translation('login', 'error_login_exception', 'ログイン処理中にエラーが発生しました。'); error_log("Login Exception (router): " . $e->getMessage());
                    } finally { if ($conn) $conn->close(); }
                }
            }
            if (!empty($template_vars['errors'])) $template_vars['csrf_token'] = generate_csrf_token();
        }
        break;

    case 'register':
        $template_file = 'register.html.twig';
        $template_vars['page_title'] = get_translation('register', 'page_title', '会員登録');
        $template_vars['form_values'] = ['name' => $_POST['name'] ?? '', 'email' => $_POST['email'] ?? ''];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
            } else {
                $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? ''; $password_confirm = $_POST['password_confirm'] ?? '';
                $agree_terms = isset($_POST['agree_terms']);

                if (empty($name)) { $template_vars['errors'][] = get_translation('register', 'error_name_required', '氏名は必須です。'); }
                if (empty($email)) { $template_vars['errors'][] = get_translation('register', 'error_email_required', 'メールアドレスは必須です。'); }
                elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $template_vars['errors'][] = get_translation('register', 'error_email_invalid', '有効なメールアドレスを入力してください。'); }
                if (empty($password)) { $template_vars['errors'][] = get_translation('register', 'error_password_required', 'パスワードは必須です。');
                } elseif (strlen($password) < 8) { $template_vars['errors'][] = get_translation('register', 'error_password_minlength_8', 'パスワードは8文字以上で。');
                } elseif (!preg_match('/[A-Z]/', $password)) { $template_vars['errors'][] = get_translation('register', 'error_password_uppercase_required', 'パスワードに大文字要。');
                } elseif (!preg_match('/[a-z]/', $password)) { $template_vars['errors'][] = get_translation('register', 'error_password_lowercase_required', 'パスワードに小文字要。');
                } elseif (!preg_match('/[0-9]/', $password)) { $template_vars['errors'][] = get_translation('register', 'error_password_digit_required', 'パスワードに数字要。'); }
                if ($password !== $password_confirm) { $template_vars['errors'][] = get_translation('register', 'error_password_mismatch', 'パスワード不一致。'); }
                if (!$agree_terms) { $template_vars['errors'][] = get_translation('register', 'error_agree_terms_required', '利用規約同意要。');}

                if (empty($template_vars['errors'])) {
                     $conn = null; try {
                        $conn = get_db_connection();
                        $stmt_check = $conn->prepare("SELECT id FROM users WHERE email = ?");
                        if (!\$stmt_check) throw new Exception(get_translation('register', 'error_db_prepare_failed', 'DBエラー'));
                        $stmt_check->bind_param("s", \$email); \$stmt_check->execute(); $result_check = \$stmt_check->get_result();
                        if (\$result_check->num_rows > 0) { $template_vars['errors'][] = get_translation('register', 'error_email_exists', 'メールアドレス使用済み。'); }
                        \$stmt_check->close();
                        if (empty(\$template_vars['errors'])) {
                            \$password_hash = password_hash(\$password, PASSWORD_DEFAULT);
                            if (!\$password_hash) throw new Exception(get_translation('register', 'error_password_hash_failed', 'パスワード処理失敗。'));
                            \$stmt_insert = \$conn->prepare("INSERT INTO users (name, email, password_hash, is_active, created_at, updated_at) VALUES (?, ?, ?, TRUE, NOW(), NOW())");
                            if (!\$stmt_insert) throw new Exception(get_translation('register', 'error_db_insert_failed', 'DBエラー'));
                            \$stmt_insert->bind_param("sss", \$name, \$email, \$password_hash);
                            if (\$stmt_insert->execute()) {
                                \$template_vars['success_message'] = get_translation('register', 'success_registration', '会員登録完了。ログインしてください。');
                                \$template_vars['form_values'] = ['name' => '', 'email' => '']; // Clear form on success
                            } else { throw new Exception(get_translation('register', 'error_user_save_failed', 'ユーザー情報保存失敗。')); }
                            \$stmt_insert->close();
                        }
                     } catch (Exception \$e) {
                        if ($conn && $conn->errno === 1062) { $template_vars['errors'][] = get_translation('register', 'error_email_exists', 'メールアドレス使用済み。');}
                        else {$template_vars['errors'][] = get_translation('register', 'error_registration_exception', '登録処理エラー。');}
                        error_log("Register Exception (router): " . \$e->getMessage());
                     } finally { if (\$conn) \$conn->close(); }
                }
            }
            if (!empty(\$template_vars['errors']) || !empty(\$template_vars['success_message'])) {
                 \$template_vars['csrf_token'] = generate_csrf_token();
            }
        }
        break;

    // TODO: Add routes for 'mypage', 'logout', 'room_details', 'booking_form'
    // For example:
    // case 'mypage':
    //    require __DIR__ . '/mypage.php'; // Or move logic here
    //    exit; // mypage.php will handle rendering

    case preg_match('/^room\/(\d+)$/', $path, $matches) ? $path : '': // Matches 'room/123'
        $room_id_from_path = (int)$matches[1];
        // --- Logic from old room_details.php ---
        $template_file = 'room_details.html.twig';
        $template_vars['active_menu'] = 'room_details'; // Or perhaps 'home' or empty if not a main menu item
        $template_vars['room_id'] = $room_id_from_path;
        $template_vars['page_title_default'] = get_translation('room_details', 'default_page_title', '部屋詳細');
        $template_vars['room'] = null;
        $template_vars['error_message'] = '';
        // Preserve query parameters if they exist, for booking form continuity
        $template_vars['check_in_date'] = filter_input(INPUT_GET, 'check_in', FILTER_SANITIZE_STRING);
        $template_vars['check_out_date'] = filter_input(INPUT_GET, 'check_out', FILTER_SANITIZE_STRING);
        $template_vars['num_adults'] = filter_input(INPUT_GET, 'adults', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
        $template_vars['page_title'] = $template_vars['page_title_default'];


        if (!$room_id_from_path) { // Should not happen due to regex, but as a safeguard
            $template_vars['error_message'] = get_translation('room_details', 'error_invalid_room_id', '部屋IDが無効です。');
        } else {
            $conn_rd = null; // Connection specific for this block
            try {
                $conn_rd = get_db_connection();
                $stmt_rd = $conn_rd->prepare("
                    SELECT r.*, rt.name as room_type_name, rt.description as room_type_description
                    FROM rooms r
                    JOIN room_types rt ON r.room_type_id = rt.id
                    WHERE r.id = ? AND r.is_active = TRUE
                ");
                if (!$stmt_rd) throw new Exception(get_translation('room_details', 'error_db_prepare_failed', 'データベースエラー(詳細準備)'));
                $stmt_rd->bind_param("i", $room_id_from_path);
                $stmt_rd->execute();
                $result_rd = $stmt_rd->get_result();
                if ($result_rd->num_rows > 0) {
                    $template_vars['room'] = $result_rd->fetch_assoc();
                    $template_vars['page_title'] = $template_vars['room']['name']; // Set page title to room name
                } else {
                    $template_vars['error_message'] = get_translation('room_details', 'error_room_not_found', '指定された部屋が見つからないか、現在利用できません。');
                }
                $stmt_rd->close();
            } catch (Exception $e) {
                $template_vars['error_message'] = get_translation('room_details', 'error_fetch_exception', '部屋情報の取得中にエラーが発生しました。');
                error_log("Room Details Error (Router): ID {$room_id_from_path} - " . $e->getMessage());
            } finally { if ($conn_rd) $conn_rd->close(); }
        }
        break;

    case 'booking': // Handles /booking?room_id=...&check_in=... etc.
        // --- Logic from old booking_form.php ---
        $template_file = 'booking_form.html.twig';
        $template_vars['active_menu'] = 'booking'; // Or perhaps 'home' or empty

        $room_id_bf = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
        $check_in_bf = filter_input(INPUT_GET, 'check_in', FILTER_SANITIZE_STRING);
        $check_out_bf = filter_input(INPUT_GET, 'check_out', FILTER_SANITIZE_STRING);
        $num_adults_bf = filter_input(INPUT_GET, 'adults', FILTER_VALIDATE_INT, ['options' => ['default' => 1]]);
        // num_children can also be passed via GET if search form supports it
        $num_children_bf = filter_input(INPUT_GET, 'children', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);


        $template_vars['page_title'] = get_translation('booking_form', 'page_title', '予約手続き');
        $template_vars['room'] = null;
        $template_vars['error_message'] = '';
        $template_vars['booking_error_message'] = '';
        $template_vars['booking_success_message'] = '';
        $template_vars['days'] = 0;
        $template_vars['total_price'] = 0;
        $template_vars['room_id'] = $room_id_bf;
        $template_vars['check_in_date_str'] = $check_in_bf;
        $template_vars['check_out_date_str'] = $check_out_bf;
        $template_vars['num_adults'] = $num_adults_bf;
        $template_vars['num_children'] = $num_children_bf; // pass to template
        $template_vars['form_values'] = [
            'guest_name' => $_POST['guest_name'] ?? ($_SESSION['user_name'] ?? ''), // Pre-fill if logged in
            'guest_email' => $_POST['guest_email'] ?? ($_SESSION['user_email'] ?? ''), // Pre-fill if logged in
            'guest_phone' => $_POST['guest_phone'] ?? '',
            'special_requests' => $_POST['special_requests'] ?? '',
        ];
        $template_vars['csrf_token'] = generate_csrf_token();

        if (!\$room_id_bf) {
            $template_vars['error_message'] = get_translation('booking_form', 'error_no_room_selected', '部屋が選択されていません。');
        } else {
            $conn_bf_load = null;
            try {
                $conn_bf_load = get_db_connection();
                $stmt_bf_load = $conn_bf_load->prepare("SELECT r.*, rt.name as room_type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = ? AND r.is_active = TRUE");
                if (!\$stmt_bf_load) throw new Exception(get_translation('booking_form', 'error_db_prepare_failed_room', 'DBエラー(部屋情報)'));
                $stmt_bf_load->bind_param("i", \$room_id_bf); $stmt_bf_load->execute(); $result_bf_load = \$stmt_bf_load->get_result();
                if (\$result_bf_load->num_rows > 0) {
                    $template_vars['room'] = \$result_bf_load->fetch_assoc();
                    if (empty(\$check_in_bf) || empty(\$check_out_bf) || empty(\$num_adults_bf)) {
                        $template_vars['error_message'] = get_translation('booking_form', 'error_missing_params'); $template_vars['room'] = null;
                    } else {
                        $check_in_dt_bf = new DateTime(\$check_in_bf); $check_out_dt_bf = new DateTime(\$check_out_bf);
                        if (\$check_out_dt_bf <= \$check_in_dt_bf) { $template_vars['error_message'] = get_translation('booking_form', 'error_checkout_after_checkin'); $template_vars['room'] = null; }
                        elseif (\$check_in_dt_bf < new DateTime('today midnight')) { $template_vars['error_message'] = get_translation('booking_form', 'error_checkin_not_past'); $template_vars['room'] = null; }
                        elseif (\$num_adults_bf < 1 || \$num_adults_bf > \$template_vars['room']['capacity']) { $template_vars['error_message'] = str_replace('%capacity%', \$template_vars['room']['capacity'], get_translation('booking_form', 'error_invalid_capacity')); $template_vars['room'] = null; }
                        else {
                           $interval_bf = \$check_in_dt_bf->diff(\$check_out_dt_bf); $template_vars['days'] = \$interval_bf->days;
                           if (\$template_vars['days'] <=0) {\$template_vars['error_message'] = get_translation('booking_form', 'error_invalid_stay_days'); \$template_vars['room'] = null;}
                           else {\$template_vars['total_price'] = \$template_vars['days'] * \$template_vars['room']['price_per_night'] * \$num_adults_bf; /* Add children price if applicable */ }
                        }
                    }
                } else { $template_vars['error_message'] = get_translation('booking_form', 'error_room_not_found'); }
                if(\$stmt_bf_load) \$stmt_bf_load->close();
            } catch (Exception \$e) {
                 $template_vars['error_message'] = get_translation('booking_form', 'error_fetch_room_exception', '情報取得エラー: ') . (DEBUG_MODE ? h(\$e->getMessage()) : '');
                 error_log("Booking Form Load Error (Router): RoomID {\$room_id_bf} - " . \$e->getMessage());
                 $template_vars['room'] = null;
            } finally { if (\$conn_bf_load) \$conn_bf_load->close(); }
        }

        if (\$_SERVER['REQUEST_METHOD'] === 'POST' && \$template_vars['room'] && empty(\$template_vars['error_message'])) {
            if (!isset(\$_POST['csrf_token']) || !validate_csrf_token(\$_POST['csrf_token'])) {
                $template_vars['booking_error_message'] = get_translation('common', 'error_csrf');
            } else {
                $guest_name_bf = trim(\$_POST['guest_name'] ?? '');
                $guest_email_bf = trim(\$_POST['guest_email'] ?? '');
                $guest_phone_bf = trim(\$_POST['guest_phone'] ?? '');
                $special_requests_bf = trim(\$_POST['special_requests'] ?? '');
                $total_price_from_form_bf = filter_input(INPUT_POST, 'total_price_val', FILTER_VALIDATE_FLOAT);
                $num_children_from_form_bf = filter_input(INPUT_POST, 'num_children', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]); // From hidden field if added

                if (empty(\$guest_name_bf) || empty(\$guest_email_bf)) { $template_vars['booking_error_message'] = get_translation('booking_form', 'error_name_email_required'); }
                elseif (!filter_var(\$guest_email_bf, FILTER_VALIDATE_EMAIL)) { $template_vars['booking_error_message'] = get_translation('booking_form', 'error_email_invalid'); }
                else {
                    $conn_book_route = null; try {
                        $conn_book_route = get_db_connection(); $conn_book_route->begin_transaction();
                        // Final availability check
                        $stmt_check_avail_bf = $conn_book_route->prepare("SELECT COUNT(*) as conflicts FROM bookings b JOIN booking_rooms br ON b.id = br.booking_id WHERE br.room_id = ? AND b.status NOT IN ('cancelled', 'rejected') AND ((b.check_in_date < ? AND b.check_out_date > ?) OR (b.check_in_date >= ? AND b.check_in_date < ?) OR (b.check_out_date > ? AND b.check_out_date <= ?) OR (b.check_in_date <= ? AND b.check_out_date >= ?))");
                        if (!\$stmt_check_avail_bf) throw new Exception('Avail check prep failed');
                        $stmt_check_avail_bf->bind_param("issssssss", \$room_id_bf, \$check_out_bf, \$check_in_bf, \$check_in_bf, \$check_out_bf, \$check_in_bf, \$check_out_bf, \$check_in_bf, \$check_out_bf);
                        $stmt_check_avail_bf->execute(); $res_check_avail_bf = \$stmt_check_avail_bf->get_result(); $conflicts_bf = $res_check_avail_bf->fetch_assoc()['conflicts']; $stmt_check_avail_bf->close();
                        if (\$conflicts_bf > 0) throw new Exception(get_translation('booking_form', 'error_room_no_longer_available'));

                        $user_id_for_booking_bf = \$_SESSION['user_id'] ?? null;
                        $stmt_b_insert = $conn_book_route->prepare("INSERT INTO bookings (user_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, num_adults, num_children, total_price, special_requests, status, payment_status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'unpaid', NOW(), NOW())");
                        if (!\$stmt_b_insert) throw new Exception(get_translation('booking_form', 'error_db_prepare_failed_booking'));
                        \$stmt_b_insert->bind_param("issssiisdsss", \$user_id_for_booking_bf, \$guest_name_bf, \$guest_email_bf, \$guest_phone_bf, \$check_in_bf, \$check_out_bf, \$num_adults_bf, \$num_children_from_form_bf, \$total_price_from_form_bf, \$special_requests_bf, 'confirmed', 'unpaid');
                        \$stmt_b_insert->execute(); $new_booking_id_bf = \$conn_book_route->insert_id;
                        if (!\$new_booking_id_bf) throw new Exception(get_translation('booking_form', 'error_booking_insert_id_failed')); \$stmt_b_insert->close();

                        \$price_at_b_bf = \$template_vars['room']['price_per_night'];
                        \$stmt_br_insert = $conn_book_route->prepare("INSERT INTO booking_rooms (booking_id, room_id, price_at_booking, created_at) VALUES (?, ?, ?, NOW())");
                        if (!\$stmt_br_insert) throw new Exception(get_translation('booking_form', 'error_db_prepare_failed_br'));
                        \$stmt_br_insert->bind_param("iid", \$new_booking_id_bf, \$room_id_bf, \$price_at_b_bf); \$stmt_br_insert->execute(); \$stmt_br_insert->close();

                        \$conn_book_route->commit();
                        $template_vars['booking_success_message'] = str_replace('%id%', \$new_booking_id_bf, get_translation('booking_form', 'success_booking', '予約完了！ID: %id%'));

                        \$booking_details_mail_bf = ['id' => \$new_booking_id_bf, 'check_in_date' => \$check_in_bf, 'check_out_date' => \$check_out_bf, 'num_adults' => \$num_adults_bf, 'num_children' => \$num_children_from_form_bf, 'total_price' => \$total_price_from_form_bf, 'special_requests' => \$special_requests_bf];
                        if (isset(\$template_vars['room'])) { send_booking_confirmation_email(\$booking_details_mail_bf, \$template_vars['room'], \$guest_email_bf, \$guest_name_bf); }

                    } catch (Exception \$e) {
                        if (\$conn_book_route) \$conn_book_route->rollback();
                        $template_vars['booking_error_message'] = \$e->getMessage(); // Show specific translated error from throw
                        error_log("Booking Process Error (Router): " . \$e->getMessage());
                    } finally { if (\$conn_book_route) \$conn_book_route->close(); }
                }
            }
            if (!empty(\$template_vars['booking_error_message']) || !empty(\$template_vars['booking_success_message'])) {
                 \$template_vars['csrf_token'] = generate_csrf_token();
            }
        }
        break;

    case 'mypage':
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . rtrim(SITE_URL, '/') . '/login?redirect_to=mypage');
            exit;
        }
        $template_file = 'mypage.html.twig';
        $template_vars['page_title'] = get_translation('mypage', 'page_title', 'マイページ');
        $template_vars['active_menu'] = 'mypage'; // For navigation active state
        $template_vars['user_name'] = $_SESSION['user_name'] ?? '';
        $template_vars['user_email'] = $_SESSION['user_email'] ?? '';
        $template_vars['bookings'] = [];
        $user_id = $_SESSION['user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_booking') {
            if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
                $template_vars['errors'][] = get_translation('common', 'error_csrf', '無効なリクエストです。');
            } else {
                $booking_id_to_cancel = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
                if ($booking_id_to_cancel) {
                    $conn_cancel = null;
                    try {
                        $conn_cancel = get_db_connection();
                        $stmt_cancel = $conn_cancel->prepare("UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND user_id = ? AND status NOT IN ('cancelled', 'completed', 'rejected')");
                        if (!$stmt_cancel) throw new Exception(get_translation('mypage', 'error_cancel_prepare_failed','キャンセル準備失敗'));
                        $stmt_cancel->bind_param("ii", $booking_id_to_cancel, $user_id);
                        if ($stmt_cancel->execute()) {
                            if ($stmt_cancel->affected_rows > 0) {
                                $template_vars['success_message'] = str_replace('%id%', h($booking_id_to_cancel), get_translation('mypage', 'success_cancel', '予約 (ID: %id%) をキャンセルしました。'));
                            } else {
                                $template_vars['errors'][] = str_replace('%id%', h($booking_id_to_cancel), get_translation('mypage', 'error_cancel_failed_or_done', '予約 (ID: %id%) のキャンセル失敗または処理済み。'));
                            }
                        } else { throw new Exception(get_translation('mypage', 'error_cancel_failed','キャンセル実行失敗')); }
                        $stmt_cancel->close();
                    } catch (Exception $e) {
                        $err_msg_key = DEBUG_MODE ? 'error_cancel_exception_debug' : 'error_cancel_exception_prod';
                        $err_detail = DEBUG_MODE ? h($e->getMessage()) : '';
                        $template_vars['errors'][] = str_replace('%msg%', $err_detail, get_translation('mypage', $err_msg_key, '予約キャンセル中にエラーが発生しました。'));
                        error_log("Booking Cancel Error (Mypage Router): UserID {$user_id}, BookingID {$booking_id_to_cancel} - " . $e->getMessage());
                    } finally { if ($conn_cancel) $conn_cancel->close(); }
                } else { $template_vars['errors'][] = get_translation('mypage', 'error_cancel_invalid_id','無効な予約ID'); }
            }
        }
        // CSRF token for cancel forms within mypage
        $template_vars['csrf_token'] = generate_csrf_token();

        $conn_bookings = null;
        try {
            $conn_bookings = get_db_connection();
            $stmt_bookings = $conn_bookings->prepare("
                SELECT b.id as booking_id, b.check_in_date, b.check_out_date, b.num_adults, b.num_children,
                       b.total_price, b.status as booking_status, b.created_at as booking_created_at,
                       r.name as room_name, rt.name as room_type_name
                FROM bookings b
                JOIN booking_rooms br ON b.id = br.booking_id
                JOIN rooms r ON br.room_id = r.id
                JOIN room_types rt ON r.room_type_id = rt.id
                WHERE b.user_id = ?
                ORDER BY b.check_in_date DESC, b.id DESC
            ");
            if (!\$stmt_bookings) throw new Exception(get_translation('mypage', 'error_fetch_bookings_prepare_failed','予約履歴取得準備失敗'));
            $stmt_bookings->bind_param("i", \$user_id);
            \$stmt_bookings->execute();
            \$result = \$stmt_bookings->get_result();
            while (\$row = \$result->fetch_assoc()) { \$template_vars['bookings'][] = \$row; }
            \$stmt_bookings->close();
        } catch (Exception \$e) {
            $err_msg_key = DEBUG_MODE ? 'error_fetch_bookings_exception_debug' : 'error_fetch_bookings_exception_prod';
            $err_detail = DEBUG_MODE ? h($e->getMessage()) : '';
            $template_vars['errors'][] = str_replace('%msg%', $err_detail, get_translation('mypage', $err_msg_key, '予約履歴の取得中にエラーが発生しました。'));
            error_log("Mypage Booking History Error (Router): UserID {\$user_id} - " . \$e->getMessage());
        } finally { if (\$conn_bookings) \$conn_bookings->close(); }
        break;

    case 'logout':
        // config.php should have already started session. If not, @ suppresses warning.
        if (session_status() == PHP_SESSION_NONE) @session_start();
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();
        header('Location: ' . rtrim(SITE_URL, '/') . '/login?logout=success');
        exit;
        // break; // Not strictly necessary after exit

    default:
        http_response_code(404);
        $template_file = '404.html.twig';
        $template_vars['page_title'] = get_translation('error_page', '404_title', 'ページが見つかりません');
        break;
}

if (!empty($template_file) && isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render($template_file, $template_vars);
    } catch (Exception $e) {
        error_log("Twig Render Error for {$template_file} (Router): " . $e->getMessage() . "\nVars: " . print_r($template_vars, true));
        http_response_code(500);
        $error_page_vars = [
            'page_title' => get_translation('error_page', '500_title', 'サーバーエラー'),
            'error_message_display' => DEBUG_MODE ? nl2br(h($e->getMessage() . "\n" . $e->getTraceAsString())) : get_translation('error_page', '500_message_prod', 'サーバー内部エラー。')
        ];
        // Attempt to render 500.html.twig, with a final fallback
        try {
            echo $twig->render('500.html.twig', $error_page_vars);
        } catch (Exception $final_e) {
            error_log("Critical: Twig Render Error for 500.html.twig (Router): " . $final_e->getMessage());
            echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred while trying to display the error page.</p>";
        }
    }
} elseif (empty($template_file) && !headers_sent()) {
    // If no template was set (and not already redirected), it's effectively a 404 or an unhandled route
    http_response_code(404);
    if (isset($twig) && $twig instanceof \Twig\Environment) {
        echo $twig->render('404.html.twig', ['page_title' => get_translation('error_page', '404_title', 'ページが見つかりません')]);
    } else {
        echo "<h1>404 Not Found</h1><p>The requested page was not found and no template was specified.</p>";
    }
} elseif (!isset($twig) || !($twig instanceof \Twig\Environment)) {
    error_log('Twig is not configured or not an instance of Twig\\Environment (Router).');
    die('Template engine is not properly configured.');
}
?>
