<?php
// 共通して使用する関数をここに定義します

// Composer autoload - PHPMailerなどのライブラリ読み込みに必要
// 実際のパスは環境によって異なる場合がある (例: __DIR__ . '/../../vendor/autoload.php')
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 例: データベース接続関数 (mysqli)
function get_db_connection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        // 本番環境ではより丁寧なエラー処理を
        error_log("データベース接続失敗: " . $conn->connect_error); // エラーログに変更
        die("データベース接続に問題が発生しました。管理者にご連絡ください。"); // 一般的なエラーメッセージ
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

// 空室検索関数
function search_available_rooms($conn, $check_in_date, $check_out_date, $num_adults) {
    $available_rooms = [];

    // 入力された日付をDateTimeオブジェクトに変換
    try {
        $check_in_dt = new DateTime($check_in_date);
        $check_out_dt = new DateTime($check_out_date);
    } catch (Exception $e) {
        return ['error' => '日付の形式が無効です。'];
    }

    // チェックアウト日はチェックイン日より後である必要がある
    if ($check_out_dt <= $check_in_dt) {
        return ['error' => 'チェックアウト日はチェックイン日より後の日付を選択してください。'];
    }
     // 昨日以前は不可 (時刻を無視して比較)
    if ($check_in_dt < new DateTime('today')) {
        return ['error' => 'チェックイン日には本日以降の日付を選択してください。'];
    }

    // 予約済みの日程と衝突する部屋を除外するSQL
    $sql_booked_rooms = "
        SELECT DISTINCT br.room_id
        FROM bookings b
        JOIN booking_rooms br ON b.id = br.booking_id
        WHERE b.status NOT IN ('cancelled', 'rejected') AND (
            (b.check_in_date < ? AND b.check_out_date > ?) OR
            (b.check_in_date >= ? AND b.check_in_date < ?) OR
            (b.check_out_date > ? AND b.check_out_date <= ?) OR
            (b.check_in_date <= ? AND b.check_out_date >= ?)
        )
    ";

    $sql_available_rooms = "
        SELECT r.id, r.name, r.price_per_night, r.capacity, rt.name as room_type_name
        FROM rooms r
        JOIN room_types rt ON r.room_type_id = rt.id
        WHERE r.is_active = TRUE AND r.capacity >= ?
    ";

    $stmt_booked = $conn->prepare($sql_booked_rooms);
    if (!$stmt_booked) {
        error_log("Prepare failed (booked_rooms): (" . $conn->errno . ") " . $conn->error);
        return ['error' => '検索クエリの準備に失敗しました。(booked)'];
    }
    // SQLには9つのプレースホルダがある (room_id用が1つ、日付比較用が8つ)
    // bind_paramの型指定は issssssss (iが1つ、sが8つ)
    $stmt_booked->bind_param("ssssssss", // This was "issssssss" in prev subtask, but SQL for search_available_rooms does not use room_id
        $check_out_date, $check_in_date,
        $check_in_date, $check_out_date,
        $check_in_date, $check_out_date,
        $check_in_date, $check_out_date
    );

    if (!$stmt_booked->execute()) {
        error_log("Execute failed (booked_rooms): (" . $stmt_booked->errno . ") " . $stmt_booked->error);
        $stmt_booked->close();
        return ['error' => '予約済み部屋の検索に失敗しました。'];
    }
    $result_booked = $stmt_booked->get_result();
    $booked_room_ids = [];
    while ($row = $result_booked->fetch_assoc()) {
        $booked_room_ids[] = $row['room_id'];
    }
    $stmt_booked->close();

    if (!empty($booked_room_ids)) {
        $placeholders = implode(',', array_fill(0, count($booked_room_ids), '?'));
        $sql_available_rooms .= " AND r.id NOT IN (" . $placeholders . ")";
    }

    $stmt_available = $conn->prepare($sql_available_rooms);
    if (!$stmt_available) {
        error_log("Prepare failed (available_rooms): (" . $conn->errno . ") " . $conn->error);
        return ['error' => '検索クエリの準備に失敗しました。(available)'];
    }

    $types = "i";
    $params = [$num_adults];
    if (!empty($booked_room_ids)) {
        foreach ($booked_room_ids as $id) { // Variable name changed from $room_id to $id for clarity
            $types .= "i";
            $params[] = $id;
        }
    }
    $stmt_available->bind_param($types, ...$params);

    if (!$stmt_available->execute()) {
        error_log("Execute failed (available_rooms): (" . $stmt_available->errno . ") " . $stmt_available->error);
        $stmt_available->close();
        return ['error' => '空室の検索に失敗しました。'];
    }
    $result_available = $stmt_available->get_result();
    while ($row = $result_available->fetch_assoc()) {
        $available_rooms[] = $row;
    }
    $stmt_available->close();

    return $available_rooms;
}

