<?php
declare(strict_types=1);

function route_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $path = rtrim(is_string($path) ? $path : '/', '/') ?: '/';

    $key = $method . ' ' . $path;
    $routes = [
        'GET /' => ['page_home', 'strict'],
        'GET /login' => ['page_login', 'strict'],
        'POST /login' => ['page_login', 'strict'],
        'GET /register' => ['page_register', 'strict'],
        'POST /register' => ['page_register', 'strict'],
        'POST /logout' => ['page_logout', 'strict'],
        'GET /login/recover' => ['page_recover', 'loose'],
        'POST /login/recover' => ['page_recover', 'loose'],
        'GET /profile' => ['page_profile', 'strict'],
        'GET /profile/edit' => ['page_profile_edit', 'loose'],
        'POST /profile/edit' => ['page_profile_edit', 'loose'],
        'GET /profile/bio-set' => ['page_bio_set', 'loose'],
        'GET /search' => ['page_search', 'loose'],
        'GET /users' => ['page_users', 'strict'],
        'GET /posts' => ['page_posts', 'strict'],
        'GET /posts/view' => ['page_post_view', 'loose'],
        'POST /posts/comment' => ['page_comment', 'loose'],
        'GET /posts/compose' => ['page_compose', 'strict'],
        'GET /posts/preview' => ['page_preview', 'strict'],
        'GET /admin' => ['page_admin', 'strict'],
        'GET /upload' => ['page_upload', 'loose'],
        'POST /upload' => ['page_upload', 'loose'],
        'GET /upload/file' => ['page_upload_file', 'strict'],
        'GET /avatar' => ['page_avatar', 'loose'],
        'POST /avatar' => ['page_avatar', 'loose'],
        'GET /avatar/file' => ['page_avatar_file', 'loose'],
        'GET /challenges' => ['page_challenges', 'strict'],
        'GET /debug/logs' => ['page_logs', 'strict'],
        'GET /api/profile' => ['api_profile', 'strict'],
        'GET /api/admin/stats' => ['api_admin_stats', 'strict'],
        'GET /api/admin/notes' => ['api_admin_notes', 'strict'],
        'POST /api/claim/xss-dom' => ['api_claim_dom', 'strict'],
    ];

    if (!isset($routes[$key])) {
        http_response_code(404);
        render_page('پیدا نشد', page_intro('این مسیر در آزمایشگاه وجود ندارد.'), 'strict');
        return;
    }
    [$handler, $mode] = $routes[$key];
    $handler($mode);
}

function page_home(string $mode): void
{
    $user = current_user();
    $solved = count(solved_map());
    $total = count(challenge_catalog());
    $progress = $user
        ? '<p class="lede">پیشرفت این نشست: ' . $solved . ' از ' . $total . ' چالش.</p>'
        : '<p class="lede"><a href="/login">وارد شوید</a> تا حل چالش‌ها در صفحه چالش‌ها ثبت شود.</p>';
    $body = page_intro('رِنج یک میدان تمرین خودکفا برای SQL Injection، XSS، آپلود، کنترل دسترسی و CSRF است. همه هدف‌ها داخل همین برنامه و روی localhost هستند.')
        . $progress . '
        <section class="grid">
          <article class="card">
            <h2>حساب‌های تمرینی</h2>
            <table>
              <thead><tr><th>کاربر</th><th>گذرواژه</th><th>نقش</th></tr></thead>
              <tbody>
                <tr><td>alice</td><td><code>AmberLane#1</code></td><td>user</td></tr>
                <tr><td>bob</td><td><code>BrightPath#2</code></td><td>user</td></tr>
                <tr><td>carol</td><td><code>CedarNorth#3</code></td><td>user</td></tr>
                <tr><td>dana</td><td><code>DeltaWest#4</code></td><td>user</td></tr>
              </tbody>
            </table>
            <p class="fine">حساب‌های admin و auditor و vaultkeeper در پایگاه هستند. گذرواژه آن‌ها منتشر نشده است.</p>
          </article>
          <article class="card">
            <h2>قواعد میدان</h2>
            <ul>
              <li>آزمایشگاه را به شبکه عمومی وصل نکنید.</li>
              <li>پرچم‌ها در HTML اولیه و فایل‌های جاوااسکریپت نیستند.</li>
              <li>آپلود، حتی اگر از اعتبارسنج عبور کند، اجرا نمی‌شود.</li>
              <li>کنار چند مسیر ناامن، مسیر امن متناظر هم هست.</li>
              <li>مبدأ آزمایشگاه <code>localhost:8080</code> و مبدأ دوم <code>127.0.0.1:8081</code> است.</li>
            </ul>
          </article>
        </section>
        <section class="card">
          <h2>نقشه</h2>
          <div class="links">
            <a href="/challenges">چالش‌ها و پیشرفت</a>
            <a href="/posts/view?id=1">جزئیات پست</a>
            <a href="/search?q=ali">جستجو</a>
            <a href="/login/recover">بازیابی پین</a>
            <a href="/upload">آپلود</a>
            <a href="/avatar">آواتار</a>
            <a href="/profile/edit">ویرایش پروفایل</a>
            <a href="/admin">پنل مدیریت</a>
          </div>
        </section>';
    render_page('داشبورد', $body, $mode);
}

