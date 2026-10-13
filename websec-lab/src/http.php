<?php
declare(strict_types=1);

function send_security_headers(string $mode): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Lab-Header-Profile: ' . ($mode === 'strict' ? 'strict' : 'loose'));
    header_remove('X-Powered-By');
    if ($mode !== 'strict') {
        return;
    }
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

function json_out(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect_to(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flag_box(?string $flag): string
{
    if ($flag === null || $flag === '') {
        return '';
    }
    return '<p class="flag-box"><span>پرچم</span> <code>' . e($flag) . '</code></p>';
}

function render_page(string $title, string $body, string $mode = 'strict', array $scripts = []): void
{
    send_security_headers($mode);
    $user = current_user();
    $solved = solved_map();
    $total = count(challenge_catalog());
    $done = count($solved);
    $flashes = take_flash();
    $flashHtml = '';
    foreach ($flashes as $item) {
        $type = e((string) ($item[0] ?? 'info'));
        $message = e((string) ($item[1] ?? ''));
        $flashHtml .= '<p class="flash ' . $type . '">' . $message . '</p>';
    }
    $nav = [
        '/' => 'داشبورد',
        '/challenges' => 'چالش‌ها',
        '/posts' => 'پست‌ها',
        '/search' => 'جستجو',
        '/users' => 'کاربران',
        '/upload' => 'آپلود',
        '/avatar' => 'آواتار',
        '/admin' => 'مدیریت',
        '/debug/logs' => 'لاگ',
    ];
    $navHtml = '';
    foreach ($nav as $href => $label) {
        $navHtml .= '<a href="' . e($href) . '">' . e($label) . '</a>';
    }
    $account = $user
        ? '<span class="who">' . e((string) $user['username']) . '</span>
           <form method="post" action="/logout">' . csrf_field() . '<button type="submit">خروج</button></form>'
        : '<a class="text-link" href="/login">ورود</a>';
    $scriptTags = '<script src="/assets/js/app.js" defer></script>';
    foreach ($scripts as $src) {
        $scriptTags .= '<script src="' . e($src) . '" defer></script>';
    }
    $profileLabel = $mode === 'strict' ? 'هدر سخت‌گیر' : 'هدر تمرینی';
    echo '<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>' . e($title) . ' · رِنج</title>
  <link rel="icon" href="/favicon.svg" type="image/svg+xml">
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <a class="skip" href="#content">رفتن به محتوا</a>
  <header class="top">
    <div class="brand">
      <span class="mark">RANGE</span>
      <div>
        <strong>رِنج</strong>
        <small>آزمایشگاه محلی امنیت وب</small>
      </div>
    </div>
    <button id="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav">منو</button>
    <nav id="site-nav">' . $navHtml . '</nav>
    <div class="account">' . $account . '<span class="score">' . (int) $done . ' / ' . (int) $total . '</span></div>
  </header>
  <main id="content">
    ' . $flashHtml . '
    <h1>' . e($title) . '</h1>
    ' . $body . '
  </main>
  <footer>
    <span>' . e($profileLabel) . '</span>
    <span>فقط localhost · بدون سرویس خارجی · فایل آپلودی اجرا نمی‌شود</span>
  </footer>
  ' . $scriptTags . '
</body>
</html>';
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function page_intro(string $text): string
{
    return '<p class="lede">' . e($text) . '</p>';
}
