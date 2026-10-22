<?php
require 'config.php';

$action = $_GET['action'] ?? '';
$decodedInput = json_decode(file_get_contents('php://input'), true);
$input = is_array($decodedInput) ? $decodedInput : $_POST;

if ($action === 'health') {
    echo json_encode(['status' => 'ok']);
    exit;
}

if ($action === 'register') {
    $emailInput = $input['email'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $nameInput = $input['name'] ?? '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $password = $input['password'] ?? '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !is_string($password) || strlen($password) < 6) {
        http_response_code(400);
        exit(json_encode(['error' => 'Enter a valid email and a password of at least 6 characters']));
    }
    if ($name === '') {
        $name = strstr($email, '@', true) ?: $email;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)")->execute([$name, $email, $hash]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['error' => 'An account with that email already exists']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Could not create your account']);
        }
    }
    exit;
}

if ($action === 'login') {
    $emailInput = $input['email'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = $input['password'] ?? '';
    $stmt = $pdo->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && is_string($password) && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        echo json_encode(['success' => true, 'email' => $user['email']]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Email or password is incorrect']);
    }
    exit;
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'me') {
    echo json_encode(['user' => $_SESSION['email'] ?? null]);
    exit;
}

function requireAuth() {
    if (!isset($_SESSION['uid'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Please log in to access your links']);
        exit;
    }
}

if ($action === 'create') {
    $longUrlInput = $input['longUrl'] ?? '';
    $longUrl = is_string($longUrlInput) ? trim($longUrlInput) : '';
    $scheme = strtolower(parse_url($longUrl, PHP_URL_SCHEME) ?: '');
    if (!filter_var($longUrl, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Enter a valid http or https URL']));
    }

    $authenticated = isset($_SESSION['uid']);
    if (!$authenticated) {
        $now = time();
        $recentCreates = array_values(array_filter(
            $_SESSION['guest_create_times'] ?? [],
            function ($createdAt) use ($now) { return $createdAt > $now - 3600; }
        ));
        if (count($recentCreates) >= 10) {
            http_response_code(429);
            exit(json_encode(['error' => 'Guest limit reached. Try again in an hour or sign in for more links.']));
        }
        $_SESSION['guest_create_times'] = $recentCreates;
    }

    $customSlugInput = $input['customSlug'] ?? '';
    $customSlug = $authenticated && is_string($customSlugInput) ? trim($customSlugInput) : '';
    if ($customSlug !== '' && !preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $customSlug)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Custom links can contain only letters, numbers, hyphens, and underscores']));
    }
    $slug = $customSlug !== '' ? $customSlug : bin2hex(random_bytes(4));
    $expiry = $authenticated ? ($input['expiry'] ?? null) : null;
    $expiresAt = null;
    if (is_string($expiry) && $expiry !== '') {
        $expiryTimestamp = strtotime($expiry);
        if ($expiryTimestamp === false) {
            http_response_code(400);
            exit(json_encode(['error' => 'Choose a valid expiration date']));
        }
        $expiresAt = date('Y-m-d 23:59:59', $expiryTimestamp);
    }
    $password = $authenticated ? ($input['password'] ?? null) : null;
    $passwordHash = is_string($password) && $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

    try {
        $pdo->prepare("INSERT INTO links (user_id, short_code, long_url, password_hash, expires_at) VALUES (?, ?, ?, ?, ?)")
            ->execute([$authenticated ? $_SESSION['uid'] : null, $slug, $longUrl, $passwordHash, $expiresAt]);
        if (!$authenticated) {
            $_SESSION['guest_create_times'][] = time();
        }
        echo json_encode(['success' => true, 'slug' => $slug]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            http_response_code(409);
            echo json_encode(['error' => 'That short link is already taken. Try another custom ending.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Could not create your link']);
        }
    }
    exit;
}

if ($action === 'my-links') {
    requireAuth();
    $stmt = $pdo->prepare("SELECT id, long_url, short_code AS slug, hits AS clicks, expires_at AS expiry_date, password_hash, created_at FROM links WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$_SESSION['uid']]);
    $links = $stmt->fetchAll();
    foreach ($links as &$link) {
        $isExpired = $link['expiry_date'] && strtotime($link['expiry_date']) < time();
        $link['status'] = $isExpired ? 'expired' : ($link['password_hash'] ? 'protected' : 'active');
        unset($link['password_hash']);
    }
    unset($link);

    $total = $pdo->prepare("SELECT COALESCE(SUM(hits), 0) AS clicks, COUNT(*) AS total FROM links WHERE user_id = ?");
    $total->execute([$_SESSION['uid']]);
    $aggregate = $total->fetch();
    $chart = $pdo->prepare("SELECT DATE(clicked_at) AS d, COUNT(*) AS c FROM clicks_log WHERE link_id IN (SELECT id FROM links WHERE user_id = ?) AND clicked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) GROUP BY DATE(clicked_at) ORDER BY d");
    $chart->execute([$_SESSION['uid']]);

    echo json_encode([
        'links' => $links,
        'totalClicks' => $aggregate['clicks'],
        'totalLinks' => $aggregate['total'],
        'chart' => $chart->fetchAll()
    ]);
    exit;
}

if ($action === 'delete') {
    requireAuth();
    $pdo->prepare("DELETE FROM links WHERE id = ? AND user_id = ?")->execute([$input['id'] ?? 0, $_SESSION['uid']]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'analytics') {
    requireAuth();
    $stmt = $pdo->prepare("SELECT cl.*, l.slug FROM clicks_log cl JOIN links l ON cl.link_id = l.id WHERE l.user_id = ? ORDER BY cl.clicked_at DESC LIMIT 100");
    $stmt->execute([$_SESSION['uid']]);
    echo json_encode(['logs' => $stmt->fetchAll()]);
    exit;
}

if ($action === 'check-slug') {
    $slug = trim($_GET['slug'] ?? '');
    if ($slug === '' || !preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $slug)) {
        http_response_code(400);
        exit(json_encode(['error' => 'Enter a valid custom ending']));
    }
    $stmt = $pdo->prepare("SELECT id FROM links WHERE short_code = ?");
    $stmt->execute([$slug]);
    echo json_encode(['available' => !$stmt->fetch()]);
    exit;
}

if (isset($_GET['s'])) {
    $slug = trim($_GET['s']);
    if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $slug)) {
        http_response_code(404);
        exit('<h1>Link not found</h1>');
    }
    $stmt = $pdo->prepare("SELECT * FROM links WHERE short_code = ?");
    $stmt->execute([$slug]);
    $link = $stmt->fetch();
    if (!$link) {
        http_response_code(404);
        exit('<h1>Link not found</h1>');
    }
    if ($link['expires_at'] && strtotime($link['expires_at']) < time()) {
        http_response_code(410);
        exit('<h1>This link has expired</h1>');
    }

    if ($link['password_hash']) {
        $password = $_POST['p'] ?? $_GET['p'] ?? '';
        if (!is_string($password) || !password_verify($password, $link['password_hash'])) {
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html><html lang="en"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Protected link</title><body style="font-family:system-ui;background:#10120f;color:#f1f2e9;min-height:100vh;display:grid;place-items:center;margin:0"><form method="post" style="box-sizing:border-box;width:min(360px,calc(100% - 32px));padding:28px;border:1px solid #34392f;border-radius:16px;background:#181b16"><h1 style="font-size:22px">Password protected</h1><p style="color:#92978c">Enter the password to continue.</p><input type="password" name="p" required autofocus style="box-sizing:border-box;width:100%;height:44px;margin:12px 0;padding:0 12px;border:1px solid #3a4034;border-radius:8px;background:#10120f;color:#f1f2e9"><button style="height:42px;padding:0 18px;border:0;border-radius:8px;background:#c8fa67;color:#19220b;font-weight:700">Continue</button></form></body></html>';
            exit;
        }
    }

    $pdo->prepare("UPDATE links SET hits = hits + 1 WHERE id = ?")->execute([$link['id']]);
    $pdo->prepare("INSERT INTO clicks_log (link_id, ip_address, user_agent, referrer) VALUES (?, ?, ?, ?)")
        ->execute([
            $link['id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? ''
        ]);
    header('Location: ' . $link['long_url'], true, 302);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Endpoint not found']);