function page_login(string $mode): void
{
    if (current_user()) {
        redirect_to('/');
    }
    $next = safe_next((string) ($_REQUEST['next'] ?? '/'));
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = db()->prepare('SELECT id, password FROM users WHERE username = ?');
        $row = null;
        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
        $hash = $row['password'] ?? dummy_password_hash();
        $valid = is_string($hash) && password_verify($password, $hash);
        if (!$row || !$valid) {
            log_event('auth', 'login failed');
            $error = '<p class="flash bad">نام کاربری یا گذرواژه نادرست است.</p>';
        } else {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $row['id'];
            log_event('auth', 'login ok');
            redirect_to($next);
        }
    }
    $body = page_intro('ورود اصلی گذرواژه را جدا از query بررسی می‌کند.')
        . $error . '
        <form class="card stack" method="post" action="/login" autocomplete="on">
          <input type="hidden" name="next" value="' . e($next) . '">
          <label>نام کاربری <input name="username" required maxlength="32" autocomplete="username"></label>
          <label>گذرواژه <input name="password" type="password" required maxlength="72" autocomplete="current-password"></label>
          <button type="submit">ورود</button>
          <p class="fine"><a href="/register">ساخت حساب</a> · <a href="/login/recover">بازیابی پین پشتیبانی</a></p>
        </form>';
    render_page('ورود', $body, $mode);
}

function page_register(string $mode): void
{
    if (current_user()) {
        redirect_to('/');
    }
    $error = '';
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
            $error = 'نام کاربری باید ۳ تا ۳۲ حرف انگلیسی یا عدد باشد.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'ایمیل معتبر نیست.';
        } elseif (strlen($password) < 8 || strlen($password) > 72) {
            $error = 'گذرواژه باید ۸ تا ۷۲ نویسه باشد.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $proof = bin2hex(random_bytes(8));
            $stmt = db()->prepare(
                'INSERT INTO users (username, email, password, bio, role, listed, csrf_proof) VALUES (?, ?, ?, ?, \'user\', 1, ?)'
            );
            $bio = '';
            if ($stmt) {
                $stmt->bind_param('sssss', $username, $email, $hash, $bio, $proof);
                $ok = $stmt->execute();
                $stmt->close();
                if ($ok) {
                    flash('حساب ساخته شد. حالا وارد شوید.');
                    redirect_to('/login');
                }
            }
            $error = 'این نام کاربری قبلاً ثبت شده است.';
        }
    }
    $body = page_intro('نقش حساب جدید همیشه user است.')
        . ($error !== '' ? '<p class="flash bad">' . e($error) . '</p>' : '') . '
        <form class="card stack" method="post" action="/register">
          <label>نام کاربری <input name="username" required maxlength="32"></label>
          <label>ایمیل <input name="email" type="email" required maxlength="120"></label>
          <label>گذرواژه <input name="password" type="password" required minlength="8" maxlength="72"></label>
          <button type="submit">ثبت‌نام</button>
        </form>';
    render_page('ثبت‌نام', $body, $mode);
}

function page_logout(string $mode): void
{
    if (!csrf_ok()) {
        http_response_code(400);
        render_page('خروج', '<p class="flash bad">توکن CSRF نامعتبر است.</p>', $mode);
        return;
    }
    $_SESSION = [];
    session_destroy();
    redirect_to('/');
}

function page_recover(string $mode): void
{
    require_login();
    $found = null;
    $accepted = null;
    $blocked = false;
    $failed = false;
    $flag = null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $username = clip((string) ($_POST['username'] ?? ''), 80);
        $pin = clip((string) ($_POST['pin'] ?? ''), 40);
        if (preg_match('/union|select|sleep|benchmark|load_file|outfile|information_schema|get_lock|drop|insert|update|delete|alter|grant|truncate|--|#|\/\*/i', $username . "\n" . $pin)) {
            $blocked = true;
            log_event('sql', 'recover filter blocked a keyword');
        } else {
            $sql = "SELECT support_pin FROM users WHERE username = '" . $username . "' LIMIT 1";
            $result = db()->query($sql);
            if ($result === false) {
                $failed = true;
                log_event('sql', 'recover query failed');
            } else {
                $row = $result->fetch_assoc();
                $result->free();
                $found = $row !== null;
                $stored = is_array($row) ? ($row['support_pin'] ?? null) : null;
                $accepted = is_string($stored) && $stored !== '' && hash_equals($stored, $pin);
                if ($accepted) {
                    $flag = award('sqli_blind');
                }
                log_event('auth', 'recover checked');
            }
        }
    }
    $status = '';
    if ($blocked) {
        $status = '<p class="flash bad">عبارت مسدود شد.</p>';
    } elseif ($failed) {
        $status = '<p class="flash bad">پرس‌وجو اجرا نشد. خطا در لاگ تمرین است.</p>';
    } elseif ($found !== null) {
        $status = '<dl class="meta"><div><dt>حساب پیدا شد</dt><dd>' . ($found ? 'بله' : 'خیر') . '</dd></div>'
            . '<div><dt>پین پذیرفته شد</dt><dd>' . ($accepted ? 'بله' : 'خیر') . '</dd></div></dl>'
            . flag_box($flag);
    }
    $body = page_intro('این فرم جدا از ورود اصلی است و فقط وضعیت بازیابی را برمی‌گرداند.')
        . $status . '
        <form class="card stack" method="post" action="/login/recover">
          <label>نام کاربری <input name="username" required maxlength="80"></label>
          <label>پین پشتیبانی <input name="pin" maxlength="40" autocomplete="off"></label>
          <button type="submit">بررسی</button>
        </form>';
    render_page('بازیابی پین', $body, $mode);
}

function page_profile(string $mode): void
{
    $user = require_login();
    $id = (int) ($_GET['id'] ?? $user['id']);
    $stmt = db()->prepare('SELECT id, username, bio, role, created_at FROM users WHERE id = ?');
    $row = null;
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$row) {
        http_response_code(404);
        render_page('پروفایل', page_intro('چنین کاربری نیست.'), $mode);
        return;
    }
    $body = '<article class="card">
        <p class="kicker">شناسه ' . (int) $row['id'] . ' · ' . e((string) $row['role']) . '</p>
        <h2>' . e((string) $row['username']) . '</h2>
        <p>' . e((string) $row['bio']) . '</p>
        <p class="fine">عضویت: ' . e((string) $row['created_at']) . '</p>
        <p><a href="/api/profile?id=' . (int) $row['id'] . '">خروجی JSON</a></p>
      </article>';
    if ((int) $row['id'] === (int) $user['id']) {
        $body .= '<p><a href="/profile/edit">ویرایش پروفایل</a></p>';
    }
    render_page('پروفایل', $body, $mode);
}

