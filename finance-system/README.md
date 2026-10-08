# FinTrack – Web-Based Financial Record-Keeping System
Stack (per proposal): PHP, MySQL, HTML, CSS, JavaScript on XAMPP.

## Setup
1. Copy this folder to `C:\xampp\htdocs\finance-system` and start Apache + MySQL in XAMPP.
2. Open phpMyAdmin > Import > `database.sql`.
3. Edit DB credentials in `config.php` if needed (default: root, no password).
4. Visit http://localhost/finance-system/ and register an account.

## Features
Register/login/logout, dashboard with balance, add/edit/delete income & expenses, search and filter, reports by period and category with CSV download and print, account management.
Security: hashed passwords, prepared statements, CSRF tokens, session regeneration, per-user data isolation.

## AI Budget helper (budget.php)
Looks at your last 3 months of income and spending and suggests a monthly limit per category, plus savings tips, in Ksh.
1. Get an API key from the Anthropic Console and paste it into `AI_KEY` in `config.php` (or set the `ANTHROPIC_API_KEY` environment variable).
2. Make sure the cURL extension is enabled in XAMPP (`extension=curl` in php.ini). If you get an SSL certificate error, set `curl.cainfo` in php.ini to a CA bundle.
3. Open **AI Budget** in the menu, enter an optional monthly savings target, and click Get budget advice.
Privacy: only category totals are sent to the AI. Your name, email and transaction descriptions are never sent.
Without a key, the page falls back to a basic rule-based budget so it still works offline.

## Forgot password
Login page > "Forgot password?" > enter email > open the reset link > choose a new password.
Links are single-use, expire after 1 hour, and only a SHA-256 hash of the token is stored (table `password_resets`). The page gives the same reply whether or not the email exists.
XAMPP cannot send email by default, so `RESET_DEV_MODE` in `config.php` is `true`: the link is shown on screen (and logged) instead. Set it to `false` on a real server where `mail()` works.
If you already imported an older `database.sql`, import it again: it only adds the new `password_resets` table.

---
# FinTrack v2 – what was added (from the FINTRACK feature list)
**Re-import `database.sql` in phpMyAdmin** (safe on an existing database; needs XAMPP's MariaDB). Then open `register.php` to make a new account, or keep your old one. To become admin, set `is_admin = 1` for your row in the `users` table.

## Built in
- **Profile & region:** currency, country, language (English / Kiswahili / French menus), time zone, Student / Employee / Freelancer / Business / Other profiles, university or organisation name (Settings).
- **Money:** multiple accounts (bank, cash, savings, credit card, mobile wallet), multiple income sources, recurring and scheduled transactions (auto-recorded), split transactions, encrypted notes, receipt attachments, CSV bank-statement import, automatic categorisation + your own rules.
- **Planning:** weekly / monthly / semester / custom budgets, category budgets, rollover, templates, auto allocation, budget vs actual; multiple savings goals with deadlines, priorities, emergency fund, milestones, forecasting; safe-to-spend today / week / month.
- **Smart:** forecasting, what-if simulator (purchase, income, rent, loan, saving), AI assistant (budget, spending analysis, recommendations, weekly/monthly summary, "Can I afford this?", "Where am I overspending?", "How can I save more?", personalised plan), alerts (budget, low balance, bills, goals, unusual spending, recurring) with browser notifications, financial health score, trends and pattern detection.
- **Debt & wealth:** credit-card / student / personal loans, interest, payoff plans (snowball and avalanche), debt-free date, assets, liabilities, net-worth history.
- **Reports:** summary, income, expense, budget, savings, debt, net worth, each as PDF, Excel (.xls) and CSV, plus print.
- **Sharing:** groups for couples, families and roommates with equal expense splitting and permissions (owner / editor / viewer).
- **Student mode:** allowance tracking, tuition and fees, "until next allowance" daily limit, food / transport / accommodation / books tracking.
- **Security & privacy:** strong-password rules, login rate limiting, two-factor authentication (authenticator app), login alerts for new locations, 30-minute session timeout, CSRF, prepared statements, validation, AES-256 encryption of notes, audit log, secure password reset, data export, account deletion, AI-sharing control.
- **Business side:** Free and Premium plans (demo billing, no real payment), admin dashboard, support tickets, feedback, product analytics, page-speed sampling, error log, JSON backup.
- **Installable & offline:** it is a Progressive Web App (use "Add to Home screen"); pages you have opened still load offline in read-only mode.

## Setup notes
- Receipt scanning (OCR) and the AI assistant / AI budget need an Anthropic key in `config.php` and the **Premium** plan (Plans page, demo switch). Without them the assistant and budget fall back to simple rules.
- `.enckey` is created on first use and encrypts transaction notes. **Back it up and never share it.** If it is lost, old notes cannot be read.
- The `uploads/` folder stores receipts; it blocks script execution via `.htaccess` (needs Apache `AllowOverride All`, the XAMPP default).
- Automatic backups: schedule `mysqldump -u root finance_db > backup.sql` with Windows Task Scheduler or cron.

## Not built (needs outside services or a different kind of project)
Native Android and iOS apps (use the installable web app), live bank connections and automatic bank balance sync (needs a provider such as Plaid or Open Banking; CSV import is included instead), real push notifications when the app is closed (needs a push server), cloud sync across offline edits, real payment processing, multi-currency conversion, organisation wellness programmes, and cloud hosting / scaling / monitoring infrastructure.
