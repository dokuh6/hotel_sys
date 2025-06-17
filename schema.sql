-- データベース作成 (存在しない場合)
-- CREATE DATABASE IF NOT EXISTS hotel_booking_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE hotel_booking_system;

-- 言語マスタテーブル
CREATE TABLE IF NOT EXISTS `languages` (
    `id` TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(5) NOT NULL UNIQUE COMMENT '言語コード (例: ja, en)',
    `name` VARCHAR(50) NOT NULL COMMENT '言語名 (例: Japanese, English)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='対応言語マスタ';

-- 翻訳テキスト管理テーブル
CREATE TABLE IF NOT EXISTS `translations` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `language_id` TINYINT UNSIGNED NOT NULL COMMENT '言語ID',
    `group_key` VARCHAR(100) NOT NULL COMMENT '翻訳グループキー (例: common, room_details)',
    `item_key` VARCHAR(255) NOT NULL COMMENT '翻訳アイテムキー',
    `text` TEXT NOT NULL COMMENT '翻訳されたテキスト',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `lang_group_item` (`language_id`, `group_key`, `item_key`),
    FOREIGN KEY (`language_id`) REFERENCES `languages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='多言語翻訳テキスト';

-- 部屋タイプ管理テーブル
CREATE TABLE IF NOT EXISTS `room_types` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT '部屋タイプ名 (例: シングル, ダブル)',
    `description` TEXT NULL COMMENT '説明',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部屋タイプ';

-- 部屋情報テーブル
CREATE TABLE IF NOT EXISTS `rooms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `room_type_id` INT UNSIGNED NOT NULL COMMENT '部屋タイプID',
    `name` VARCHAR(255) NOT NULL COMMENT '部屋名・番号',
    `description` TEXT NULL COMMENT '部屋の詳細説明',
    `price_per_night` DECIMAL(10, 2) NOT NULL COMMENT '1泊あたりの価格',
    `capacity` TINYINT UNSIGNED NOT NULL COMMENT '最大収容人数',
    `image_path` VARCHAR(255) NULL COMMENT '部屋の画像パス',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT '公開状態 (true:公開, false:非公開)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`room_type_id`) REFERENCES `room_types`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='部屋情報';

-- 顧客情報テーブル (会員)
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT '氏名',
    `email` VARCHAR(255) NOT NULL UNIQUE COMMENT 'メールアドレス',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'ハッシュ化されたパスワード',
    `phone_number` VARCHAR(20) NULL COMMENT '電話番号',
    `address` TEXT NULL COMMENT '住所',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `email_verified_at` TIMESTAMP NULL COMMENT 'メールアドレス確認日時',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='顧客情報 (会員)';

-- 管理者情報テーブル
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '管理者ユーザー名',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'ハッシュ化されたパスワード',
    `email` VARCHAR(255) NOT NULL UNIQUE COMMENT '管理者メールアドレス',
    `name` VARCHAR(100) NULL COMMENT '氏名',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='管理者情報';

-- 予約情報テーブル
CREATE TABLE IF NOT EXISTS `bookings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL COMMENT '顧客ID (会員の場合)',
    `guest_name` VARCHAR(100) NOT NULL COMMENT '宿泊者名 (非会員または代表者)',
    `guest_email` VARCHAR(255) NOT NULL COMMENT '連絡先メールアドレス',
    `guest_phone` VARCHAR(20) NULL COMMENT '連絡先電話番号',
    `check_in_date` DATE NOT NULL COMMENT 'チェックイン日',
    `check_out_date` DATE NOT NULL COMMENT 'チェックアウト日',
    `num_adults` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '大人の人数',
    `num_children` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '子供の人数',
    `total_price` DECIMAL(12, 2) NOT NULL COMMENT '合計金額',
    `special_requests` TEXT NULL COMMENT '特記事項・要望',
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '予約ステータス (pending, confirmed, cancelled, completed)',
    `payment_status` VARCHAR(20) NOT NULL DEFAULT 'unpaid' COMMENT '支払い状況 (unpaid, paid, refunded)',
    `payment_method` VARCHAR(50) NULL COMMENT '支払い方法',
    `transaction_id` VARCHAR(255) NULL COMMENT '決済トランザクションID',
    `cancellation_reason` TEXT NULL COMMENT 'キャンセル理由',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    CONSTRAINT `chk_dates` CHECK (`check_out_date` > `check_in_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='予約情報';

-- 予約と部屋の関連テーブル (中間テーブル)
CREATE TABLE IF NOT EXISTS `booking_rooms` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `booking_id` INT UNSIGNED NOT NULL COMMENT '予約ID',
    `room_id` INT UNSIGNED NOT NULL COMMENT '部屋ID',
    `price_at_booking` DECIMAL(10, 2) NOT NULL COMMENT '予約時の部屋の価格',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`booking_id`) REFERENCES `bookings`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`room_id`) REFERENCES `rooms`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `booking_room` (`booking_id`, `room_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='予約された部屋';

-- 初期データ (任意)
-- languages
INSERT INTO `languages` (`code`, `name`) VALUES
('ja', '日本語'),
('en', 'English')
ON DUPLICATE KEY UPDATE `name`=`name`;

-- room_types (例)
INSERT INTO `room_types` (`name`, `description`) VALUES
('シングル', 'お一人様向けの標準的なお部屋です。'),
('ダブル', 'カップルやご夫婦向けのダブルベッドのお部屋です。'),
('ツイン', 'ご友人同士やご家族向けのベッド2台のお部屋です。'),
('スイート', '広々とした豪華なお部屋です。特別な滞在に。')
ON DUPLICATE KEY UPDATE `description`=`description`;

-- admins (テスト用管理者 - パスワードは 'adminpassword' をハッシュ化する必要あり)
-- INSERT INTO `admins` (`username`, `password_hash`, `email`, `name`) VALUES
-- ('admin', '$2y$10$...', 'admin@example.com', '管理者太郎');
-- 注意: 上記のパスワードハッシュはプレースホルダーです。実際のハッシュ値を設定してください。
-- 例: password_hash('adminpassword', PASSWORD_DEFAULT) で生成

EOL