function page_profile_edit(string $mode): void
{
    $user = require_login();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!csrf_ok()) {
            flash('توکن CSRF نامعتبر است.', 'bad');
            redirect_to('/profile/edit');
        }
        $action = (string) ($_POST['action'] ?? '');
        $uid = (int) $user['id'];
        if ($action === 'email') {
            $email = trim((string) ($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('ایمیل معتبر نیست.', 'bad');
            } else {
                $stmt = db()->prepare('UPDATE users SET email = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $email, $uid);
                    $stmt->execute();
                    $stmt->close();
                    flash('ایمیل با توکن CSRF به‌روز شد.');
                    log_event('profile', 'email updated');
                }
            }
        } elseif ($action === 'bio') {
            $bio = clip(trim((string) ($_POST['bio'] ?? '')), 500);
            $stmt = db()->prepare('UPDATE users SET bio = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $bio, $uid);
                $stmt->execute();
                $stmt->close();
                flash('بیو از فرم امن ذخیره شد.');
            }
        }
        redirect_to('/profile/edit');
    }
    $fresh = current_user_fresh((int) $user['id']) ?? $user;
    $csrfFlag = $_SESSION['csrf_flag'] ?? null;
    unset($_SESSION['csrf_flag']);
    $body = page_intro('تغییر ایمیل توکن می‌خواهد. فرم قدیمی بیو جداست و در هدر این صفحه قاب‌بندی مسدود نشده است.')
        . flag_box(is_string($csrfFlag) ? $csrfFlag : null) . '
        <div class="grid">
          <form class="card stack" method="post" action="/profile/edit">
            ' . csrf_field() . '
            <input type="hidden" name="action" value="email">
            <h2>ایمیل</h2>
            <label>ایمیل فعلی <input name="email" type="email" required value="' . e((string) $fresh['email']) . '"></label>
            <button type="submit">ذخیره ایمیل</button>
          </form>
          <form class="card stack" method="post" action="/profile/edit">
            ' . csrf_field() . '
            <input type="hidden" name="action" value="bio">
            <h2>بیو</h2>
            <label>متن <textarea name="bio" maxlength="500" rows="4">' . e((string) $fresh['bio']) . '</textarea></label>
            <button type="submit">ذخیره بیو</button>
            <p class="fine">نمایش بیو در پروفایل escape می‌شود.</p>
          </form>
        </div>
        <form class="card stack" method="get" action="/profile/bio-set">
          <h2>فرم قدیمی بیو</h2>
          <label>متن <input name="bio" maxlength="180" value=""></label>
          <button type="submit">ثبت با فرم قدیمی</button>
          <p>نشان اثبات این حساب: <code>' . e((string) $fresh['csrf_proof']) . '</code></p>
          <p class="fine">برای کامل شدن چالش، همین مقدار باید از <a href="http://127.0.0.1:8081/">127.0.0.1:8081</a> بیاید. خود آزمایشگاه را با localhost باز کنید. کوکی نشست HttpOnly و SameSite=Lax است. کوکی range_pref از اسکریپت خوانده می‌شود و پرچم نیست.</p>
        </form>'
        . avatar_forms('/profile/edit')
        . avatar_result_html();
    render_page('ویرایش پروفایل', $body, $mode);
}

function current_user_fresh(int $id): ?array
{
    $stmt = db()->prepare('SELECT id, username, email, bio, role, csrf_proof FROM users WHERE id = ?');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

function page_bio_set(string $mode): void
{
    $user = require_login();
    $bio = clip((string) ($_GET['bio'] ?? ''), 180);
    $uid = (int) $user['id'];
    $stmt = db()->prepare('UPDATE users SET bio = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $bio, $uid);
        $stmt->execute();
        $stmt->close();
    }
    $cross = ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '') === 'cross-site';
    log_event('csrf', 'bio-set site=' . (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'missing'));
    if ($cross && hash_equals((string) $user['csrf_proof'], $bio)) {
        $_SESSION['csrf_flag'] = award('csrf');
    }
    redirect_to('/profile/edit');
}

function page_search(string $mode): void
{
    require_login();
    $raw = clip((string) ($_GET['q'] ?? ''), 240);
    $reflected = reflect_filter($raw);
    $flag = xss_vector($reflected) ? award('xss_reflected') : null;
    $rows = [];
    $note = '';
    if ($raw !== '' && array_key_exists('q', $_GET)) {
        $sqlInput = str_replace('\\', '', filter_once(strip_danger($raw), ['/union/i', '/select/i']));
        $sql = "SELECT username, email FROM users WHERE listed = 1 AND username LIKE '%" . $sqlInput . "%'";
        $result = db()->query($sql);
        if ($result === false) {
            log_event('sql', 'search: ' . db()->error);
            $note = '<p class="flash bad">جستجو ناموفق بود. متن خطا در لاگ تمرین ثبت شد.</p>';
        } else {
            while (($row = $result->fetch_assoc()) && count($rows) < 30) {
                observe_row($row);
                $rows[] = $row;
            }
            $result->free();
        }
    }
    $table = '<p class="fine">نتیجه‌ای نیست.</p>';
    if ($rows) {
        $table = '<table><thead><tr><th>نام</th><th>ایمیل</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            $table .= '<tr><td>' . e((string) ($row['username'] ?? '')) . '</td><td>' . e((string) ($row['email'] ?? '')) . '</td></tr>';
        }
        $table .= '</tbody></table>';
    }
    $body = page_intro('بخش امن عبارت را escape می‌کند. بخش دیگر همان مقدار فیلترشده را در ویژگی input می‌گذارد. پایین صفحه یک sink جدا در مرورگر است.')
        . flag_box($flag) . $note . '
        <form class="card stack" method="get" action="/search">
          <label>عبارت <input name="q" maxlength="240" value=""></label>
          <button type="submit">جستجو</button>
          <p class="safe-box">خروجی امن: ' . e($raw) . '</p>
          <p class="fine">بازتاب فیلترشده داخل مقدار این فیلد است.</p>
          <input class="reflect" type="text" readonly value="' . $reflected . '">
        </form>
        <section class="card">' . $table . '</section>
        <section class="card">
          <h2>پیش‌نمایش مرورگر</h2>
          <p class="fine">پارامتر <code>dom</code> در سرور بازتاب داده نمی‌شود. خروجی امن با textContent پر می‌شود.</p>
          <p id="dom-safe" class="safe-box"></p>
          <div id="dom-preview" class="sink-box"></div>
          <p id="dom-score" class="flag-slot"></p>
        </section>';
    render_page('جستجو', $body, $mode, ['/assets/js/domlab.js']);
}

