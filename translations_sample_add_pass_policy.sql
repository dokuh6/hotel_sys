-- Register Page - Password Policy Errors
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'register', 'error_password_minlength_8', 'パスワードは8文字以上で入力してください。'),
(2, 'register', 'error_password_minlength_8', 'Password must be at least 8 characters long.'),
(1, 'register', 'error_password_uppercase_required', 'パスワードには少なくとも1つの大文字を含めてください。'),
(2, 'register', 'error_password_uppercase_required', 'Password must contain at least one uppercase letter.'),
(1, 'register', 'error_password_lowercase_required', 'パスワードには少なくとも1つの小文字を含めてください。'),
(2, 'register', 'error_password_lowercase_required', 'Password must contain at least one lowercase letter.'),
(1, 'register', 'error_password_digit_required', 'パスワードには少なくとも1つの数字を含めてください。'),
(2, 'register', 'error_password_digit_required', 'Password must contain at least one digit.')
-- (1, 'register', 'error_password_symbol_required', 'パスワードには少なくとも1つの記号を含めてください。'),
-- (2, 'register', 'error_password_symbol_required', 'Password must contain at least one symbol.')
ON DUPLICATE KEY UPDATE `text` = VALUES(`text`);
