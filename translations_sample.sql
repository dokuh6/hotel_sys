-- languages テーブルに ja と en が存在することを確認 (IDが1と2と仮定)
-- INSERT IGNORE INTO languages (id, code, name) VALUES (1, 'ja', '日本語'), (2, 'en', 'English');

-- translations テーブルへのサンプルデータ
-- Common UI elements
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'common', 'search', '検索'),
(2, 'common', 'search', 'Search'),
(1, 'common', 'submit', '送信'),
(2, 'common', 'submit', 'Submit'),
(1, 'common', 'login', 'ログイン'),
(2, 'common', 'login', 'Login'),
(1, 'common', 'register', '会員登録'),
(2, 'common', 'register', 'Register'),
(1, 'common', 'logout', 'ログアウト'),
(2, 'common', 'logout', 'Logout'),
(1, 'common', 'mypage', 'マイページ'),
(2, 'common', 'mypage', 'My Page'),
(1, 'common', 'home', 'ホーム'),
(2, 'common', 'home', 'Home'),
(1, 'common', 'yes', 'はい'),
(2, 'common', 'yes', 'Yes'),
(1, 'common', 'no', 'いいえ'),
(2, 'common', 'no', 'No'),
(1, 'common', 'cancel', 'キャンセル'),
(2, 'common', 'cancel', 'Cancel'),
(1, 'common', 'edit', '編集'),
(2, 'common', 'edit', 'Edit'),
(1, 'common', 'delete', '削除'),
(2, 'common', 'delete', 'Delete'),
(1, 'common', 'confirm_action', 'この操作を実行してもよろしいですか？'),
(2, 'common', 'confirm_action', 'Are you sure you want to perform this action?'),
(1, 'common', 'hotel_booking_system', 'ホテル予約システム'),
(2, 'common', 'hotel_booking_system', 'Hotel Booking System');

-- Index Page (Room Search)
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'index', 'page_title', '空室検索 - ホテル予約システム'),
(2, 'index', 'page_title', 'Room Search - Hotel Booking System'),
(1, 'index', 'search_heading', '空室検索'),
(2, 'index', 'search_heading', 'Search for Available Rooms'),
(1, 'index', 'check_in_date_label', 'チェックイン日:'),
(2, 'index', 'check_in_date_label', 'Check-in Date:'),
(1, 'index', 'check_out_date_label', 'チェックアウト日:'),
(2, 'index', 'check_out_date_label', 'Check-out Date:'),
(1, 'index', 'num_adults_label', '大人の人数:'),
(2, 'index', 'num_adults_label', 'Number of Adults:'),
(1, 'index', 'search_results_heading', '検索結果'),
(2, 'index', 'search_results_heading', 'Search Results'),
(1, 'index', 'no_rooms_found', '空室が見つかりませんでした。'),
(2, 'index', 'no_rooms_found', 'No available rooms found for the selected criteria.'),
(1, 'index', 'book_this_room_link', 'この部屋を予約する（仮）'),
(2, 'index', 'book_this_room_link', 'Book this room (temp)');

-- Login Page
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'login', 'page_title', 'ログイン - ホテル予約システム'),
(2, 'login', 'page_title', 'Login - Hotel Booking System'),
(1, 'login', 'email_label', 'メールアドレス:'),
(2, 'login', 'email_label', 'Email Address:'),
(1, 'login', 'password_label', 'パスワード:'),
(2, 'login', 'password_label', 'Password:'),
(1, 'login', 'forgot_password_link', 'パスワードをお忘れですか？'),
(2, 'login', 'forgot_password_link', 'Forgot your password?');

-- Register Page
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'register', 'page_title', '会員登録 - ホテル予約システム'),
(2, 'register', 'page_title', 'Register - Hotel Booking System'),
(1, 'register', 'name_label', '氏名'),
(2, 'register', 'name_label', 'Name'),
(1, 'register', 'password_confirm_label', 'パスワード (確認用)'),
(2, 'register', 'password_confirm_label', 'Password (Confirm)'),
(1, 'register', 'agree_terms_label', '利用規約に同意する'),
(2, 'register', 'agree_terms_label', 'I agree to the terms of service'),
(1, 'register', 'terms_link_placeholder', '利用規約のページへのリンクをここに設置'),
(2, 'register', 'terms_link_placeholder', '(Link to Terms of Service page here)');

ON DUPLICATE KEY UPDATE `text` = VALUES(`text`);
