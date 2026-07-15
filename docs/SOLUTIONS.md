# 運営者向け想定解

> このファイルにはフラグと想定ペイロードが含まれます。競技中は参加者に見えないようにしてください。

## 1. SQL Injection

商品検索の `q` がSQL文字列へ直接連結されています。検索欄に次を入力します。

```sql
' UNION SELECT id,name,description,price,emoji FROM products WHERE published=0-- 
```

フラグ: `FLAG{union_selects_more_than_products}`

修正: プレースホルダ付きのprepared statementを使用し、公開条件をクエリの全分岐で保証します。

## 2. Reflected XSS

検索結果の見出しだけはHTMLエスケープされていません。たとえば次を検索します。

```html
<img src=x onerror="document.body.innerHTML+=localStorage.vulnmart_flag">
```

フラグ: `FLAG{xss_runs_in_the_users_origin}`

修正: 出力コンテキストに合うHTMLエスケープを行い、CSPを追加します。

## 3. IDOR

Aliceでログインして `/order?id=1` を開き、IDを `2` に変えます。

フラグ: `FLAG{object_ids_are_not_authorization}`

修正: `WHERE id = ? AND user_id = ?` のように、必ずログインユーザーの所有権も検証します。

## 4. Parameter Tampering

限定商品をカートに入れ、開発者ツールでhidden inputの `price` を `0` にして決済します。HTTPリクエストを編集しても構いません。

フラグ: `FLAG{never_trust_client_side_prices}`

修正: 商品IDからサーバー側で最新価格を取得し、残高確認と減算をトランザクション内で行います。

## 5. Broken Access Control

ログイン後、Cookie `role=customer` を `role=admin` に変更して `/admin` を開きます。

フラグ: `FLAG{unsigned_role_cookies_are_not_auth}`

修正: 権限はサーバー側セッションやDBから読み、全管理者ルートで認可します。
