<?php
require_once __DIR__ . '/../config/config.php'; // $twig もここで初期化
http_response_code(500);

$template_vars = [
    'page_title' => get_translation('error_page', '500_title', 'サーバーエラー'),
    'error_message_display' => '',
];

// エラー情報をログに記録（もしあれば）
// このページが表示される前にエラーログは取られているはずだが、追加情報があれば。
// $error_ref = $_GET['ref'] ?? 'unknown'; // 例: エラー参照ID
// error_log("Displayed 500 error page. Reference: {$error_ref}");

// Attempt to get error details if passed via session by a custom error handler
// This is a basic example; a more robust system might pass an error reference ID
if (DEBUG_MODE && isset($_SESSION['last_error_for_500'])) {
    $last_error = $_SESSION['last_error_for_500'];
    $details = "Error: " . h($last_error['message'] ?? 'N/A');
    if (isset($last_error['file'])) $details .= "\nFile: " . h($last_error['file']);
    if (isset($last_error['line'])) $details .= "\nLine: " . h($last_error['line']);
    // Stack trace can be very long, consider if it's useful here or just in logs
    // if (isset($last_error['trace'])) $details .= "\nTrace: " . h($last_error['trace']);
    $template_vars['error_message_display'] = nl2br($details);
    unset($_SESSION['last_error_for_500']); // Clear after displaying
} else {
    // In production, or if no specific details are available, show a generic message
    $template_vars['error_message_display'] = get_translation('error_page', '500_message_prod', 'サーバー内部でエラーが発生しました。しばらくしてから再度お試しいただくか、管理者にお問い合わせください。');
}


if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('500.html.twig', $template_vars);
    } catch (Exception $e) {
        error_log("Error rendering 500.html.twig: " . $e->getMessage());
        // Fallback for Twig rendering error on 500 page itself
        echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please try again later.</p>";
    }
} else {
    // Fallback if Twig is not available
    error_log("Twig not available for 500 page.");
    echo "<h1>500 Internal Server Error</h1><p>An unexpected error occurred. Please try again later.</p>";
}
?>
