# VulnMart — 脆弱なECサイトCTF

PHP 8.3 + SQLite で動く、初学者向けの意図的に脆弱なECサイトです。

> [!WARNING]
> このアプリには意図的な脆弱性があります。インターネットへ公開せず、ローカルまたは隔離されたCTF環境でのみ使用してください。

## 起動

```bash
docker compose up --build
```

ブラウザで <http://localhost:8080> を開きます。初期化は次のコマンドです。

```bash
docker compose down -v
docker compose up --build
```

## 参加者用アカウント

- メール: `alice@example.test`
- パスワード: `password123`
- 初期残高: 1,000円

## 収録問題

| # | 分類 | 難易度 | 入口 |
|---|---|---|---|
| 1 | SQL Injection | Easy | 商品検索 |
| 2 | Reflected XSS | Easy | 商品検索 |
| 3 | IDOR | Easy | 注文履歴 |
| 4 | Parameter Tampering | Easy | カート・決済 |
| 5 | Broken Access Control | Easy | Cookie・管理画面 |

問題文はアプリ内の「チャレンジ」ページにあります。運営者向けの想定解は [`docs/SOLUTIONS.md`](docs/SOLUTIONS.md) を参照してください。

## 構成

- Apache + PHP 8.3
- SQLite（初回アクセス時に自動生成）
- フレームワーク・外部依存なし
- データは Docker named volume に保存
- 公開ポートは既定で `127.0.0.1:8080` のみ

ポートは環境変数で変更できます。

```bash
VULN_EC_PORT=18080 docker compose up --build
```

## 簡易確認

起動後に次を実行します。

```bash
./tests/smoke.sh
```

## 注意

このコードの脆弱な実装は教材用です。実サービスへコピーしないでください。各問題の修正方針も `docs/SOLUTIONS.md` に記載しています。
