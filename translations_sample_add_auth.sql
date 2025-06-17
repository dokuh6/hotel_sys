-- Common Error Messages
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'common', 'error_csrf', '無効なリクエストです。'),
(2, 'common', 'error_csrf', 'Invalid request. Please try again.'),
(1, 'common', 'unimplemented', '未実装'),
(2, 'common', 'unimplemented', 'Not implemented'),
(1, 'common', 'login_link_text', 'ログインページへ'),
(2, 'common', 'login_link_text', 'Go to Login Page');

-- Login Page Errors
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'login', 'error_email_required', 'メールアドレスを入力してください。'),
(2, 'login', 'error_email_required', 'Please enter your email address.'),
(1, 'login', 'error_email_invalid', '有効なメールアドレスを入力してください。'),
(2, 'login', 'error_email_invalid', 'Please enter a valid email address.'),
(1, 'login', 'error_password_required', 'パスワードを入力してください。'),
(2, 'login', 'error_password_required', 'Please enter your password.'),
(1, 'login', 'error_db_prepare_failed', 'データベースエラーが発生しました。'),
(2, 'login', 'error_db_prepare_failed', 'A database error occurred.'),
(1, 'login', 'error_auth_failed', 'メールアドレスまたはパスワードが正しくありません。'),
(2, 'login', 'error_auth_failed', 'Incorrect email address or password.'),
(1, 'login', 'error_login_exception', 'ログイン処理中にエラーが発生しました。'),
(2, 'login', 'error_login_exception', 'An error occurred during the login process.');

-- Register Page Errors and Messages
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'register', 'error_name_required', '氏名は必須です。'),
(2, 'register', 'error_name_required', 'Name is required.'),
(1, 'register', 'error_email_required', 'メールアドレスは必須です。'), /* Duplicate with login key, but can be page specific if needed */
(2, 'register', 'error_email_required', 'Email address is required.'),
(1, 'register', 'error_email_invalid', '有効なメールアドレスを入力してください。'), /* Duplicate with login key */
(2, 'register', 'error_email_invalid', 'Please enter a valid email address.'),
(1, 'register', 'error_password_required', 'パスワードは必須です。'), /* Duplicate with login key */
(2, 'register', 'error_password_required', 'Password is required.'),
(1, 'register', 'error_password_minlength', 'パスワードは8文字以上で入力してください。'),
(2, 'register', 'error_password_minlength', 'Password must be at least 8 characters long.'),
(1, 'register', 'error_password_mismatch', 'パスワードと確認用パスワードが一致しません。'),
(2, 'register', 'error_password_mismatch', 'Password and confirmation password do not match.'),
(1, 'register', 'error_agree_terms_required', '利用規約への同意が必要です。'),
(2, 'register', 'error_agree_terms_required', 'You must agree to the terms of service.'),
(1, 'register', 'error_db_prepare_failed', 'データベースエラーが発生しました。'), /* Duplicate with login key */
(2, 'register', 'error_db_prepare_failed', 'A database error occurred.'),
(1, 'register', 'error_email_exists', 'このメールアドレスは既に使用されています。'),
(2, 'register', 'error_email_exists', 'This email address is already in use.'),
(1, 'register', 'error_password_hash_failed', 'パスワードの処理に失敗しました。'),
(2, 'register', 'error_password_hash_failed', 'Password processing failed.'),
(1, 'register', 'error_db_insert_failed', 'データベースエラーが発生しました。'), /* Duplicate with login key */
(2, 'register', 'error_db_insert_failed', 'A database error occurred.'),
(1, 'register', 'error_db_insert_failed_detail', 'ユーザー情報の保存に失敗しました。'),
(2, 'register', 'error_db_insert_failed_detail', 'Failed to save user information.'),
(1, 'register', 'error_registration_exception', '登録処理中にエラーが発生しました。'),
(2, 'register', 'error_registration_exception', 'An error occurred during the registration process.'),
(1, 'register', 'success_registration', '会員登録が完了しました。ログインページからログインしてください。'),
(2, 'register', 'success_registration', 'Registration complete. Please log in from the login page.'),
(1, 'register', 'password_min_length_note', '8文字以上'),
(2, 'register', 'password_min_length_note', 'min. 8 characters');

ON DUPLICATE KEY UPDATE `text` = VALUES(`text`);