function observe_row(array $row): void
{
    foreach ($row as $value) {
        if (is_string($value)) {
            observe_flag_value($value);
        }
    }
}

function page_users(string $mode): void
{
    require_login();
    $q = clip(trim((string) ($_GET['q'] ?? '')), 64);
    if ($q === '') {
        $stmt = db()->prepare('SELECT id, username, bio FROM users WHERE listed = 1 ORDER BY id');
    } else {
        $stmt = db()->prepare('SELECT id, username, bio FROM users WHERE listed = 1 AND username LIKE ? ORDER BY id');
    }
    $rows = [];
    if ($stmt) {
        if ($q !== '') {
            $like = '%' . $q . '%';
            $stmt->bind_param('s', $like);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }
    $table = '<table><thead><tr><th>شناسه</th><th>نام</th><th>بیو</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $table .= '<tr><td>' . $id . '</td><td><a href="/profile?id=' . $id . '">' . e((string) $row['username']) . '</a></td><td>' . e((string) $row['bio']) . '</td></tr>';
    }
    $table .= '</tbody></table>';
    $body = page_intro('این فهرست با prepared statement ساخته شده و بیو escape می‌شود.') . '
        <form class="inline" method="get" action="/users">
          <input name="q" value="' . e($q) . '" maxlength="64" placeholder="نام کاربری">
          <button type="submit">جستجو</button>
        </form>' . $table;
    render_page('کاربران', $body, $mode);
}

function page_posts(string $mode): void
{
    require_login();
    $stmt = db()->prepare(
        'SELECT posts.id, posts.title, users.username FROM posts JOIN users ON users.id = posts.user_id WHERE posts.published = 1 ORDER BY posts.id'
    );
    $rows = [];
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        $stmt->close();
    }
    $list = '';
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $list .= '<li><a href="/posts/view?id=' . $id . '">' . e((string) $row['title']) . '</a> <span class="fine">' . e((string) $row['username']) . '</span></li>';
    }
    $body = page_intro('فهرست فقط مطالب منتشرشده را با query پارامتری می‌آورد.')
        . '<ul class="index">' . $list . '</ul><p><a href="/posts/compose">ویرایشگر پیش‌نویس</a></p>';
    render_page('پست‌ها', $body, $mode);
}

function page_post_view(string $mode): void
{
    require_login();
    $rawId = clip((string) ($_GET['id'] ?? '0'), 240);
    $sqlId = strip_danger($rawId);
    if ($sqlId === '') {
        $sqlId = '0';
    }
    $sql = 'SELECT id, title, body, user_id FROM posts WHERE published = 1 AND id = ' . $sqlId;
    $result = db()->query($sql);
    $cards = '';
    $error = '';
    if ($result === false) {
        log_event('sql', 'posts/view: ' . db()->error);
        $error = '<pre class="sql-error">' . e(redact(db()->error)) . '</pre>';
    } else {
        $count = 0;
        while (($row = $result->fetch_assoc()) && $count < 30) {
            $count++;
            observe_row($row);
            $cards .= '<article class="card"><h2>' . e((string) ($row['title'] ?? '')) . '</h2><p>' . e((string) ($row['body'] ?? '')) . '</p></article>';
        }
        $result->free();
        if ($cards === '') {
            $cards = '<p class="fine">مطلبی برنگشت.</p>';
        }
    }
    $comments = '';
    $flag = null;
    if (ctype_digit($rawId)) {
        $postId = (int) $rawId;
        $stmt = db()->prepare(
            'SELECT comments.body, comments.created_at, users.username FROM comments JOIN users ON users.id = comments.user_id WHERE comments.post_id = ? ORDER BY comments.id'
        );
        if ($stmt) {
            $stmt->bind_param('i', $postId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rendered = legacy_comment((string) $row['body']);
                if (xss_vector($rendered)) {
                    $flag = award('xss_stored');
                }
                $comments .= '<article class="comment"><header>' . e((string) $row['username']) . ' · ' . e((string) $row['created_at']) . '</header><div class="comment-body">' . $rendered . '</div></article>';
            }
            $stmt->close();
        }
        $comments .= '<form class="stack" method="post" action="/posts/comment">
            ' . csrf_field() . '
            <input type="hidden" name="post_id" value="' . $postId . '">
            <label>دیدگاه <textarea name="body" maxlength="500" required rows="3"></textarea></label>
            <button type="submit">ثبت دیدگاه</button>
          </form>';
    }
    $body = flag_box($flag) . $error . $cards . '<section class="card"><h2>دیدگاه‌ها</h2>' . $comments . '</section>';
    render_page('جزئیات پست', $body, $mode);
}

