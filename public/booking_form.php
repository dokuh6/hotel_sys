<?php
require_once __DIR__ . '/../config/config.php';

// GETパラメータから予約情報を取得
$room_id = filter_input(INPUT_GET, 'room_id', FILTER_VALIDATE_INT);
$check_in_date_str = filter_input(INPUT_GET, 'check_in', FILTER_SANITIZE_STRING);
$check_out_date_str = filter_input(INPUT_GET, 'check_out', FILTER_SANITIZE_STRING);
$num_adults = filter_input(INPUT_GET, 'adults', FILTER_VALIDATE_INT);
// TODO: 子供の人数の処理も後で追加 $num_children = filter_input(INPUT_GET, 'children', FILTER_VALIDATE_INT);

$room = null;
$error_message = '';
$days = 0;
$total_price = 0;

// CSRFトークン生成
$csrf_token = generate_csrf_token(); // config.php で定義されている想定

if (!$room_id) {
    $error_message = '部屋が選択されていません。';
} else {
    $conn = null;
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT r.*, rt.name as room_type_name FROM rooms r JOIN room_types rt ON r.room_type_id = rt.id WHERE r.id = ? AND r.is_active = TRUE");
        if (!$stmt) throw new Exception("SQL prepare failed: " . $conn->error);
        $stmt->bind_param("i", $room_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $room = $result->fetch_assoc();

            // 日付と人数のバリデーション
            if (empty($check_in_date_str) || empty($check_out_date_str) || empty($num_adults)) {
                $error_message = 'チェックイン日、チェックアウト日、または人数が指定されていません。';
                $room = null; // 部屋情報があってもエラーとする
            } else {
                $check_in_date = new DateTime($check_in_date_str);
                $check_out_date = new DateTime($check_out_date_str);
                if ($check_out_date <= $check_in_date) {
                    $error_message = 'チェックアウト日はチェックイン日より後の日付にしてください。';
                    $room = null;
                } elseif ($check_in_date < new DateTime('today')) {
                     $error_message = 'チェックイン日には本日以降の日付を選択してください。';
                     $room = null;
                } elseif ($num_adults < 1 || $num_adults > $room['capacity']) {
                    $error_message = '指定された人数ではこの部屋タイプをご利用いただけません。(定員: ' . h($room['capacity']) . '名)';
                    $room = null;
                } else {
                    $interval = $check_in_date->diff($check_out_date);
                    $days = $interval->days;
                    if ($days <= 0) {
                        $error_message = '宿泊日数が無効です。';
                        $room = null;
                    } else {
                        $total_price = $days * $room['price_per_night'] * $num_adults; // 簡易的な料金計算 (人数も考慮)
                    }
                }
            }
        } else {
            $error_message = '指定された部屋が見つかりませんでした。';
        }
        if ($stmt) $stmt->close();
    } catch (Exception $e) {
        $error_message = '予約情報の取得中にエラーが発生しました: ' . h($e->getMessage());
        error_log("Booking Form Error: RoomID {$room_id} - " . $e->getMessage());
        $room = null;
    } finally {
        if ($conn) $conn->close();
    }
}

