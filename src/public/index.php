<?php

declare(strict_types=1);

session_start();
require dirname(__DIR__) . '/app.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

if ($path === '/health') {
    header('Content-Type: text/plain');
    echo 'ok';
    exit;
}

if ($path === '/') {
    $q = $_GET['q'] ?? '';
    if ($q !== '') {
        // INTENTIONALLY VULNERABLE: challenge 1 (SQLi)
        $sql = "SELECT id, name, description, price, emoji FROM products WHERE published = 1 AND name LIKE '%{$q}%'";
        try {
            $products = db()->query($sql)->fetchAll();
            $error = null;
        } catch (Throwable $exception) {
            $products = [];
            $error = $exception->getMessage();
        }
    } else {
        $products = db()->query('SELECT id, name, description, price, emoji FROM products WHERE published = 1')->fetchAll();
        $error = null;
    }

    ob_start(); ?>
    <section class="hero">
        <p class="eyebrow">CAPTURE THE FLAG × E-COMMERCE</p>
        <h1>壊して学ぶ、脆弱なオンラインストア。</h1>
        <p>5つのフラグが、いつものEC操作の中に隠れています。</p>
    </section>
    <form class="search" method="get">
        <input name="q" value="<?= e((string)$q) ?>" placeholder="商品を検索">
        <button>検索</button>
    </form>
    <?php if ($q !== ''): ?>
        <script>localStorage.setItem('vulnmart_flag', <?= json_encode(FLAGS['xss']) ?>);</script>
        <!-- INTENTIONALLY VULNERABLE: challenge 2 (reflected XSS) -->
        <p class="result">「<?= $q ?>」の検索結果</p>
    <?php endif; ?>
    <?php if ($error): ?><pre class="error"><?= e($error) ?></pre><?php endif; ?>
    <div class="products">
        <?php foreach ($products as $product): ?>
            <article class="product">
                <div class="emoji"><?= e($product['emoji']) ?></div>
                <h2><?= e($product['name']) ?></h2>
                <p><?= e($product['description']) ?></p>
                <div class="product-footer">
                    <strong>¥<?= number_format((int)$product['price']) ?></strong>
                    <form method="post" action="/cart/add">
                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                        <button>カートへ</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php $content = ob_get_clean(); render('商品一覧', $content);
}