function page_comment(string $mode): void
{
    $user = require_login();
    if (!csrf_ok()) {
        flash('توکن CSRF نامعتبر است.', 'bad');
        redirect_to('/posts');
    }
    $postId = (int) ($_POST['post_id'] ?? 0);
    $body = clip(trim((string) ($_POST['body'] ?? '')), 500);
    $stmt = db()->prepare('SELECT id FROM posts WHERE id = ? AND published = 1');
    $exists = false;
    if ($stmt) {
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if ($exists && $body !== '') {
        $uid = (int) $user['id'];
        $insert = db()->prepare('INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)');
        if ($insert) {
            $insert->bind_param('iis', $postId, $uid, $body);
            $insert->execute();
            $insert->close();
        }
    }
    redirect_to('/posts/view?id=' . $postId);
}

function page_compose(string $mode): void
{
    require_login();
    $body = page_intro('پیش‌نویس در فهرست مطالب منتشرشده نیست.') . '
        <section class="card">
          <h2>ویرایشگر</h2>
          <p>ذخیره پیش‌نویس در این نسخه بسته است. مشاهده از مسیر پیش‌نمایش انجام می‌شود.</p>
          <div id="draft-config" data-preview="/posts/preview" data-sample="1337" hidden></div>
        </section>';
    render_page('ویرایشگر پیش‌نویس', $body, $mode);
}

function page_preview(string $mode): void
{
    require_login();
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = db()->prepare('SELECT id, title, body, published FROM posts WHERE id = ?');
    $row = null;
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
    if (!$row) {
        http_response_code(404);
        render_page('پیش‌نمایش', page_intro('پیش‌نویسی با این شناسه نیست.'), $mode);
        return;
    }
    $flag = ((int) $row['published'] === 0 && (int) $row['id'] === 1337) ? award('predictable') : null;
    $body = flag_box($flag) . '<article class="card"><p class="kicker">شناسه ' . (int) $row['id'] . '</p><h2>' . e((string) $row['title']) . '</h2><p>' . e((string) $row['body']) . '</p></article>';
    render_page('پیش‌نمایش', $body, $mode);
}

function page_admin(string $mode): void
{
    require_login();
    $body = page_intro('پنل تمرین فقط پوسته مدیریت است. دکمه آمار نقش را چک می‌کند.') . '
        <section class="card stack">
          <button id="admin-stats" type="button">دریافت آمار</button>
          <pre id="admin-out" class="sql-error">هنوز درخواستی نرفته است.</pre>
        </section>';
    render_page('مدیریت', $body, $mode, ['/assets/js/admin.js']);
}

function page_upload(string $mode): void
{
    require_login();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        handle_upload_post();
        redirect_to('/upload');
    }
    $result = $_SESSION['upload_result'] ?? null;
    $resultHtml = '';
    if (is_array($result)) {
        $resultHtml = '<section class="card"><h2>آخرین نتیجه</h2><dl class="meta">'
            . meta_row('نام فایل', (string) ($result['name'] ?? ''))
            . meta_row('پسوند نهایی', (string) ($result['ext'] ?? ''))
            . meta_row('MIME کلاینت', (string) ($result['mime'] ?? ''))
            . meta_row('حجم', (string) ($result['size'] ?? ''))
            . meta_row('حالت', (string) ($result['mode'] ?? ''))
            . meta_row('نتیجه', (string) ($result['decision'] ?? ''))
            . (isset($result['detail']) ? meta_row('جزئیات', (string) $result['detail']) : '')
            . '</dl>'
            . (isset($result['download']) ? '<p><a href="' . e((string) $result['download']) . '">دانلود پیوست</a></p>' : '')
            . flag_box(isset($result['flag']) ? (string) $result['flag'] : null)
            . '</section>';
    }
    $body = page_intro('سه اعتبارسنج کنار هم هستند. موفقیت چالش یعنی پذیرش فایل؛ هیچ فایلی در سرور اجرا نمی‌شود و دانلود همیشه پیوست octet-stream است.')
        . $resultHtml
        . upload_form('ext', 'چالش پسوند', 'فهرست پسوند نهایی کوتاه است.')
        . upload_form('mime', 'چالش MIME', 'پسوند و نوع اعلام‌شده هر دو دیده می‌شوند.')
        . upload_form('secure', 'آپلود امن', 'allowlist، MIME واقعی، GD، نام تصادفی و ذخیره بیرون از web root.');
    render_page('آپلود', $body, $mode);
}

function meta_row(string $label, string $value): string
{
    return '<div><dt>' . e($label) . '</dt><dd>' . e($value) . '</dd></div>';
}

function upload_form(string $mode, string $title, string $text): string
{
    return '<form class="card stack" method="post" action="/upload" enctype="multipart/form-data">
        ' . csrf_field() . '
        <input type="hidden" name="mode" value="' . e($mode) . '">
        <h2>' . e($title) . '</h2>
        <p class="fine">' . e($text) . '</p>
        <label>فایل <input type="file" name="file" required></label>
        <button type="submit">آپلود</button>
      </form>';
}