// POSTリクエスト処理 (予約実行)
$booking_success_message = '';
$booking_error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $room && !$error_message) {
    // CSRFトークン検証
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
        $booking_error_message = '無効なリクエストです。ページを再読み込みして再度お試しください。';
    } else {
        // フォームデータの取得とバリデーション
        $guest_name = trim($_POST['guest_name'] ?? '');
        $guest_email = trim($_POST['guest_email'] ?? '');
        $guest_phone = trim($_POST['guest_phone'] ?? '');
        $special_requests = trim($_POST['special_requests'] ?? '');

        // 簡単なバリデーション (詳細は後ほど強化)
        if (empty($guest_name) || empty($guest_email)) {
            $booking_error_message = '氏名とメールアドレスは必須です。';
        } elseif (!filter_var($guest_email, FILTER_VALIDATE_EMAIL)) {
            $booking_error_message = '有効なメールアドレスを入力してください。';
        } else {
            // --- ここからが実際の予約処理ロジック ---
            $conn = null;
            try {
                $conn = get_db_connection();
                $conn->begin_transaction();

                // 1. 最終空室確認 (指定された部屋ID、期間で)
                // SQLのプレースホルダは8個。room_id (i), check_out_date_str (s), check_in_date_str (s), check_in_date_str (s), check_out_date_str (s), check_in_date_str (s), check_out_date_str (s), check_in_date_str (s)
                // (b.check_in_date < P1 AND b.check_out_date > P2) OR (b.check_in_date >= P3 AND b.check_in_date < P4) OR (b.check_out_date > P5 AND b.check_out_date <= P6) OR (b.check_in_date <= P7 AND b.check_out_date >= P8)
                $stmt_check = $conn->prepare("
                    SELECT COUNT(*) as conflicts
                    FROM bookings b
                    JOIN booking_rooms br ON b.id = br.booking_id
                    WHERE br.room_id = ?
                      AND b.status NOT IN ('cancelled', 'rejected')
                      AND (
                          (b.check_in_date < ? AND b.check_out_date > ?) OR
                          (b.check_in_date >= ? AND b.check_in_date < ?) OR
                          (b.check_out_date > ? AND b.check_out_date <= ?) OR
                          (b.check_in_date <= ? AND b.check_out_date >= ?)
                      )
                ");
                if (!$stmt_check) throw new Exception("最終空室確認クエリ準備失敗: " . $conn->error);
                $stmt_check->bind_param("isssssss",
                    $room_id,                   // br.room_id = ?
                    $check_out_date_str,        // b.check_in_date < ?
                    $check_in_date_str,         // b.check_out_date > ?
                    $check_in_date_str,         // b.check_in_date >= ?
                    $check_out_date_str,        // b.check_in_date < ? (次の予約の開始日が、現在の検索の終了日より前)
                    $check_in_date_str,         // b.check_out_date > ? (次の予約の終了日が、現在の検索の開始日より後)
                    $check_out_date_str,        // b.check_out_date <= ?
                    $check_in_date_str          // b.check_in_date <= ? (最後のペアの最初の?、P7)
                                                // P8は $check_out_date_str (予約期間が検索期間を完全に含む場合の検索終了日)
                                                // SQLの最後のペアは (b.check_in_date <= $check_in_date_str AND b.check_out_date >= $check_out_date_str)
                                                // なので bind_param の最後は $check_out_date_str となるべき。
                                                // しかし、現状のSQLのプレースホルダは8つ。
                                                // (b.check_in_date <= ? AND b.check_out_date >= ?)  この?は $check_in_date_str と $check_out_date_str
                                                // 修正: P7=$check_in_date_str, P8=$check_out_date_str
                );
                // 正しいbind_paramは上記SQLに対して isssssss で8つのプレースホルダ
                // P1=$check_out_date_str, P2=$check_in_date_str
                // P3=$check_in_date_str, P4=$check_out_date_str
                // P5=$check_in_date_str, P6=$check_out_date_str
                // P7=$check_in_date_str, P8=$check_out_date_str
                // これで $stmt_check->bind_param("isssssss", $room_id, $check_out_date_str, $check_in_date_str, $check_in_date_str, $check_out_date_str, $check_in_date_str, $check_out_date_str, $check_in_date_str, $check_out_date_str);
                // だと変数が9つ。
                // SQLのプレースホルダに対応する変数は8つ。$room_id, $P1, $P2, $P3, $P4, $P5, $P6, $P7.
                // $P8に対応する $check_out_date_str がbind_paramに必要。
                // 最後のペア (b.check_in_date <= ? AND b.check_out_date >= ?) は $check_in_date_str と $check_out_date_str
                // したがって、bind_paramは issssssss で $room_id, $P1, $P2, $P3, $P4, $P5, $P6, $P7, $P8
                // $P1 = $check_out_date_str, $P2 = $check_in_date_str
                // $P3 = $check_in_date_str,  $P4 = $check_out_date_str
                // $P5 = $check_in_date_str,  $P6 = $check_out_date_str
                // $P7 = $check_in_date_str,  $P8 = $check_out_date_str
                // $stmt_check->bind_param("issssssss", // 9 params
                //     $room_id,
                //     $check_out_date_str, $check_in_date_str,
                //     $check_in_date_str, $check_out_date_str,
                //     $check_in_date_str, $check_out_date_str,
                //     $check_in_date_str, $check_out_date_str
                // );
                // 上記のコメントアウトされたbind_paramが正しい。SQLのプレースホルダーは8つ。
                // 最初の? = $room_id (i)
                // 次の7つの? = s,s,s,s,s,s,s
                // (b.check_in_date < CO AND b.check_out_date > CI) OR (b.check_in_date >= CI AND b.check_in_date < CO) OR (b.check_out_date > CI AND b.check_out_date <= CO) OR (b.check_in_date <= CI AND b.check_out_date >= CO)
                // CO, CI, CI, CO, CI, CO, CI, CO (8つの日付パラメータ)
                 $stmt_check->bind_param("issssssss", //9つのプレースホルダのはずだがSQLは8つ。
                    $room_id,
                    $check_out_date_str, $check_in_date_str,
                    $check_in_date_str, $check_out_date_str,
                    $check_in_date_str, $check_out_date_str,
                    $check_in_date_str, $check_out_date_str // この最後のペアは (b.check_in_date <= ? AND b.check_out_date >= ?) に対応
                );

                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                $conflicts = $result_check->fetch_assoc()['conflicts'];
                $stmt_check->close();

                if ($conflicts > 0) {
                    throw new Exception("申し訳ございません、他のお客様が先に予約を完了されたため、ご希望の日程ではご予約いただけません。再度検索してお部屋をお選びください。");
                }

                // 2. bookings テーブルに保存
                $booking_status = 'confirmed'; // または 'pending' など状況に応じて
                $payment_status = 'unpaid';   // 現地決済なので
                $total_price_from_form = filter_input(INPUT_POST, 'total_price_val', FILTER_VALIDATE_FLOAT);

                $stmt_booking = $conn->prepare("
                    INSERT INTO bookings (user_id, guest_name, guest_email, guest_phone,
                                        check_in_date, check_out_date, num_adults, /* num_children, */
                                        total_price, special_requests, status, payment_status, created_at, updated_at)
                    VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                if (!$stmt_booking) throw new Exception("予約情報保存クエリ準備失敗: " . $conn->error);
                $stmt_booking->bind_param("sssssisdss", // total_price is decimal (d)
                    $guest_name, $guest_email, $guest_phone,
                    $check_in_date_str, $check_out_date_str, $num_adults,
                    $total_price_from_form, $special_requests, $booking_status, $payment_status
                );
                $stmt_booking->execute();
                $new_booking_id = $conn->insert_id;
                if (!$new_booking_id) {
                    throw new Exception("予約情報の保存に失敗しました。IDが取得できませんでした。");
                }
                $stmt_booking->close();

                // 3. booking_rooms テーブルに保存
                $price_at_booking = $room['price_per_night'];
                $stmt_booking_room = $conn->prepare("
                    INSERT INTO booking_rooms (booking_id, room_id, price_at_booking, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                if (!$stmt_booking_room) throw new Exception("予約部屋関連保存クエリ準備失敗: " . $conn->error);
                $stmt_booking_room->bind_param("iid", $new_booking_id, $room_id, $price_at_booking);
                $stmt_booking_room->execute();
                if ($stmt_booking_room->affected_rows === 0) {
                    throw new Exception("予約部屋関連の保存に失敗しました。");
                }
                $stmt_booking_room->close();

                $conn->commit();

                // メール送信処理
                $booking_details_for_mail = [
                    'id' => $new_booking_id,
                    'check_in_date' => $check_in_date_str,
                    'check_out_date' => $check_out_date_str,
                    'num_adults' => $num_adults,
                    // 'num_children' => $num_children, // Retrieve from POST if implemented
                    'total_price' => $total_price_from_form,
                    'special_requests' => $special_requests,
                ];
                // $room variable should still hold the room details fetched at the beginning of the script
                if (isset($room) && $room !== null) {
                    if(send_booking_confirmation_email($booking_details_for_mail, $room, $guest_email, $guest_name)) {
                        $booking_success_message = "ご予約が完了しました！予約番号: " . $new_booking_id . "。確認メールを送信しました。";
                    } else {
                        $booking_success_message = "ご予約は完了しましたが、確認メールの送信に失敗しました。予約番号: " . $new_booking_id . "。ホテルにお問い合わせください。";
                        error_log("予約完了後メール送信失敗: 予約ID {$new_booking_id}");
                    }
                } else {
                     error_log("メール送信失敗: \$room変数が未定義またはNULL。予約ID: {$new_booking_id}");
                     $booking_success_message = "ご予約は完了しましたが、部屋情報が取得できず確認メールの送信に失敗しました。予約番号: " . $new_booking_id . "。ホテルにお問い合わせください。";
                }

            } catch (Exception $e) {
                if ($conn) $conn->rollback();
                $booking_error_message = "予約処理中にエラーが発生しました: " . h($e->getMessage());
                error_log("Booking Process Error: RoomID {$room_id}, Guest {$guest_email} - " . $e->getMessage());
            } finally {
                if ($conn) $conn->close();
            }
            // --- ここまでが実際の予約処理ロジック ---
        }
    }
    // 新しいCSRFトークンを生成してフォームに再設定（エラーで再表示の場合）
    // または成功時も新しいトークンを生成して、再投稿を防ぐ
    $csrf_token = generate_csrf_token();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>予約手続き - ホテル予約システム</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header>
        <nav>
            <a href="index.php">空室検索に戻る</a>
            <?php if ($room_id): ?>
            | <a href="room_details.php?id=<?= h($room_id) ?>&check_in=<?=h($check_in_date_str)?>&check_out=<?=h($check_out_date_str)?>&adults=<?=h($num_adults)?>">部屋詳細に戻る</a>
            <?php endif; ?>
        </nav>
    </header>

    <main>
        <h2>予約手続き</h2>

        <?php if ($error_message): ?>
            <p style="color: red;"><?= h($error_message) ?></p>
            <p><a href="index.php">再度検索する</a></p>
        <?php elseif ($room): ?>
            <h3>予約内容の確認</h3>
            <p><strong>部屋タイプ:</strong> <?= h($room['name']) ?> (<?= h($room['room_type_name']) ?>)</p>
            <p><strong>チェックイン日:</strong> <?= h($check_in_date_str) ?></p>
            <p><strong>チェックアウト日:</strong> <?= h($check_out_date_str) ?></p>
            <p><strong>宿泊日数:</strong> <?= h($days) ?> 泊</p>
            <p><strong>ご利用人数:</strong> 大人 <?= h($num_adults) ?> 名</p>
            <p><strong>予定合計金額:</strong> &yen;<?= h(number_format($total_price)) ?> (税込み)</p>
            <hr>

            <?php if ($booking_success_message): ?>
                <p style="color: green;"><?= h($booking_success_message) ?></p>
                <p><a href="index.php">トップページに戻る</a></p>
            <?php else: ?>
                <h3>お客様情報入力</h3>
                <?php if ($booking_error_message): ?>
                    <p style="color: red;"><?= h($booking_error_message) ?></p>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
                    <input type="hidden" name="room_id" value="<?= h($room_id) ?>">
                    <input type="hidden" name="check_in" value="<?= h($check_in_date_str) ?>">
                    <input type="hidden" name="check_out" value="<?= h($check_out_date_str) ?>">
                    <input type="hidden" name="adults" value="<?= h($num_adults) ?>">
                    <input type="hidden" name="total_price_val" value="<?= h($total_price) ?>">


                    <div>
                        <label for="guest_name">氏名 <span style="color:red;">*</span>:</label>
                        <input type="text" id="guest_name" name="guest_name" value="<?= h($_POST['guest_name'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label for="guest_email">メールアドレス <span style="color:red;">*</span>:</label>
                        <input type="email" id="guest_email" name="guest_email" value="<?= h($_POST['guest_email'] ?? '') ?>" required>
                    </div>
                    <div>
                        <label for="guest_phone">電話番号:</label>
                        <input type="tel" id="guest_phone" name="guest_phone" value="<?= h($_POST['guest_phone'] ?? '') ?>">
                    </div>
                    <div>
                        <label for="special_requests">備考・ご要望など:</label>
                        <textarea id="special_requests" name="special_requests" rows="4"><?= h($_POST['special_requests'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <p>お支払い方法: 現地決済 (現在はこれのみ選択可能です)</p>
                    </div>
                    <div>
                        <button type="submit">この内容で予約する</button>
                    </div>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <p>予約処理を開始できませんでした。お手数ですが、再度空室検索からお試しください。</p>
            <p><a href="index.php">空室検索ページへ</a></p>
        <?php endif; ?>
    </main>

    <footer>
        <p>&copy; <?= date('Y') ?> ホテル予約システム</p>
    </footer>
</body>
</html>
