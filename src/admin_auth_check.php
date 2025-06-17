<?php
// このファイルは config.php が既に読み込まれ、セッションが開始されている前提
if (session_status() == PHP_SESSION_NONE) {
    // 通常は config.php で session_start() されるが、念のため
    // ただし、config.php 以外で session_start() を呼ぶと二重開始の警告が出る可能性あり
    // なので、このファイルより前に必ず config.php を読む運用とする
    // session_start();
}

if (!isset($_SESSION['admin_id'])) {
    // adminディレクトリの深さを考慮して login.php へのパスを調整
    // このファイルが src にあるので、呼び出し元からの相対パスか、絶対パスで指定
    // 例: header('Location: /hotel_booking_system/public/admin/login.php');
    // SITE_URL を使うのが望ましい

    // 現在のスクリプトが admin ディレクトリ内にあるかどうかの判定が難しい
    // SITE_URL が config.php で定義されている前提でそれを使う
    $login_url = '';
    if (defined('SITE_URL')) {
        // SITE_URL の末尾に / があるかないかで調整
        $base_url = rtrim(SITE_URL, '/');
        // SITE_URLが 'http://localhost/hotel_booking_system' のようなドメインルートを指す場合
        // '/public/admin/login.php' を付加する必要があるかもしれない。
        // ここでは、SITE_URLが 'http://localhost/hotel_booking_system/public' のように
        // public ディレクトリを指しているか、あるいはWebサーバーのドキュメントルートが
        // public ディレクトリであることを想定する。
        if (strpos($base_url, '/public') !== false) {
             // SITE_URL includes /public, so just append /admin/login.php
            $login_url = $base_url . '/admin/login.php';
        } else {
            // SITE_URL does not include /public, assume it's domain root or similar
            // and /public is part of the web path.
            // This case is tricky without knowing web server setup.
            // A common setup is SITE_URL is the true base, and 'public' is part of the path.
            // However, for admin pages, they are often accessed via /admin, not /public/admin if public is doc root.
            // Assuming admin pages are directly under SITE_URL/admin/ if SITE_URL is http://host/
            // If SITE_URL is http://host/project/ then it would be SITE_URL/admin/
            // The original instruction mentioned `../css/style.css` from `public/admin/login.php`
            // implying `public` is part of the path from web root.
            // If `config.php` is in `project_root/config/` and `SITE_URL` is `http://host/project_root_url`,
            // then admin login is likely `http://host/project_root_url/public/admin/login.php`.
            // Given `require_once __DIR__ . '/../../config/config.php';` from `public/admin/login.php`,
            // `config.php` is at the project root.
            // So, `SITE_URL` likely points to `http://host/project_name` (without /public)
             $login_url = $base_url . '/public/admin/login.php';
        }
    } else {
        // SITE_URL が未定義の場合のフォールバック (adminディレクトリ内のファイルから呼ばれることを想定)
        $login_url = 'login.php';
    }

    header('Location: ' . $login_url . '?error=auth_required');
    exit;
}

// ここで管理者IDや権限に基づいた追加のチェックも可能
$current_admin_id = $_SESSION['admin_id'];
$current_admin_username = $_SESSION['admin_username'] ?? 'Admin';

// CSRFトークンもここで生成しておくと便利かもしれないが、各フォームで生成する方が一般的
// $admin_csrf_token = generate_csrf_token();
?>