function handle_upload_post(): void
{
    $user = require_login();
    if (!csrf_ok()) {
        $_SESSION['upload_result'] = ['decision' => 'رد شد', 'detail' => 'توکن CSRF نامعتبر است', 'mode' => '', 'name' => '', 'ext' => '', 'mime' => '', 'size' => '0'];
        return;
    }
    $mode = (string) ($_POST['mode'] ?? '');
    if (!in_array($mode, ['ext', 'mime', 'secure'], true)) {
        $mode = 'ext';
    }
    $file = $_FILES['file'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $_SESSION['upload_result'] = result_stub($mode, 'رد شد', 'فایل دریافت نشد');
        return;
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    $original = clip(basename(str_replace('\\', '/', (string) ($file['name'] ?? 'file'))), 180);
    $clientMime = clip((string) ($file['type'] ?? ''), 128);
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 1500000 || !is_uploaded_file($tmp)) {
        $_SESSION['upload_result'] = result_stub($mode, 'رد شد', 'حجم مجاز نیست', $original, $clientMime, $size);
        log_event('upload', 'rejected ' . $mode);
        return;
    }
    $ext = ext_of($original);
    $decision = 'پذیرفته شد';
    $detail = 'بیرون از web root ذخیره شد';
    $flag = null;
    if ($mode === 'ext') {
        $banned = ['php', 'exe', 'js', 'html', 'htm'];
        if (in_array($ext, $banned, true)) {
            $decision = 'رد شد';
            $detail = 'پسوند نهایی در فهرست ممنوع است';
        } else {
            $dangerous = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'pht', 'phps'];
            foreach (extension_tokens($original) as $token) {
                if (in_array($token, $dangerous, true)) {
                    $flag = award('upload_ext');
                    break;
                }
            }
        }
    } elseif ($mode === 'mime') {
        $allowedExt = ['png', 'jpg', 'jpeg', 'gif'];
        $allowedMime = ['image/png', 'image/jpeg', 'image/gif'];
        $extOk = in_array($ext, $allowedExt, true);
        $mimeOk = in_array($clientMime, $allowedMime, true);
        if (!$extOk && !$mimeOk) {
            $decision = 'رد شد';
            $detail = 'نه پسوند و نه MIME اعلام‌شده مجاز نبود';
        } else {
            $realOk = in_array(finfo_mime($tmp), $allowedMime, true);
            if (!$extOk || !$realOk) {
                $flag = award('upload_mime');
            }
        }
    } else {
        $expected = mime_for_extension($ext);
        $real = finfo_mime($tmp);
        if ($expected === null || $expected !== $real || image_type_ok($tmp) === 0) {
            $decision = 'رد شد';
            $detail = 'پسوند، MIME واقعی و تصویر قابل خواندن هم‌خوان نیستند. MIME واقعی: ' . $real;
        }
    }
    $download = null;
    if ($decision === 'پذیرفته شد') {
        $stored = bin2hex(random_bytes(16));
        $dest = storage_dir('uploads') . '/' . $stored;
        $moved = $mode === 'secure' ? reencode_image($tmp, $dest) : move_uploaded_file($tmp, $dest);
        if (!$moved) {
            $decision = 'رد شد';
            $detail = 'ذخیره یا کدگذاری مجدد انجام نشد';
            $flag = null;
        } else {
            chmod($dest, 0640);
            $uid = (int) $user['id'];
            $size = (int) filesize($dest);
            $stmt = db()->prepare('INSERT INTO uploads (user_id, stored_name, original_name, client_mime, size_bytes, mode) VALUES (?, ?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('isssis', $uid, $stored, $original, $clientMime, $size, $mode);
                $stmt->execute();
                $id = (int) $stmt->insert_id;
                $stmt->close();
                $download = '/upload/file?id=' . $id;
            }
        }
    }
    log_event('upload', $decision === 'پذیرفته شد' ? 'accepted ' . $mode : 'rejected ' . $mode);
    $_SESSION['upload_result'] = [
        'name' => $original,
        'ext' => $ext,
        'mime' => $clientMime,
        'size' => (string) $size,
        'mode' => $mode,
        'decision' => $decision,
        'detail' => $detail,
        'download' => $download,
        'flag' => $flag,
    ];
}

function result_stub(string $mode, string $decision, string $detail, string $name = '', string $mime = '', int $size = 0): array
{
    return [
        'name' => $name,
        'ext' => ext_of($name),
        'mime' => $mime,
        'size' => (string) $size,
        'mode' => $mode,
        'decision' => $decision,
        'detail' => $detail,
    ];
}

function page_upload_file(string $mode): void
{
    $user = require_login();
    $id = (int) ($_GET['id'] ?? 0);
    $uid = (int) $user['id'];
    $stmt = db()->prepare('SELECT stored_name FROM uploads WHERE id = ? AND user_id = ?');
    $stored = '';
    if ($stmt) {
        $stmt->bind_param('ii', $id, $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $stored = (string) ($row['stored_name'] ?? '');
    }
    $base = realpath(storage_dir('uploads'));
    $path = $base && preg_match('/^[a-f0-9]{32}$/', $stored) ? realpath($base . '/' . $stored) : false;
    if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        return;
    }
    header('Content-Type: application/octet-stream');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: attachment; filename="range-download.bin"');
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: no-store');
    header('X-Lab-Header-Profile: strict');
    readfile($path);
}

function page_avatar(string $mode): void
{
    require_login();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        handle_avatar_post();
        $back = (string) ($_POST['return'] ?? '/avatar');
        if (!in_array($back, ['/avatar', '/profile/edit'], true)) {
            $back = '/avatar';
        }
        redirect_to($back);
    }
    $body = page_intro('سه پردازشگر تصویر: سهل‌گیر، میانی، و سخت‌گیر. بندانگشتی با GD ساخته می‌شود. حالت میانی فراداده را جدا نشان می‌دهد و حالت سخت‌گیر فایل را دوباره کد می‌کند.')
        . avatar_result_html()
        . avatar_forms('/avatar');
    render_page('آواتار', $body, $mode);
}

function avatar_forms(string $returnTo): string
{
    $html = '';
    foreach ([
        'loose' => ['سهل‌گیر', 'نام یا نوع اعلام‌شده کافی است. بعضی فایل‌ها در خود صفحه پیش‌نمایش داده می‌شوند.'],
        'standard' => ['میانی', 'تصویر واقعی لازم است. کامنت JPEG و tEXt فایل PNG خوانده می‌شود و بندانگشتی جدا ساخته می‌شود.'],
        'strict' => ['سخت‌گیر', 'allowlist، MIME واقعی، کدگذاری مجدد، نام تصادفی و حذف فراداده.'],
    ] as $processor => $copy) {
        $html .= '<form class="card stack" method="post" action="/avatar" enctype="multipart/form-data">
            ' . csrf_field() . '
            <input type="hidden" name="processor" value="' . e($processor) . '">
            <input type="hidden" name="return" value="' . e($returnTo) . '">
            <h2>' . e($copy[0]) . '</h2>
            <p class="fine">' . e($copy[1]) . '</p>
            <label>تصویر <input type="file" name="avatar" required></label>
            <button type="submit">پردازش</button>
          </form>';
    }
    return $html;
}

