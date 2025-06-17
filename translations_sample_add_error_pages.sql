-- Error Pages
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'error_page', '404_title', 'ページが見つかりません'), (2, 'error_page', '404_title', 'Page Not Found'),
(1, 'error_page', '404_heading', '404 - ページが見つかりません'), (2, 'error_page', '404_heading', '404 - Page Not Found'),
(1, 'error_page', '404_message', 'お探しのページは見つかりませんでした。URLが正しいかご確認ください。'), (2, 'error_page', '404_message', 'The page you are looking for could not be found. Please check the URL.'),
(1, 'error_page', '404_back_link', 'トップページに戻る'), (2, 'error_page', '404_back_link', 'Back to Homepage'),
(1, 'error_page', '500_title', 'サーバーエラー'), (2, 'error_page', '500_title', 'Server Error'),
(1, 'error_page', '500_heading', '500 - サーバー内部エラー'), (2, 'error_page', '500_heading', '500 - Internal Server Error'),
(1, 'error_page', '500_message', '申し訳ございません。システム内部で予期せぬエラーが発生しました。'), (2, 'error_page', '500_message', 'We are sorry, but an unexpected internal error has occurred.'),
(1, 'error_page', '500_details_heading', 'エラー詳細 (開発モード時):'), (2, 'error_page', '500_details_heading', 'Error Details (Development Mode):'),
(1, 'error_page', '500_message_prod', 'サーバー内部でエラーが発生しました。しばらくしてから再度お試しいただくか、管理者にお問い合わせください。'), (2, 'error_page', '500_message_prod', 'An internal server error occurred. Please try again later or contact the administrator.'),
(1, 'error_page', '500_back_link', 'トップページに戻る'), (2, 'error_page', '500_back_link', 'Back to Homepage')
ON DUPLICATE KEY UPDATE `text` = VALUES(`text`);

-- Booking form specific error messages used in the "DEBUG_MODE" example
INSERT INTO translations (language_id, group_key, item_key, `text`) VALUES
(1, 'booking_form', 'error_booking_exception_debug', '予約処理エラー(D): %msg%'), (2, 'booking_form', 'error_booking_exception_debug', 'Booking Processing Error (D): %msg%'),
(1, 'booking_form', 'error_booking_exception_prod', '予約処理中に予期せぬエラーが発生しました。サポートにお問い合わせください。'), (2, 'booking_form', 'error_booking_exception_prod', 'An unexpected error occurred during booking. Please contact support.')
ON DUPLICATE KEY UPDATE `text` = VALUES(`text`);
