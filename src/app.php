<?php

declare(strict_types=1);

const FLAGS = [
    'sqli' => 'FLAG{union_selects_more_than_products}',
    'xss' => 'FLAG{xss_runs_in_the_users_origin}',
    'idor' => 'FLAG{object_ids_are_not_authorization}',
    'price' => 'FLAG{never_trust_client_side_prices}',
    'admin' => 'FLAG{unsigned_role_cookies_are_not_auth}',
];

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = getenv('DB_PATH') ?: dirname(__DIR__) . '/data/shop.sqlite';
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0775, true);
    }

    $isNew = !file_exists($path);
    $pdo = new PDO('sqlite:' . $path, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');

    if ($isNew) {
        seed($pdo);
    }

    return $pdo;
}

function seed(PDO $pdo): void
{
    $pdo->exec(<<<'SQL'
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            name TEXT NOT NULL,
            balance INTEGER NOT NULL DEFAULT 1000
        );
        CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            description TEXT NOT NULL,
            price INTEGER NOT NULL,
            emoji TEXT NOT NULL,
            published INTEGER NOT NULL DEFAULT 1
        );
        CREATE TABLE orders (
            id INTEGER PRIMARY KEY,
            user_id INTEGER NOT NULL,
            item_name TEXT NOT NULL,
            total INTEGER NOT NULL,
            shipping_address TEXT NOT NULL,
            note TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id)
        );
        SQL);

    $user = $pdo->prepare('INSERT INTO users (id, email, password_hash, name, balance) VALUES (?, ?, ?, ?, ?)');
    $user->execute([1, 'alice@example.test', password_hash('password123', PASSWORD_DEFAULT), 'Alice', 1000]);
    $user->execute([2, 'bob@example.test', password_hash('bob-secret', PASSWORD_DEFAULT), 'Bob', 5000]);

    $product = $pdo->prepare('INSERT INTO products (id, name, description, price, emoji, published) VALUES (?, ?, ?, ?, ?, ?)');
    $product->execute([1, 'CTF Starter Mug', '朝のフラグ探索にぴったりのマグカップ。', 800, '☕', 1]);
    $product->execute([2, 'Packet Hoodie', 'パケット柄の暖かいパーカー。', 6800, '🧥', 1]);
    $product->execute([3, 'Root Access Keycap', 'ESCキーをroot風に着せ替え。', 1200, '⌨️', 1]);
    $product->execute([4, 'Limited Flag Box', '決済に成功した購入者だけが開けられます。', 50000, '🎁', 1]);
    $product->execute([99, 'Internal SQL Memo', FLAGS['sqli'], 0, '🔒', 0]);

    $order = $pdo->prepare('INSERT INTO orders (id, user_id, item_name, total, shipping_address, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $order->execute([1, 1, 'CTF Starter Mug', 800, 'Tokyo CTF Street 1', '玄関前に置いてください', '2026-07-01 10:00']);
    $order->execute([2, 2, 'Private Backup Drive', 4200, 'Osaka Secret Ave 2', FLAGS['idor'], '2026-07-02 11:30']);
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    $statement = db()->prepare('SELECT id, email, name, balance FROM users WHERE id = ?');
    $statement->execute([$_SESSION['user_id']]);
    return $statement->fetch() ?: null;
}

function requireLogin(): array
{
    $user = currentUser();
    if (!$user) {
        header('Location: /login');
        exit;
    }
    return $user;
}

function render(string $title, string $content): never
{
    $user = currentUser();
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    require __DIR__ . '/template.php';
    exit;
}
