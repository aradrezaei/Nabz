# رِنج — آزمایشگاه محلی امنیت وب

میدان تمرین خودکفا برای سطح Medium. برنامه فقط روی همین ماشین و داخل Docker بالا می‌آید و به سرویس، API یا دامنه بیرونی وصل نمی‌شود.

آسیب‌پذیری‌ها عمدی و محدود به همین برنامه هستند. پرچم‌ها در HTML اولیه و جاوااسکریپت نیستند. آپلود، حتی پس از دور زدن اعتبارسنج، به‌عنوان کد اجرا نمی‌شود و از کانتینر خارج نمی‌شود.

## اجرا

```bash
cd websec-lab
docker compose up --build
```

- آزمایشگاه: http://localhost:8080
- مبدأ دوم برای CSRF و مقایسه قاب: http://127.0.0.1:8081

آزمایشگاه را با `localhost` باز کنید. مبدأ دوم را با `127.0.0.1` باز کنید. هر دو پورت فقط روی loopback منتشر می‌شوند.

برای ساخت دوباره پایگاه:

```bash
docker compose down -v
docker compose up --build
```

## حساب‌ها

| کاربر | گذرواژه | نقش |
| --- | --- | --- |
| alice | AmberLane#1 | user |
| bob | BrightPath#2 | user |
| carol | CedarNorth#3 | user |
| dana | DeltaWest#4 | user |

حساب‌های `admin`، `auditor` و `vaultkeeper` در پایگاه هستند. گذرواژه آن‌ها در رابط کاربری نیست. ورود معمولی گذرواژه را با `password_hash` چک می‌کند.

## چالش‌ها

پیشرفت هر کاربر در `/challenges` ثبت می‌شود. راهنما فقط محل نگاه کردن را می‌گوید.

| کد | موضوع | محل |
| --- | --- | --- |
| sqli_post | SQL Injection در GET | `/posts/view?id=` |
| sqli_search | SQL Injection در جستجو | `/search?q=` |
| sqli_blind | SQL Injection در بازیابی پین | `/login/recover` |
| xss_reflected | Reflected XSS | `/search?q=` |
| xss_stored | Stored XSS | دیدگاه پست |
| xss_dom | DOM XSS | `/search?dom=` |
| upload_ext | اعتبارسنج ناقص پسوند | `/upload` |
| upload_mime | ترکیب ناقص MIME و پسوند | `/upload` |
| avatar_loose | پیش‌نمایش ناامن تصویر | `/avatar` و `/profile/edit` |
| avatar_meta | فراداده JPEG/PNG | `/avatar` |
| idor | خروجی پروفایل | `/api/profile?id=` |
| authz | کنترل دسترسی پنل | `/admin` |
| csrf | تغییر بیو | `/profile/edit` و مبدأ دوم |
| predictable | شناسه پیش‌نویس | `/posts/compose` |

مسیرهای امن برای مقایسه: جستجوی `/users`، ورود اصلی، حالت secure آپلود، حالت strict آواتار، تغییر ایمیل با توکن CSRF، و `/api/admin/stats`.

## هدرها و کوکی

صفحه ورود، چالش‌ها، کاربران و مدیریت هدر سخت‌گیر دارند. جستجو، جزئیات پست، آپلود و ویرایش پروفایل عمداً ساده‌ترند. نشان `X-Lab-Header-Profile` فقط نام پروفایل هدر است.

کوکی نشست `HttpOnly` و `SameSite=Lax` است. کوکی `range_pref` از اسکریپت قابل خواندن است و پرچم ندارد.

لاگ تمرین در `/debug/logs` فقط وقتی `APP_ENV=training` باشد روشن است و مقدار پرچم را نگه نمی‌دارد.

## محدودیت عمدی

- دستور سیستم، شِل و include از آدرس بیرونی غیرفعال است.
- `open_basedir` به `/var/www` و `/tmp` محدود است.
- فایل آپلودی بیرون از web root است و فقط به‌صورت پیوست `application/octet-stream` برمی‌گردد.
- پرس‌وجوی چنددستوری و خواندن فایل از MySQL در مسیرهای تزریق حذف می‌شود.
- موفقیت هر چالش با پرچم داخل برنامه مشخص می‌شود، نه با اجرای کد روی میزبان.