function handle_avatar_post(): void
{
    $user = require_login();
    if (!csrf_ok()) {
        $_SESSION['avatar_preview'] = ['error' => 'توکن CSRF نامعتبر است'];
        return;
    }
    $processor = (string) ($_POST['processor'] ?? '');
    if (!in_array($processor, ['loose', 'standard', 'strict'], true)) {
        $processor = 'loose';
    }
    $file = $_FILES['avatar'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $_SESSION['avatar_preview'] = ['error' => 'فایل دریافت نشد'];
        return;
    }
    $tmp = (string) $file['tmp_name'];
    $original = clip(basename(str_replace('\\', '/', (string) ($file['name'] ?? 'avatar'))), 180);
    $clientMime = clip((string) ($file['type'] ?? ''), 128);
    $size = (int) ($file['size'] ?? 0);
    if ($size < 1 || $size > 400000 || !is_uploaded_file($tmp)) {
        $_SESSION['avatar_preview'] = ['error' => 'حجم آواتار مجاز نیست'];
        return;
    }
    $ext = ext_of($original);
    $looseOk = (bool) preg_match('/\.(png|jpe?g|gif)$/i', $original)
        || str_starts_with(strtolower($clientMime), 'image/')
        || $ext === 'svg';
    $expected = mime_for_extension($ext);
    $real = finfo_mime($tmp);
    $imageOk = $expected !== null && $expected === $real && image_type_ok($tmp) !== 0;
    if ($processor === 'loose' && !$looseOk) {
        $_SESSION['avatar_preview'] = ['error' => 'حالت سهل‌گیر این فایل را تصویر ندانست'];
        log_event('avatar', 'rejected loose');
        return;
    }
    if ($processor !== 'loose' && !$imageOk) {
        $_SESSION['avatar_preview'] = ['error' => 'حالت ' . $processor . ' تصویر واقعی با پسوند هم‌خوان می‌خواهد'];
        log_event('avatar', 'rejected ' . $processor);
        return;
    }
    $id = bin2hex(random_bytes(16));
    $dir = storage_dir('avatars');
    $stored = $dir . '/' . $id;
    if ($processor === 'strict') {
        if (!reencode_image($tmp, $stored . '.jpg')) {
            $_SESSION['avatar_preview'] = ['error' => 'کدگذاری مجدد ناموفق بود'];
            return;
        }
        chmod($stored . '.jpg', 0640);
        $view = $id . '.jpg';
    } else {
        if (!move_uploaded_file($tmp, $stored)) {
            $_SESSION['avatar_preview'] = ['error' => 'ذخیره ناموفق بود'];
            return;
        }
        chmod($stored, 0640);
        $view = $id;
        if ($processor === 'standard') {
            reencode_image($stored, $stored . '-thumb.jpg');
            if (is_file($stored . '-thumb.jpg')) {
                chmod($stored . '-thumb.jpg', 0640);
            }
        }
    }
    $_SESSION['avatar_preview'] = [
        'processor' => $processor,
        'file' => $view,
        'original' => $original,
        'mime' => $clientMime,
        'size' => (string) $size,
        'ext' => $ext,
    ];
    log_event('avatar', 'accepted ' . $processor);
}

function avatar_result_html(): string
{
    $preview = $_SESSION['avatar_preview'] ?? null;
    if (!is_array($preview)) {
        return '';
    }
    if (isset($preview['error'])) {
        return '<p class="flash bad">' . e((string) $preview['error']) . '</p>';
    }
    $processor = (string) ($preview['processor'] ?? '');
    $file = (string) ($preview['file'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}(-thumb\.jpg|\.jpg)?$/', $file)) {
        return '';
    }
    $baseName = preg_replace('/(-thumb\.jpg|\.jpg)$/', '', $file) ?? '';
    $path = storage_dir('avatars') . '/' . $baseName;
    $flag = null;
    $extra = '';
    if ($processor === 'loose' && is_file($path)) {
        $bytes = file_get_contents($path);
        if (is_string($bytes) && !str_contains($bytes, "\0") && stripos($bytes, '<svg') !== false) {
            $inline = local_only_markup($bytes);
            if (xss_vector($inline)) {
                $flag = award('avatar_loose');
            }
            $extra = '<div class="sink-box">' . $inline . '</div>';
        } elseif (is_file($path) && in_array(finfo_mime($path), ['image/jpeg', 'image/png', 'image/gif'], true)) {
            $extra = '<img alt="" src="/avatar/file?name=' . e($baseName) . '">';
        } else {
            $extra = '<p class="fine">فایل ذخیره شد اما به‌صورت تصویر قابل نمایش نیست و اجرا نمی‌شود.</p>';
        }
    } elseif ($processor === 'standard' && is_file($path)) {
        $meta = local_only_markup(extract_image_metadata($path));
        if (xss_vector($meta)) {
            $flag = award('avatar_meta');
        }
        $thumb = $baseName . '-thumb.jpg';
        $thumbPath = storage_dir('avatars') . '/' . $thumb;
        if (is_file($thumbPath)) {
            $extra .= '<img alt="" src="/avatar/file?name=' . e($thumb) . '">';
        }
        $extra .= '<h3>فراداده</h3><div class="sink-box">' . ($meta === '' ? '<p class="fine">فراداده‌ای خوانده نشد.</p>' : $meta) . '</div>';
    } elseif ($processor === 'strict') {
        $extra = '<img alt="" src="/avatar/file?name=' . e($file) . '"><p class="fine">خروجی مجدد GD است. نام ذخیره‌شده تصادفی است و فراداده منبع در فایل نهایی نیست.</p>';
    }
    return '<section class="card"><h2>آخرین پردازش</h2><dl class="meta">'
        . meta_row('حالت', $processor)
        . meta_row('نام فایل', (string) ($preview['original'] ?? ''))
        . meta_row('پسوند', (string) ($preview['ext'] ?? ''))
        . meta_row('MIME کلاینت', (string) ($preview['mime'] ?? ''))
        . meta_row('حجم', (string) ($preview['size'] ?? ''))
        . '</dl>' . flag_box($flag) . $extra . '</section>';
}

