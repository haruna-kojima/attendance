# アプリケーション名

COACHTECH 勤怠管理アプリ

# 概要

会員登録したユーザーは勤怠の打刻、勤怠状況の閲覧、打刻修正の申請ができる。
管理者はユーザーの勤怠状況、詳細、申請内容の閲覧・承認ができる。
   
## 環境構築手順

1. **リポジトリをクローン**
````bash
    git clone https://github.com/haruna-kojima/attendance.git
    cd attendance

    ※ MySQLは、OSによって起動しない場合があるのでそれぞれのPCに合わせて docker-compose.ymlファイルを編集してください。
````

2. **.env.exampleの中身を確認**

データベース接続情報が以下と一致していることを確認します。
````bash
    DB_CONNECTION=mysql
    DB_HOST=mysql
    DB_PORT=3306
    DB_DATABASE=laravel
    DB_USERNAME=sail
    DB_PASSWORD=password

    MAIL_MAILER=smtp
    MAIL_HOST=mailpit
    MAIL_PORT=1025
````

3. **.envファイルの準備**
````bash
    cp .env.example .env
````
4. **Composer依存パッケージのインストール**

````bash
    docker run --rm \
        -u "$(id -u):$(id -g)" \
        -v "$(pwd):/var/www/html" \
        -w /var/www/html \
        -e COMPOSER_CACHE_DIR=/tmp/composer_cache \
        laravelsail/php82-composer:latest \
        composer install --ignore-platform-reqs
````

5. **Laravel Sailの起動**

````bash
    ./vendor/bin/sail up -d --build
````

6. **アプリケーションキーの生成**

````bash
    ./vendor/bin/sail artisan key:generate
````

7. **データベースのマイグレーションと初期データ投入**

````bash
    ./vendor/bin/sail artisan migrate --seed
````

8. **フロントエンドのビルド**

````bash
    ./vendor/bin/sail npm install
    ./vendor/bin/sail npm run dev
````

9. **アプリケーションへのアクセス**

````bash
    一般ユーザーログイン http://localhost/login
    管理者ログイン http://localhost/admin/login

    ユーザー情報
    ユーザー1（一般）　メールアドレス：user1@example.com　パスワード：password
    ユーザー2（一般）　メールアドレス：user2@example.com　パスワード：password
    ユーザー2（管理者）　メールアドレス：user3@example.com　パスワード：password
````


## ER図

```mermaid
erDiagram
    users ||--o{ attendances : "複数の勤怠を持つ"
    users ||--o{ attendance_corrections : "複数の修正申請を持つ"
    attendances ||--o{ breaks : "複数の休憩を持つ"
    attendances ||--o{ attendance_corections : "複数の修正申請を持つ"


    users {
        bigint id PK "ID"
        string name "名前"  
        string email "メールアドレス"
        timestamp email_verified_at ”メール確認日時”
        string password "パスワード"
        string role "権限(デフォルト:heneral)"
        string remember_token "ログイン保持トークン"
        timestamp created_at
        timestamp updated_at
    }

    attendances {
        bigint id PK "ID"
        bigint user_id FK
        date date "日付"
        timestamp clock_in_at "出勤時間"
        timestamp clock_out_at "退勤時間"
        enum status "勤怠状況"
        text remarks 
        timestamp created_at
        timestamp updated_at
    }

    breaks {
        bigint id PK "ID"
        bigint attendance_id FK
        timestamp break_in_at "休憩開始時間"
        timestamp break_out_at "休憩終了時間"
        timestamp created_at
        timestamp updated_at
    }

    attendance_corrections {
        bigint id PK "ID"
        bigint attendance_id FK
        bigint user_id FK
        string new_clock_in "修正出勤時間"
        string new_clock_out "修正退勤時間"
        json new_breaks
        enum approval_status "承認状況"
        text comment "備考"
        timestamp created_at
        timestamp updated_at
    }
```    
 
## 使用技術

````bash
    - Laravel 10.x
    - PHP 8.2
    - Tailwind CSS
    - DB MySQL8.4
    - Webサーバー Nginx
    - フロントエンド Vite, Tailwind CSS ^3.4.0
    - 開発ツール Docker,Laravel Sail,phpAcmin
````

## APIエンドポイント一覧
    
| 処理内容 | HTTPメソッド | パス (URL) | 必須パラメータ | HTTPステータス |
| :--- | :---: | :--- | :--- | :---: |
| **お問い合わせ一覧取得** | `GET` | `/api/v1/contacts` | 任意 (検索・ページネーション) | `200 OK` |
| **お問い合わせ詳細取得** | `GET` | `/api/v1/contacts/{id}` | パスパラメータ: `id` | `200 OK` / `404` |
| **お問い合わせ新規作成** | `POST` | `/api/v1/contacts` | リクエストボディ (全必須項目) | `201 Created` / `422` |
| **お問い合わせ情報の更新** | `PUT` | `/api/v1/contacts/{id}` | リクエストボディ、パス: `id` | `200 OK` / `404` / `422` |
| **お問い合わせデータの削除** | `DELETE` | `/api/v1/contacts/{id}` | パスパラメータ: `id` | `204 No Content` / `404` |

## テスト実行

````bash
    単体テストの実行
    .vendor/bin/sail artisan test tests/Unit/ContactWebUnitTest.php
    すべてのテストを一括実行
    ./vendor/bin/sail artisan test --display-deprecations
````

## 開発環境URL
````bash
    お問い合わせフォーム http://localhost
    管理者ログイン http://localhost/login
````

## 作成者

小島　春菜
