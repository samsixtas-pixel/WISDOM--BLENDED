# Deployment configuration

The active XAMPP `/opt/lampp/etc/php.ini` and `/opt/lampp/etc/my.cnf` are outside this project and require administrator access. Review and merge the matching templates before restarting Apache/MySQL:

- `php.ini.wisdom2.example`
- `my.cnf.wisdom2.example`

Use timestamp validation during development. Set `opcache.validate_timestamps=0` only for production deployments with an explicit Apache restart or OPcache reset after every release.

## Required runtime and storage checks

- WISDOM requires PHP 8.1 or newer for the **Apache/web PHP module**. The current XAMPP deployment reports PHP 8.0.30; do not remove or bypass Composer's platform check. Upgrade XAMPP's PHP runtime before serving the application.
- The web worker runs as `daemon`. Keep `storage/` private and grant that account access to the application's required subdirectories; do not use mode `777`.
- XLSX imports need `ext-zip` and `ext-dom`. The XAMPP PHP 8.0 CLI has these extensions, but the application still cannot run there until its PHP runtime meets the minimum.
- The bundled backup script calls `exec()` for `mysqldump`/`tar`. Do not enable the template's `disable_functions` example unchanged if scheduled in-app backups are required; otherwise use a separately managed backup utility.
- Imported workbooks are private in `storage/imports/`. Schedule `bin/cleanup_imports.php` daily to remove staged workbooks older than 30 days. Schedule `bin/backup.php` daily only after granting the CLI job access to the app configuration and private storage.