if ($path === '/login') {
    if ($method === 'POST') {
        $statement = db()->prepare('SELECT * FROM users WHERE email = ?');
        $statement->execute([$_POST['email'] ?? '']);
        $found = $statement->fetch();
        if ($found && password_verify($_POST['password'] ?? '', $found['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $found['id'];
            setcookie('role', 'customer', ['path' => '/', 'samesite' => 'Lax']);
            header('Location: /');
            exit;
        }
        $loginError = 'メールアドレスまたはパスワードが違います。';
    }
    ob_start(); ?>
    <section class="panel narrow">
        <p class="eyebrow">MEMBER LOGIN</p><h1>ログイン</h1>
        <?php if (!empty($loginError)): ?><p class="error"><?= e($loginError) ?></p><?php endif; ?>
        <form method="post" class="stack">
            <label>メール<input type="email" name="email" value="alice@example.test" required></label>
            <label>パスワード<input type="password" name="password" value="password123" required></label>
            <button>ログイン</button>
        </form>
        <p class="hint">デモ用の認証情報は入力済みです。</p>
    </section>
    <?php $content = ob_get_clean(); render('ログイン', $content);
}

if ($path === '/logout') {
    session_destroy();
    setcookie('role', '', time() - 3600, '/');
    header('Location: /');
    exit;
}

if ($path === '/cart/add' && $method === 'POST') {
    requireLogin();
    $id = (int)($_POST['product_id'] ?? 0);
    $statement = db()->prepare('SELECT id, name, price FROM products WHERE id = ? AND published = 1');
    $statement->execute([$id]);
    if ($product = $statement->fetch()) {
        $_SESSION['cart'] = $product;
        $_SESSION['flash'] = 'カートに追加しました。';
    }
    header('Location: /cart');
    exit;
}

if ($path === '/cart') {
    requireLogin();
    $product = $_SESSION['cart'] ?? null;
    ob_start(); ?>
    <section class="panel"><p class="eyebrow">YOUR CART</p><h1>ショッピングカート</h1>
    <?php if ($product): ?>
        <div class="cart-row"><div><strong><?= e($product['name']) ?></strong><p>数量 1</p></div><strong>¥<?= number_format((int)$product['price']) ?></strong></div>
        <form method="post" action="/checkout" class="stack">
            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <!-- INTENTIONALLY VULNERABLE: price is trusted by the server -->
            <input type="hidden" name="price" value="<?= (int)$product['price'] ?>">
            <label>配送先<input name="address" value="Tokyo CTF Street 1" required></label>
            <button>¥<?= number_format((int)$product['price']) ?>を支払う</button>
        </form>
    <?php else: ?><p>カートは空です。</p><?php endif; ?></section>
    <?php $content = ob_get_clean(); render('カート', $content);
}

if ($path === '/checkout' && $method === 'POST') {
    $user = requireLogin();
    $productId = (int)($_POST['product_id'] ?? 0);
    $clientPrice = (int)($_POST['price'] ?? -1); // INTENTIONALLY VULNERABLE
    $statement = db()->prepare('SELECT * FROM products WHERE id = ? AND published = 1');
    $statement->execute([$productId]);
    $product = $statement->fetch();

    if (!$product || $clientPrice < 0 || $clientPrice > (int)$user['balance']) {
        $_SESSION['flash'] = '残高不足または不正な決済です。';
        header('Location: /cart');
        exit;
    }

    $update = db()->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
    $update->execute([$clientPrice, $user['id']]);
    unset($_SESSION['cart']);
    $reward = $productId === 4 ? FLAGS['price'] : null;
    ob_start(); ?>
    <section class="panel success"><p class="eyebrow">PAYMENT COMPLETE</p><h1>ご購入ありがとうございます</h1>
        <p><?= e($product['name']) ?> を ¥<?= number_format($clientPrice) ?> で購入しました。</p>
        <?php if ($reward): ?><div class="flag"><?= e($reward) ?></div><?php endif; ?>
        <a class="button" href="/">買い物を続ける</a>
    </section>
    <?php $content = ob_get_clean(); render('購入完了', $content);
}

if ($path === '/orders') {
    $user = requireLogin();
    $statement = db()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
    $statement->execute([$user['id']]);
    $orders = $statement->fetchAll();
    ob_start(); ?>
    <section class="panel"><p class="eyebrow">ORDER HISTORY</p><h1>注文履歴</h1>
        <?php foreach ($orders as $order): ?><a class="order" href="/order?id=<?= (int)$order['id'] ?>"><span>#<?= (int)$order['id'] ?> <?= e($order['item_name']) ?></span><strong>詳細 →</strong></a><?php endforeach; ?>
    </section>
    <?php $content = ob_get_clean(); render('注文履歴', $content);
}

if ($path === '/order') {
    requireLogin();
    // INTENTIONALLY VULNERABLE: no ownership check (IDOR)
    $statement = db()->prepare('SELECT * FROM orders WHERE id = ?');
    $statement->execute([(int)($_GET['id'] ?? 0)]);
    $order = $statement->fetch();
    if (!$order) {
        http_response_code(404);
        render('注文なし', '<section class="panel"><h1>注文が見つかりません</h1></section>');
    }
    ob_start(); ?>
    <section class="panel"><p class="eyebrow">ORDER #<?= (int)$order['id'] ?></p><h1><?= e($order['item_name']) ?></h1>
        <dl><dt>購入日時</dt><dd><?= e($order['created_at']) ?></dd><dt>金額</dt><dd>¥<?= number_format((int)$order['total']) ?></dd><dt>配送先</dt><dd><?= e($order['shipping_address']) ?></dd><dt>備考</dt><dd><?= e($order['note']) ?></dd></dl>
    </section>
    <?php $content = ob_get_clean(); render('注文詳細', $content);
}

if ($path === '/admin') {
    requireLogin();
    // INTENTIONALLY VULNERABLE: unsigned, user-controlled cookie is authorization
    if (($_COOKIE['role'] ?? '') !== 'admin') {
        http_response_code(403);
        render('権限エラー', '<section class="panel"><h1>403 Forbidden</h1><p>管理者権限が必要です。</p></section>');
    }
    $content = '<section class="panel"><p class="eyebrow">ADMIN CONSOLE</p><h1>管理画面</h1><p>Cookieだけを信頼してはいけません。</p><div class="flag">' . e(FLAGS['admin']) . '</div></section>';
    render('管理画面', $content);
}

if ($path === '/challenges') {
    ob_start(); ?>
    <section class="hero small"><p class="eyebrow">5 CHALLENGES</p><h1>すべてのフラグを見つけよう。</h1><p>形式は <code>FLAG{...}</code> です。ブラウザの開発者ツールを使って構いません。</p></section>
    <div class="challenges">
        <article><b>01 / SQLi</b><h2>倉庫の隠し商品</h2><p>商品検索の裏には、非公開の商品も入ったデータベースがあります。</p><span>Easy · 100 pts</span></article>
        <article><b>02 / XSS</b><h2>検索結果のスクリプト</h2><p>検索語をHTMLとして実行し、localStorageに保管された値を表示してください。</p><span>Easy · 100 pts</span></article>
        <article><b>03 / IDOR</b><h2>他人の注文</h2><p>Aliceでログインし、Bobの注文詳細にある備考を確認してください。</p><span>Easy · 100 pts</span></article>
        <article><b>04 / PRICE</b><h2>買えない限定品</h2><p>残高1,000円で50,000円の限定商品を購入してください。</p><span>Easy · 100 pts</span></article>
        <article><b>05 / ACCESS</b><h2>管理者になりすます</h2><p><code>/admin</code> はCookieのroleだけで権限を判定しています。</p><span>Easy · 100 pts</span></article>
    </div>
    <?php $content = ob_get_clean(); render('チャレンジ', $content);
}

http_response_code(404);
render('404', '<section class="panel"><h1>404 Not Found</h1><a href="/">トップへ戻る</a></section>');
