<?php /** @var string $title */ ?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | VulnMart</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<header>
    <a class="brand" href="/"><span>V</span> VulnMart</a>
    <nav>
        <a href="/">商品</a>
        <a href="/challenges">チャレンジ</a>
        <?php if ($user): ?>
            <a href="/orders">注文履歴</a>
            <a href="/cart">カート</a>
            <span class="balance">残高 ¥<?= number_format((int)$user['balance']) ?></span>
            <a href="/logout">ログアウト</a>
        <?php else: ?>
            <a href="/login">ログイン</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
    <?= $content ?>
</main>
<footer>VulnMart is intentionally vulnerable. Local CTF use only.</footer>
</body>
</html>
