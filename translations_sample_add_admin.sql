-- Admin Common
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'admin_common', 'default_admin_title', '管理画面'), (2, 'admin_common', 'default_admin_title', 'Admin Panel'),
(1, 'admin_common', 'admin_panel_suffix', '管理画面'), (2, 'admin_common', 'admin_panel_suffix', 'Admin Panel'),
(1, 'admin_common', 'welcome_message', 'ようこそ、%name% 様'), (2, 'admin_common', 'welcome_message', 'Welcome, %name%!'),
(1, 'admin_common', 'menu_heading', 'メニュー'), (2, 'admin_common', 'menu_heading', 'Menu'),
(1, 'admin_common', 'menu_dashboard', 'ダッシュボード'), (2, 'admin_common', 'menu_dashboard', 'Dashboard'),
(1, 'admin_common', 'menu_bookings', '予約管理'), (2, 'admin_common', 'menu_bookings', 'Booking Management'),
(1, 'admin_common', 'menu_rooms', '部屋管理'), (2, 'admin_common', 'menu_rooms', 'Room Management'),
(1, 'admin_common', 'menu_room_types', '部屋タイプ管理'), (2, 'admin_common', 'menu_room_types', 'Room Type Management'),
(1, 'admin_common', 'menu_users', '顧客管理'), (2, 'admin_common', 'menu_users', 'Customer Management'),
(1, 'admin_common', 'menu_admin_users', '管理者管理'), (2, 'admin_common', 'menu_admin_users', 'Admin User Management'),
(1, 'admin_common', 'menu_settings', 'サイト設定'), (2, 'admin_common', 'menu_settings', 'Site Settings');

-- Admin Login Page
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'admin_login', 'page_title', '管理者ログイン'), (2, 'admin_login', 'page_title', 'Admin Login'),
(1, 'admin_login', 'form_heading', '管理者ログイン'), (2, 'admin_login', 'form_heading', 'Administrator Login'),
(1, 'admin_login', 'username_label', 'ユーザー名:'), (2, 'admin_login', 'username_label', 'Username:'),
(1, 'admin_login', 'password_label', 'パスワード:'), (2, 'admin_login', 'password_label', 'Password:'),
(1, 'admin_login', 'error_username_required', 'ユーザー名を入力してください。'), (2, 'admin_login', 'error_username_required', 'Please enter your username.'),
(1, 'admin_login', 'error_password_required', 'パスワードを入力してください。'), (2, 'admin_login', 'error_password_required', 'Please enter your password.'),
(1, 'admin_login', 'error_auth_failed', 'ユーザー名またはパスワードが正しくありません。'), (2, 'admin_login', 'error_auth_failed', 'Incorrect username or password.'),
(1, 'admin_login', 'error_login_exception', 'ログイン処理中にエラーが発生しました。'), (2, 'admin_login', 'error_login_exception', 'An error occurred during login.'),
(1, 'admin_login', 'error_db_prepare_failed', 'データベースエラー(準備)'), (2, 'admin_login', 'error_db_prepare_failed', 'Database error (prepare)');


-- Admin Dashboard Page
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'admin_dashboard', 'page_title', 'ダッシュボード'), (2, 'admin_dashboard', 'page_title', 'Dashboard'),
(1, 'admin_dashboard', 'welcome_text', '管理画面へようこそ。左のメニューから各管理機能をご利用ください。'), (2, 'admin_dashboard', 'welcome_text', 'Welcome to the admin panel. Please use the menu on the left to access management features.'),
(1, 'admin_dashboard', 'widget_total_bookings', '総予約数'), (2, 'admin_dashboard', 'widget_total_bookings', 'Total Bookings'),
(1, 'admin_dashboard', 'widget_total_bookings_note', 'キャンセルを除く'), (2, 'admin_dashboard', 'widget_total_bookings_note', 'excluding cancellations'),
(1, 'admin_dashboard', 'widget_total_rooms', '総部屋数'), (2, 'admin_dashboard', 'widget_total_rooms', 'Total Rooms'),
(1, 'admin_dashboard', 'widget_total_rooms_note', 'アクティブな部屋'), (2, 'admin_dashboard', 'widget_total_rooms_note', 'active rooms'),
(1, 'admin_dashboard', 'widget_total_users', '総顧客数'), (2, 'admin_dashboard', 'widget_total_users', 'Total Customers'),
(1, 'admin_dashboard', 'widget_total_users_note', 'アクティブな会員'), (2, 'admin_dashboard', 'widget_total_users_note', 'active members'),
(1, 'admin_dashboard', 'widget_recent_bookings', '最近の予約 (上位5件)'), (2, 'admin_dashboard', 'widget_recent_bookings', 'Recent Bookings (Top 5)'),
(1, 'admin_dashboard', 'col_id', 'ID'), (2, 'admin_dashboard', 'col_id', 'ID'),
(1, 'admin_dashboard', 'col_guest_name', 'ゲスト名'), (2, 'admin_dashboard', 'col_guest_name', 'Guest Name'),
(1, 'admin_dashboard', 'col_check_in', 'チェックイン'), (2, 'admin_dashboard', 'col_check_in', 'Check-in'),
(1, 'admin_dashboard', 'col_total_price', '合計金額'), (2, 'admin_dashboard', 'col_total_price', 'Total Price'),
(1, 'admin_dashboard', 'col_status', 'ステータス'), (2, 'admin_dashboard', 'col_status', 'Status'),
(1, 'admin_dashboard', 'no_recent_bookings', '最近の予約はありません。'), (2, 'admin_dashboard', 'no_recent_bookings', 'No recent bookings.'),
(1, 'admin_dashboard', 'error_data_fetch', 'ダッシュボードデータの取得に失敗しました。'),(2, 'admin_dashboard', 'error_data_fetch', 'Failed to fetch dashboard data.')
ON DUPLICATE KEY UPDATE `text` = VALUES(`text`);