// PHPMailer を利用したメール送信関数
function send_booking_confirmation_email($booking_details, $room_details, $guest_email, $guest_name) {
    if (!defined('SMTP_HOST') || !defined('MAIL_FROM')) {
        error_log('メール設定が不完全です。config.phpを確認してください。');
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = SMTP::DEBUG_OFF; // 本番: DEBUG_OFF, 開発: DEBUG_SERVER or DEBUG_CLIENT
        if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = defined('SMTP_PORT') ? SMTP_PORT : 587;
        } else {
            $mail->isMail(); // PHP mail() function
        }

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(MAIL_FROM, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Hotel Booking System');
        $mail->addAddress($guest_email, $guest_name);
        $mail->addReplyTo(MAIL_FROM, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Hotel Booking System');

        $mail->isHTML(true);
        $mail->Subject = '【ホテル予約システム】ご予約ありがとうございます (予約番号: ' . h($booking_details['id']) . ')';

        $body  = "<p>" . h($guest_name) . " 様</p>";
        $body .= "<p>この度は、ホテル予約システムをご利用いただき、誠にありがとうございます。</p>";
        $body .= "<p>以下の内容でご予約を承りました。</p>";
        $body .= "<hr>";
        $body .= "<h3>ご予約内容</h3>";
        $body .= "<ul>";
        $body .= "<li><strong>予約番号:</strong> " . h($booking_details['id']) . "</li>";
        $body .= "<li><strong>ホテル名:</strong> ホテル XYZ (仮)</li>"; // Consider making hotel name configurable
        $body .= "<li><strong>お部屋タイプ:</strong> " . h($room_details['name']) . (isset($room_details['room_type_name']) ? " (" . h($room_details['room_type_name']) . ")" : "") . "</li>";
        $body .= "<li><strong>チェックイン日:</strong> " . h($booking_details['check_in_date']) . "</li>";
        $body .= "<li><strong>チェックアウト日:</strong> " . h($booking_details['check_out_date']) . "</li>";
        $body .= "<li><strong>ご利用人数:</strong> 大人 " . h($booking_details['num_adults']) . " 名様</li>";
        if (isset($booking_details['num_children']) && $booking_details['num_children'] > 0) {
           $body .= "<li><strong>お子様:</strong> " . h($booking_details['num_children']) . " 名様</li>";
        }
        $body .= "<li><strong>合計金額:</strong> &yen;" . h(number_format($booking_details['total_price'])) . " (税込み)</li>";
        $body .= "<li><strong>お支払い方法:</strong> 現地決済</li>"; // Consider making payment method dynamic
        $body .= "</ul>";
        if (!empty($booking_details['special_requests'])) {
            $body .= "<p><strong>ご要望:</strong><br>" . nl2br(h($booking_details['special_requests'])) . "</p>";
        }
        $body .= "<hr>";
        $body .= "<p>何かご不明な点がございましたら、お気軽にお問い合わせください。</p>";
        $body .= "<p>ホテル予約システム</p>"; // Consider using MAIL_FROM_NAME

        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace("<br>", "\r\n", $body)); // More robust plain text conversion

        $mail->send();
        error_log("予約確認メール送信成功: 予約ID {$booking_details['id']}, 送信先 {$guest_email}");
        return true;
    } catch (Exception $e) {
        error_log("予約確認メール送信失敗: 予約ID {$booking_details['id']}, 送信先 {$guest_email}. エラー: {$mail->ErrorInfo} - Exception: {$e->getMessage()}");
        return false;
    }
}
EOL
