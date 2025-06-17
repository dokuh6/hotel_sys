# ホテル予約システム

これはPHPで構築されたホテル予約システムです。

## 要件

（ここに課題の要件を貼り付ける）

## セットアップ

1.  このリポジトリをクローンします。
2.  `config/config.php` を環境に合わせて編集します。
3.  データベースを作成し、`config.php` に接続情報を設定します。
4.  Composer を使用して依存ライブラリをインストールします (`composer install`)。
5.  Webサーバー（Apache, Nginxなど）を設定し、`public` ディレクトリをドキュメントルートに向けます。

## ディレクトリ構造 (予定)

-   `config/`: 設定ファイル
-   `public/`: 公開ディレクトリ、フロントコントローラ (index.php)
    -   `css/`: CSSファイル
    -   `js/`: JavaScriptファイル
-   `src/`: PHPソースコード (コントローラ、モデル、ヘルパー関数など)
-   `templates/`: Twigテンプレートファイル
-   `vendor/`: Composerで管理される依存ライブラリ
-   `cache/`: キャッシュファイル (Twigなど)
-   `tests/`: ユニットテスト、機能テスト

## 使用技術 (予定)

-   PHP
-   MySQL
-   Twig (テンプレートエンジン)
-   PHPMailer (メール送信)
-   HTML, CSS, JavaScript
EOL
