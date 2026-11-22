# JEWELSETU — website + Q-TRACK

| URL | What |
|---|---|
| `/` | Public website (jewelsetuqms.in design) — Home, Services, Q-Track Portal, Knowledge Hub, About, Book an Audit, /verify |
| `/admin` | Website CMS — dashboard, blog articles, audit leads, PDF downloads, lead magnets, all page content, settings (Super Admin / Back Office login) |
| `/erp` | Q-TRACK ERP (below). Old links like `/login`, `/app`, `/dashboard` redirect to `/erp/...` |
| `/api` | PHP JSON API shared by both |

Website source: `frontend/site` (React) → `npm run build:site`. `www/index.php` serves it and adds per-page SEO / OpenGraph tags, `sitemap.xml`, `robots.txt`.
Website data lives in the same MySQL database (`site_*` tables, created automatically).


Multi-portal jewellery QC inspection, reporting & analytics — Internal portal (Super Admin, Back Office, QC Manager), Inspector mobile app (PWA, works offline), Client portal and Vendor portal.

## Tech stack
| Layer | Technology |
|---|---|
| Frontend | **React 19 + React Router** (Vite build) — single-page app, inspector PWA with offline queue |
| Backend | **PHP 8 JSON API** (no framework) — runs on any Linux shared hosting |
| Database | **MySQL / MariaDB** (SQLite for local dev) — tables auto-created on first request |
| PDF / email | Dompdf + PHPMailer, bundled in `www/server/lib` (no Composer needed) |

## Folder layout
```
frontend/         React source  (npm install && npm run build → builds the ERP into www/erp and the website into www/)
  src/            Q-TRACK ERP          site/   public website + /admin
www/              ← THIS is what goes on the server (upload to public_html)
  index.php, index.html, assets/   website (built)      erp/   Q-TRACK ERP (built)
  api/index.php   JSON API entry (/api/...)
  server/         PHP code, config, lib, storage/, uploads/   (blocked from the web by .htaccess)
  .htaccess       routes /api → PHP, /erp → ERP app, everything else → index.php (website)
```

## Deploy on cPanel / Hostinger shared hosting
1. cPanel → MySQL Databases: create a database + user.
2. Upload **the contents of `www/`** into `public_html/` (or the sub-domain folder, e.g. `qtrack.jewelsetu.com`).
3. Copy `server/config.local.example.php` → `server/config.local.php`, fill in DB name/user/password and `APP_URL`; set `SEED_DEMO` to `'0'` for a clean install.
4. Open the site — tables are created automatically. Login `admin@jewelsetu.com` / `Admin@123` and change the password.
5. Make `server/uploads/` and `server/storage/` writable (755/775).
6. Settings → Email: choose *PHP mail()* or SMTP.

PHP extensions needed: pdo_mysql, gd, zip, mbstring, dom (default on cPanel). Apache `mod_rewrite` must be on (default).
Installing in a sub-folder (e.g. `/qtrack/`): build with `VITE_BASE=/qtrack/ npm run build` and set `RewriteBase /qtrack/` in `.htaccess`.

## Changing the frontend
```
cd frontend
npm install
npm run dev      # http://localhost:5173 (proxies /api to http://127.0.0.1:8090)
npm run build    # writes the production build into ../www
```
Local API for dev: `DB_DRIVER=sqlite php -S 127.0.0.1:8090 -t www dev-router.php`

## Railway / Docker
`Dockerfile` serves `www/` with Apache + PHP 8.3. Variables: `MYSQLHOST, MYSQLPORT, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE` (Railway MySQL references), `SEED_DEMO=1` for demo data. Mount a volume at `/var/www/html/server/uploads` for photos & PDFs.

## Roles
| Role | Lands on |
|---|---|
| Super Admin / Back Office / QC Manager | `/erp/dashboard` |
| QC Inspector | `/erp/app` (mobile PWA — install via “Add to Home Screen”) |
| Client user | `/erp/client` |
| Vendor user | `/erp/vendor` |

SKU **Hold for approval**: inspector pauses one SKU for client or vendor confirmation → they answer in *Approvals* → inspector is notified and resumes.
