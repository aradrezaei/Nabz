<?php
declare(strict_types=1);

function challenge_catalog(): array
{
    return [
        [
            'code' => 'sqli_post',
            'category' => 'SQL Injection',
            'title' => 'جزئیات پست',
            'where' => '/posts/view?id=',
            'hint' => 'شناسه از انتهای شرط منتشرشده خوانده می‌شود. خطای MySQL روی همین صفحه می‌آید. توکن فقط داخل جدول lab_vault است.',
        ],
        [
            'code' => 'sqli_search',
            'category' => 'SQL Injection',
            'title' => 'جستجو',
            'where' => '/search?q=',
            'hint' => 'خروجی دو ستون است و بعضی واژه‌های SQL فقط یک‌بار حذف می‌شوند. توکن داخل جدول search_locker است. جزئیات خطا در لاگ تمرین است.',
        ],
        [
            'code' => 'sqli_blind',
            'category' => 'SQL Injection',
            'title' => 'بازیابی پین',
            'where' => '/login/recover',
            'hint' => 'ورود اصلی prepared statement است. اینجا فقط وضعیت پیدا شدن حساب و پذیرش پین برمی‌گردد. کاربر هدف auditor است و یک payload ساده نشست مدیریت نمی‌سازد.',
        ],
        [
            'code' => 'xss_reflected',
            'category' => 'XSS',
            'title' => 'بازتاب جستجو',
            'where' => '/search?q=',
            'hint' => 'عبارت جستجو در چند جا چاپ می‌شود و همه یکسان فیلتر نشده‌اند. فیلتر را با مقایسه ورودی و خروجی بسنجید.',
        ],
        [
            'code' => 'xss_stored',
            'category' => 'XSS',
            'title' => 'دیدگاه ذخیره‌شده',
            'where' => '/posts/view?id=1',
            'hint' => 'بیو در پروفایل escape می‌شود. رندر دیدگاه مسیر دیگری است و حذف واژه‌ها کامل نیست.',
        ],
        [
            'code' => 'xss_dom',
            'category' => 'XSS',
            'title' => 'Sink سمت مرورگر',
            'where' => '/search?dom=',
            'hint' => 'یک پارامتر فقط در جاوااسکریپت خوانده می‌شود. کنار sink ناامن، یک خروجی با textContent وجود دارد. عنصر script که با innerHTML درج شود اجرا نمی‌شود.',
        ],
        [
            'code' => 'upload_ext',
            'category' => 'Upload',
            'title' => 'فهرست ناقص پسوند',
            'where' => '/upload',
            'hint' => 'حالت extension فقط پسوند نهایی را با یک فهرست کوتاه مقایسه می‌کند. فایل در این آزمایشگاه اجرا نمی‌شود؛ عبور از اعتبارسنج یعنی موفقیت.',
        ],
        [
            'code' => 'upload_mime',
            'category' => 'Upload',
            'title' => 'MIME و پسوند',
            'where' => '/upload',
            'hint' => 'حالت mime هر دو را می‌بیند، اما نحوه ترکیب شرط را از رفتار پذیرش و رد بفهمید. نوع اعلام‌شده توسط کلاینت با محتوای واقعی یکی نیست.',
        ],
        [
            'code' => 'avatar_loose',
            'category' => 'Avatar',
            'title' => 'پیش‌نمایش سهل‌گیر',
            'where' => '/avatar',
            'hint' => 'حالت loose فایل تصویری را از روی نام یا نوع اعلام‌شده می‌پذیرد و بعضی محتواها را مستقیم در صفحه می‌گذارد.',
        ],
        [
            'code' => 'avatar_meta',
            'category' => 'Avatar',
            'title' => 'فراداده تصویر',
            'where' => '/avatar',
            'hint' => 'حالت standard تصویر واقعی می‌خواهد، بندانگشتی GD می‌سازد، و کامنت JPEG یا tEXt فایل PNG را جداگانه نشان می‌دهد.',
        ],
        [
            'code' => 'idor',
            'category' => 'Access',
            'title' => 'خروجی پروفایل',
            'where' => '/api/profile?id=',
            'hint' => 'صفحه HTML و خروجی JSON یک فیلدها را ندارند. فهرست کاربران همه شناسه‌ها را نشان نمی‌دهد.',
        ],
        [
            'code' => 'authz',
            'category' => 'Access',
            'title' => 'یادداشت پنل',
            'where' => '/admin',
            'hint' => 'نقش روی یک مسیر چک می‌شود و روی مسیر دیگر نه. اسکریپت همان صفحه را بخوانید. پرچم با ورود به حساب admin به دست نمی‌آید.',
        ],
        [
            'code' => 'csrf',
            'category' => 'CSRF',
            'title' => 'فرم قدیمی بیو',
            'where' => '/profile/edit',
            'hint' => 'کوکی نشست SameSite=Lax است. فرم قدیمی GET است. نشان اثبات هر حساب روی صفحه ویرایش است. مبدأ دوم باید 127.0.0.1:8081 باشد و خود آزمایشگاه با localhost:8080 باز شود.',
        ],
        [
            'code' => 'predictable',
            'category' => 'Access',
            'title' => 'پیش‌نویس',
            'where' => '/posts/compose',
            'hint' => 'مطالب منتشرشده از مسیر امن فهرست می‌شوند. شناسه پیش‌نویس در منبع ویرایشگر است و مشاهده آن از مسیر دیگری انجام می‌شود.',
        ],
    ];
}

function reference_notes(): array
{
    return [
        ['title' => 'فهرست کاربران', 'text' => 'جستجوی /users با prepared statement و escape خروجی است.'],
        ['title' => 'ورود', 'text' => 'گذرواژه با password_hash ذخیره می‌شود و ورود اصلی پارامتر را به query نمی‌چسباند.'],
        ['title' => 'آپلود سخت‌گیر', 'text' => 'allowlist پسوند، MIME واقعی، کدگذاری مجدد GD، نام تصادفی، ذخیره بیرون از web root و دانلود به‌صورت پیوست.'],
        ['title' => 'آواتار سخت‌گیر', 'text' => 'فقط JPEG/PNG/GIF واقعی پذیرفته می‌شود و فراداده در خروجی مجدد باقی نمی‌ماند.'],
        ['title' => 'تغییر ایمیل', 'text' => 'فقط POST و توکن CSRF نشست.'],
        ['title' => 'آمار مدیریت', 'text' => '/api/admin/stats نقش admin را چک می‌کند.'],
        ['title' => 'هدر سخت‌گیر', 'text' => 'ورود، چالش‌ها، کاربران و پنل مدیریت CSP، X-Frame-Options و nosniff دارند.'],
    ];
}
