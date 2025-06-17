<?php
require_once __DIR__ . '/../config/config.php'; // $twig もここで初期化
http_response_code(404);

$template_vars = [
    'page_title' => get_translation('error_page', '404_title', 'ページが見つかりません'),
    // base.html.twig で必要な他の変数を設定 (例: active_menu は空など)
];

if (isset($twig) && $twig instanceof \Twig\Environment) {
    try {
        echo $twig->render('404.html.twig', $template_vars);
    } catch (Exception $e) {
        // Twigレンダリングエラーの場合は簡素なフォールバック
        error_log("Error rendering 404.html.twig: " . $e->getMessage());
        echo "<h1>404 Not Found</h1><p>The page you are looking for could not be found.</p><p><a href=\"/\">Go to Homepage</a></p>";
    }
} else {
    // Twigが利用できない場合のフォールバック
    error_log("Twig not available for 404 page.");
    echo "<h1>404 Not Found</h1><p>The page you are looking for could not be found.</p><p><a href=\"/\">Go to Homepage</a></p>";
}
?>