function page_avatar_file(string $mode): void
{
    require_login();
    $name = (string) ($_GET['name'] ?? '');
    if (!preg_match('/^[a-f0-9]{32}(-thumb\.jpg|\.jpg)?$/', $name)) {
        http_response_code(404);
        return;
    }
    $base = realpath(storage_dir('avatars'));
    $path = $base ? realpath($base . '/' . $name) : false;
    if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
        http_response_code(404);
        return;
    }
    $mime = finfo_mime($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'], true)) {
        http_response_code(415);
        header('Content-Type: text/plain; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo 'Not an image';
        return;
    }
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline; filename="avatar.img"');
    header('Cache-Control: no-store');
    header('X-Lab-Header-Profile: loose');
    readfile($path);
}

function page_challenges(string $mode): void
{
    require_login();
    $solved = solved_map();
    $cards = '';
    foreach (challenge_catalog() as $challenge) {
        $code = (string) $challenge['code'];
        $isSolved = isset($solved[$code]);
        $cards .= '<article class="card challenge ' . ($isSolved ? 'solved' : '') . '">
            <p class="kicker">' . e((string) $challenge['category']) . '</p>
            <h2>' . e((string) $challenge['title']) . '</h2>
            <p><code>' . e((string) $challenge['where']) . '</code></p>
            <p>' . e((string) $challenge['hint']) . '</p>'
            . ($isSolved ? flag_box(flag_for_code($code)) : '<p class="fine">هنوز حل نشده است.</p>')
            . '</article>';
    }
    $notes = '';
    foreach (reference_notes() as $note) {
        $notes .= '<li><strong>' . e($note['title']) . '.</strong> ' . e($note['text']) . '</li>';
    }
    $body = page_intro('هر چالش پرچم جدا دارد. مرجع‌های امن پرچم ندارند و برای مقایسه رفتار هستند.')
        . '<div class="grid">' . $cards . '</div><section class="card"><h2>مرجع امن</h2><ul>' . $notes . '</ul></section>';
    render_page('چالش‌ها', $body, $mode);
}

function page_logs(string $mode): void
{
    require_login();
    if (env_string('APP_ENV', '') !== 'training') {
        http_response_code(404);
        render_page('لاگ', page_intro('این صفحه فقط در محیط تمرین روشن است.'), $mode);
        return;
    }
    $result = db()->query('SELECT level, message, created_at FROM lab_logs ORDER BY id DESC LIMIT 80');
    $rows = '';
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows .= '<tr><td>' . e((string) $row['created_at']) . '</td><td>' . e((string) $row['level']) . '</td><td>' . e((string) $row['message']) . '</td></tr>';
        }
        $result->free();
    }
    if ($rows === '') {
        $rows = '<tr><td colspan="3">رویدادی ثبت نشده است.</td></tr>';
    }
    $body = page_intro('خطاهای SQL، رد و قبول آپلود، و حل چالش‌ها اینجا می‌آیند. مقدار پرچم در لاگ پاک می‌شود.')
        . '<table><thead><tr><th>زمان</th><th>سطح</th><th>رویداد</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    render_page('لاگ تمرین', $body, $mode);
}

function api_profile(string $mode): void
{
    require_login();
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        json_out(['error' => 'bad id'], 400);
    }
    $stmt = db()->prepare('SELECT id, username, email, bio, role, created_at FROM users WHERE id = ?');
    if (!$stmt) {
        json_out(['error' => 'unavailable'], 500);
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        json_out(['error' => 'not found'], 404);
    }
    $payload = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'email' => $row['email'],
        'bio' => $row['bio'],
        'role' => $row['role'],
        'created_at' => $row['created_at'],
        'sealed_note' => null,
    ];
    if ((string) $row['username'] === 'vaultkeeper') {
        $payload['sealed_note'] = award('idor');
    }
    json_out($payload);
}

function api_admin_stats(string $mode): void
{
    $user = current_user();
    if (!$user) {
        json_out(['error' => 'auth'], 401);
    }
    if ((string) $user['role'] !== 'admin') {
        log_event('authz', 'stats denied');
        json_out(['error' => 'forbidden'], 403);
    }
    $result = db()->query('SELECT COUNT(*) AS users FROM users');
    $count = 0;
    if ($result) {
        $row = $result->fetch_assoc();
        $count = (int) ($row['users'] ?? 0);
        $result->free();
    }
    json_out(['users' => $count]);
}

function api_admin_notes(string $mode): void
{
    $user = current_user();
    if (!$user) {
        json_out(['error' => 'auth'], 401);
    }
    $flag = award('authz');
    json_out([
        'notes' => [
            ['id' => 4, 'title' => 'یادداشت داخلی', 'body' => $flag],
        ],
    ]);
}

function api_claim_dom(string $mode): void
{
    $user = current_user();
    if (!$user) {
        json_out(['error' => 'auth'], 401);
    }
    $raw = file_get_contents('php://input');
    $data = is_string($raw) ? json_decode($raw, true) : null;
    $sink = is_array($data) ? (string) ($data['sink'] ?? '') : '';
    if (!dom_xss_vector($sink)) {
        json_out(['ok' => false]);
    }
    json_out(['ok' => true, 'flag' => award('xss_dom')]);
}
