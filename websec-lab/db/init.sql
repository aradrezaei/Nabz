SET NAMES utf8mb4;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(32) NOT NULL UNIQUE,
  email VARCHAR(120) NOT NULL,
  password VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) NULL,
  bio VARCHAR(500) NOT NULL DEFAULT '',
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  listed TINYINT(1) NOT NULL DEFAULT 1,
  support_pin VARCHAR(16) NULL,
  csrf_proof CHAR(16) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
  id INT UNSIGNED PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(160) NOT NULL,
  body TEXT NOT NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE comments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  post_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  body VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_vault (
  id INT UNSIGNED PRIMARY KEY,
  slot VARCHAR(64) NOT NULL,
  token VARCHAR(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE search_locker (
  id INT UNSIGNED PRIMARY KEY,
  label VARCHAR(64) NOT NULL,
  secret VARCHAR(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE solves (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  flag_code VARCHAR(64) NOT NULL,
  solved_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_solve (user_id, flag_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  level VARCHAR(16) NOT NULL,
  message VARCHAR(500) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE uploads (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  stored_name CHAR(32) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  client_mime VARCHAR(128) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  mode VARCHAR(16) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (username, email, password, bio, role, listed, support_pin, csrf_proof) VALUES
('alice', 'alice@range.local', '$2y$10$zlJALPBrBAkuSue8GH5XJep5p.VN5DZQUm7zZBlK0GaDBpDlHXFKe', 'مسئول شیفت صبح دفتر تمرین.', 'user', 1, NULL, 'a91c0e44b7d218f0'),
('bob', 'bob@range.local', '$2y$10$Cb.bPZzDpVoRrZEpdEXgBuQDe0nR0xjvFseBqgVKOD0zaonkhExSK', 'یادداشت‌های جستجو را مرتب می‌کند.', 'user', 1, NULL, 'b18d44aa091c77e2'),
('carol', 'carol@range.local', '$2y$10$ytZcWi4OBnFUE1sGPl3OLuVq9y/EUWZ6k78F895CUUGBxBytQNksy', 'آرشیو پست‌های منتشرشده را می‌خواند.', 'user', 1, NULL, 'c77e15a02b8891aa'),
('dana', 'dana@range.local', '$2y$10$0.vMw5s.VbGWROCIiredDORsGSzIjr/bQaTW8jwKyMo/rej6O8Ewi', 'فرم‌های پروفایل را با کاربران جدید تمرین می‌کند.', 'user', 1, NULL, 'd33bc8055de21770'),
('admin', 'admin@range.local', '$2y$10$2aoqz.PN1Qom2T.782.ix.o24bULyQhBLb6wsztwQ3KVQPwv9Qle.', 'حساب مدیریت آزمایشگاه.', 'admin', 0, NULL, 'e12ab4488cd1944e'),
('auditor', 'auditor@range.local', '$2y$10$WM2tpdT9ESVpim4lFC2raesQFtfA8kAn9BjYmnbNykWQz3fEG0dsi', 'بازیابی پین فقط از میز پشتیبانی.', 'user', 0, 'K7mQ2pLx', 'f44ef6366aa0819c'),
('vaultkeeper', 'vaultkeeper@range.local', '$2y$10$gmJYt5WvFJktleyJqhmNG.rHeJJomhIBjJTk5yK.8GObwyjAD3Qk6', 'نگهدارنده یادداشت مهرشده.', 'user', 0, NULL, 'a55de2177f0ab12c');

INSERT INTO posts (id, user_id, title, body, published) VALUES
(1, 1, 'خوش‌آمد به دفتر تمرین', 'این پست عمومی است. شناسه در نوار آدرس همان چیزی است که سرویس جزئیات می‌خواند.', 1),
(2, 2, 'جستجوی داخلی', 'فهرست کاربران منتشرشده از صفحه کاربران می‌آید. همه رکوردها آنجا نیستند.', 1),
(3, 3, 'یادداشت آپلود', 'فایل‌های تمرینی بیرون از ریشه وب نگه داشته می‌شوند و اجرا نمی‌شوند.', 1),
(4, 4, 'هدرها یکسان نیستند', 'بعضی صفحه‌ها سیاست سخت‌گیرانه دارند و بعضی عمداً ساده‌ترند. پاسخ را مقایسه کنید.', 1),
(5, 2, 'پیش‌نویس‌ها در فهرست نیستند', 'ویرایشگر پیش‌نویس مسیر دیگری برای دیدن یک مطلب دارد.', 1),
(1337, 5, 'پیش‌نویس داخلی', 'این متن منتشر نشده است و در فهرست مطالب دیده نمی‌شود.', 0);

INSERT INTO comments (post_id, user_id, body) VALUES
(1, 2, 'شیفت عصر هم همین دفتر را می‌خواند.'),
(2, 3, 'خروجی امن و خروجی بازتابی را کنار هم بگذارید.');

INSERT INTO lab_vault (id, slot, token) VALUES
(1, 'sqli_post', 'FLAG{SQLI_GET_UNION_7f3a1c}');

INSERT INTO search_locker (id, label, secret) VALUES
(1, 'sqli_search', 'FLAG{SQLI_SEARCH_DOUBLE_b91c4e}');
