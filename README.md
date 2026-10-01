# WISDOM — student registration, fees & exams system

A restructured, object-oriented PHP/MySQLi backend for the WISDOM training-
programme portal. Students register, choose subjects, submit fee payment
proof, and view published results. Administrators approve payments, record
exams, search accounts, and run reports.

## What changed from the original prototype

The original app was five flat scripts sharing one PDO connection function
and raw HTML mixed with SQL. It worked for the happy path but had no
structure to build on and several real security gaps. This version keeps
the same features and adds:

- **Layered architecture** — `Models` (domain objects) → `Repositories`
  (all SQL, via MySQLi prepared statements) → `Services` (business rules,
  validation) → `public/*.php` (thin controllers: read input, call a
  service, render a view). No SQL and no business logic lives in a page.
- **OOP demonstrated deliberately, not decoratively**:
  - *Encapsulation* — `User` keeps its password hash and raw state private;
    everything goes through methods (`verifyPassword()`, `can()`, etc.).
  - *Abstraction* — `Wisdom\Contracts\RepositoryInterface` and
    `ReportInterface` define what a repository/report must do, not how.
  - *Inheritance* — `Student`, `Admin`, and `Secretary` extend the abstract `User`;
    every concrete report extends `AbstractReport`.
  - *Polymorphism* — `User::fromRow()` returns a `Student` or `Admin`
    depending on the row, and each overrides `permissions()`/`roleLabel()`
    differently; each report overrides `rows()`/`headers()` differently
    while sharing one `toCsv()` implementation.
  - *Constructors* — every model and service takes its dependencies/state
    through the constructor (constructor promotion throughout).
- **Security hardening**:
  - Application connects as a least-privilege `wisdom_app` DB user, never
    `root`.
  - `config/`, `src/`, `database/`, `storage/` sit **outside** the web
    root; only `public/` is served (the original exposed `config.php`,
    with DB credentials, directly under the document root).
  - Sessions: `HttpOnly` + `SameSite=Lax` cookies, strict mode, ID
    regeneration on login, idle + absolute timeouts, user-agent binding.
  - CSRF tokens on every state-changing form, checked with `hash_equals()`.
  - Every query is a MySQLi prepared statement — including the admin
    search box, which escapes `LIKE` wildcards and binds the search term.
  - Login throttling (per email and per IP) against brute-forcing.
  - Uploaded payment proofs are validated by real file content
    (`finfo`), renamed to random filenames, stored outside the web root,
    and served only through `payment_proof.php` after an admin-permission
    check — never linked to directly.
  - Global security headers + a Content-Security-Policy with a per-request
    nonce for the one inline `<script>` block.
  - Centralised error handling: real errors go to `storage/logs/app.log`;
    users only ever see a generic message (stack traces never reach the
    browser in production).
  - Password policy (min length + letter/number) and bcrypt cost 12, with
    automatic rehashing if the cost parameter is ever increased later.
- **Fixed bugs from the original**:
  - The dashboard linked to `results.php` and `help.php`, neither of
    which existed. Replaced with a working `settings.php` (password
    change) and consolidated exam results into `exams.php`.
  - Registered users' `subjects` catalogue was hard-coded in PHP with no
    server-side source of truth; it now lives in the `subjects` table.
  - Payment review updated `is_approved` for *any* category, including
    examination-fee payments — which meant an approved exam payment
    would silently re-approve/re-lock the *account*. Now only
    `programme`-category payments touch account approval.
- **New capabilities requested**: role-based `Admin`/`Student`/`Secretary` classes,
  live/real-time user search (`admin_users_search.php`, polled via
  `fetch()` with debounce as the admin types), and a reporting module
  (`reports.php`) with four reports (enrollment, revenue, exam
  performance, audit trail) exportable as CSV.

## Requirements

- PHP 8.1+ with the `mysqli`, `fileinfo`, and (recommended) `mbstring`
  extensions. The PHP version used by the web server must meet this minimum;
  a newer CLI PHP does not upgrade XAMPP Apache's PHP module.
- MySQL 5.7+/MariaDB 10.4+.

## Setup

1. Import the schema:
   ```bash
   mysql -u root -p < database/schema.sql
   ```
2. Create a least-privilege database user (replace the password):
   ```sql
   CREATE USER 'wisdom_app'@'localhost' IDENTIFIED BY 'choose-a-strong-password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON wisdom_db.* TO 'wisdom_app'@'localhost';
   ```
3. Copy `.env.example` to `.env` and fill in the real `DB_PASS` (and any
   other overrides). **Never commit `.env`.**
4. Point your web server's document root at `public/` — not the project
   root. With PHP's built-in server, for local development only:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```
5. Create the first administrator account:
   ```bash
   php bin/create_admin.php "Your Name" you@example.com "ChoosePassword1"
   ```
6. Visit `/register.php` to create a student account, or `/login.php` to
   sign in as the administrator you just created.

The administration workspace is split across the dashboard, user directory,
Add User, payments, examinations, notices, and password-reset pages. Excel
imports are feature-flagged; privately staged workbooks should be removed
after 30 days with `php bin/cleanup_imports.php` from a scheduled job.

## Project layout

```
config/      Environment loading, DI container boot (config.php, bootstrap.php)
src/
  Core/      Database (MySQLi), Session, Csrf, Validator, Guard, App (container)
  Contracts/ RepositoryInterface, ReportInterface
  Models/    User (abstract), Student, Admin, Payment, Exam
  Repositories/  One class per table; the only place SQL is written
  Services/  Business rules: AuthService, RegistrationService, FeeService,
             PaymentService, ExamService, UserService, ReportService, ...
  Reports/   AbstractReport + four concrete reports
database/    schema.sql (tables + seed subject catalogue)
bin/         create_admin.php (CLI)
public/      The web root: one thin script per page/route, plus CSS/images
storage/     Uploaded payment proofs and application logs (not web-accessible)
```

## Notes for future work

- `ReportService::available()` rebuilds report objects on every call,
  which is cheap here (they're stateless) but worth caching if more
  reports are added.
- Configure and measure PHP OPcache and Apache caching on the deployment
  runtime; page-load targets must be verified on the production-like server.