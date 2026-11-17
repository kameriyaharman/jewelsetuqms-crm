# JEWELSETU Q-TRACK

Multi-portal jewellery QC inspection, reporting & analytics — Internal portal (Super Admin, Back Office, QC Manager), Inspector mobile app (PWA, offline), Client portal and Vendor portal.

**Stack (built for Linux shared hosting):** PHP 8.1+ · MySQL/MariaDB · plain PHP (no framework, no build step) · Dompdf + PHPMailer bundled in `lib/` (no Composer needed) · vanilla JS PWA.

## Install on cPanel / Hostinger shared hosting
1. Create a MySQL database + user in cPanel → MySQL Databases.
2. Upload the whole folder into `public_html/` (or a sub-domain folder such as `qtrack.jewelsetu.com`).
3. Copy `config.local.example.php` → `config.local.php` and fill in DB name, user, password and `APP_URL`. Set `SEED_DEMO` to `'0'` for a clean install.
4. Open the site — tables are created automatically. Login: `admin@jewelsetu.com` / `Admin@123` → change the password in Profile.
5. Make sure `uploads/` and `storage/` are writable (755/775).
6. Settings → Email: choose *PHP mail()* (works on most shared hosts) or SMTP (cPanel mailbox / Gmail / SES).
7. PHP extensions required: pdo_mysql, gd, zip, mbstring, dom (all on by default on cPanel).

If installed in a sub-folder, uncomment `RewriteBase` in `.htaccess`. If mod_rewrite is off, URLs also work as `index.php?r=dashboard`.

## Railway / Docker
`Dockerfile` runs Apache + PHP 8.3. Variables: `MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE` (Railway MySQL references), `SEED_DEMO=1` for demo data. Mount a volume at `/var/www/html/uploads` for photos & PDFs.

## Local dev
`DB_DRIVER=sqlite php -S localhost:8080 router.php`

## Roles
| Role | Lands on |
|---|---|
| Super Admin / Back Office / QC Manager | `/dashboard` |
| QC Inspector | `/app` (mobile PWA, installable, offline drafts + sync queue) |
| Client user | `/client` |
| Vendor user | `/vendor` |

SKU **Hold for approval** (from the spec, not in the screen design): inspector pauses one SKU for client or vendor confirmation → the client/vendor answers in *Approvals* → inspector is notified and resumes.
