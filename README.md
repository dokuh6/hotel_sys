# ホテル予約システム

これはPHP、MySQL、Twig、PHPMailerを使用して構築された多言語対応のホテル予約システムです。

## 1. システム概要

本システムは、顧客向けの部屋検索・予約機能と、管理者向けの予約・部屋・部屋タイプ管理機能を提供します。

### 1.1. 主要機能

**顧客向け機能:**
*   空室検索（日付・人数）
*   部屋一覧・詳細表示
*   オンライン予約処理（ゲスト予約・会員予約）
*   予約確認メールの自動送信
*   ユーザー認証（会員登録、ログイン、ログアウト）
*   マイページ（予約履歴の確認・キャンセル）
*   多言語対応（日本語・英語）

**管理者向け機能:**
*   管理者専用ログイン・ログアウト
*   ダッシュボード（予約・部屋の統計概要表示）
*   予約管理（一覧表示、フィルタリング、詳細編集、キャンセル）
*   部屋管理（一覧表示、登録、編集、公開/非公開設定）
*   部屋タイプ管理（一覧表示、登録、編集、削除（使用状況確認付き））

## 2. 使用技術

*   **PHP**: 7.4 以降推奨 (開発時PHP 8.x 想定)
*   **MySQL**: 5.7 以降推奨 (MariaDBも可)
*   **Twig**: ^3.0 (テンプレートエンジン)
*   **PHPMailer**: ^6.0 (メール送信用ライブラリ)
*   **Webサーバー**: Apache (mod_rewrite利用) または Nginx (設定別途必要)
*   **Composer**: 依存関係管理
*   HTML, CSS, JavaScript

## 3. ディレクトリ構造 (主要部分)

-   `cache/twig/`: Twigのコンパイル済みテンプレートキャッシュ。書き込み権限が必要。
-   `config/`: 設定ファイル。
    -   `config.php`: データベース接続情報、SMTP設定、サイトURLなど。
-   `public/`: Webサーバーのドキュメントルート。フロントコントローラー(`index.php`)や静的アセット(CSS, JS, images)を配置。
    -   `admin/`: 管理者向けページのスクリプト。
    -   `css/`: CSSファイル。
    -   `js/`: JavaScriptファイル。
    -   `images/`: 画像ファイル。
    -   `.htaccess`: URLリライトルール (Apache用)。
-   `src/`: PHPのコアロジック。
    -   `functions.php`: 共通関数（DB接続、翻訳、メール送信など）。
    -   `admin_auth_check.php`: 管理者ページの認証チェック。
-   `templates/`: Twigテンプレートファイル。
    -   `admin/`: 管理者ページ用テンプレート。
    -   `_flash_messages.html.twig`: 管理者画面での共通メッセージ表示用。
    -   `base.html.twig`: 顧客向けページのベースレイアウト。
    -   `404.html.twig`, `500.html.twig`: カスタムエラーページ。
-   `translations_sample*.sql`: 多言語対応のためのサンプル翻訳データ。
-   `schema.sql`: データベーススキーマ定義。
-   `vendor/`: Composerによってインストールされたライブラリ。

## 4. セットアップ・インストール手順

1.  **リポジトリのクローン**:
    `git clone <リポジトリURL> hotel_booking_system`
    `cd hotel_booking_system`

2.  **Composer依存関係のインストール**:
    プロジェクトルートで以下のコマンドを実行します。
    `composer install`
    これにより `vendor` ディレクトリが作成され、TwigやPHPMailerなどがインストールされます。

3.  **データベースのセットアップ**:
    *   MySQLサーバーにデータベースを作成します (例: `hotel_booking_system`)。文字コードは `utf8mb4` を推奨します。
    *   `schema.sql` ファイルをインポートしてテーブルを作成します。
        `mysql -u <username> -p <database_name> < schema.sql`
    *   サンプル翻訳データをインポートします。複数の `translations_sample_*.sql` ファイルが存在する場合、全てインポートしてください。
        `mysql -u <username> -p <database_name> < translations_sample.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_index.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_auth.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_user_pages.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_admin.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_admin_bookings.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_admin_rooms.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_admin_room_types.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_pass_policy.sql`
        `mysql -u <username> -p <database_name> < translations_sample_add_error_pages.sql`
        (ファイル名は実際の提供物に合わせてください)

4.  **設定ファイルの準備**:
    *   `config/config.php.example` (もしあれば) を `config/config.php` にコピーします。
    *   `config/config.php` を開き、以下の情報をご自身の環境に合わせて編集します:
        *   データベース接続情報: `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
        *   サイトURL: `SITE_URL` (例: `http://localhost/hotel_booking_system/public`)。末尾にスラッシュは含めないでください。
        *   デバッグモード: `DEBUG_MODE` (`true` または `false`)
        *   メール設定 (PHPMailer): `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_PORT`, `MAIL_FROM`, `MAIL_FROM_NAME`
        *   Twigテンプレートディレクトリ: `TWIG_TEMPLATE_DIR` (通常は `__DIR__ . '/../templates'`)
        *   Twigキャッシュディレクトリ: `TWIG_CACHE_DIR` (通常は `__DIR__ . '/../cache/twig'`)。このディレクトリにWebサーバーからの書き込み権限が必要です。

5.  **Webサーバーの設定**:
    *   ドキュメントルートをプロジェクト内の `public` ディレクトリに設定します。
    *   **Apacheの場合**:
        *   `mod_rewrite` モジュールを有効にしてください。
        *   `public` ディレクトリ内の `.htaccess` ファイルが読み込まれるように、Apacheの設定で `AllowOverride All` (または適切な値) をドキュメントルートに対して許可してください。
    *   **Nginxの場合**:
        *   適切なリライトルールを設定して、全てのリクエスト (実在ファイルを除く) を `public/index.php` に転送するようにしてください。設定例:
          ```nginx
          location / {
              try_files $uri $uri/ /index.php?$query_string;
          }
          location ~ \.php$ {
              # ... (fastcgi_pass などのPHP設定)
          }
          ```

6.  **ディレクトリ権限**:
    *   `cache/twig` ディレクトリにWebサーバープロセスからの書き込み権限を与えてください。
    *   エラーログファイルが指定されている場合、そのファイルまたはディレクトリにも書き込み権限が必要です。

## 5. 初期管理者アカウント

システムインストール後、管理者アカウントは手動でデータベースに登録する必要があります。
以下のSQL例を参考に、`admins` テーブルにレコードを挿入してください。パスワードは必ずハッシュ化してください。

```sql
-- 例: ユーザー名 'admin', パスワード 'password123' (ハッシュ化が必要)
-- PHPで echo password_hash('password123', PASSWORD_DEFAULT); を実行してハッシュ値を取得
INSERT INTO admins (username, email, password_hash, name, is_active, created_at, updated_at)
VALUES ('admin', 'admin@example.com', '$2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx', '管理者ユーザー', TRUE, NOW(), NOW());
```
(`$2y$10$...` の部分は実際に生成されたハッシュ値に置き換えてください)

## 6. 多言語対応

*   **対応言語**: 現在、日本語 (ja) と英語 (en) に対応しています。
*   **言語の切り替え**: サイトヘッダーの言語スイッチャーから変更できます。言語設定はセッションに保存されます。
*   **翻訳の管理**:
    *   言語マスタは `languages` テーブルで管理されます。
    *   翻訳テキストは `translations` テーブルで、`language_id`, `group_key` (例: 'common', 'index'), `item_key` (例: 'login_button', 'welcome_message') の組み合わせで管理されます。
*   **Twigテンプレートでの使用**: `{{ t('group_key', 'item_key', 'フォールバックテキスト') }}` 関数を使用します。
*   **PHPでの使用**: `get_translation('group_key', 'item_key', 'フォールバックテキスト')` 関数を使用します。

## 7. 注意事項・その他

*   **メール送信**: 予約確認メールなどの送信には、`config/config.php` で正しいSMTPサーバー情報の設定が必要です。ローカル開発環境では MailHog や Mailtrap などのツールを利用すると便利です。
*   **エラーログ**: エラーはWebサーバーのエラーログ、またはPHPの設定で指定されたログファイルに出力されます。`DEBUG_MODE = false` の本番環境では、こちらを確認してください。
*   **セキュリティ**: 基本的なセキュリティ対策（XSS, CSRF, SQLインジェクション防止）は施されていますが、公開前に専門家による診断を推奨します。

---
この `README.md` は基本的な情報を提供します。必要に応じて追記・修正してください。
