# SWAssist

SWAssist is a PHP and MariaDB documentation workspace for Social Work students and OJT teams. It organizes case records, family composition, treatment plans, progress notes, and printable Social Case Study Reports. It stores and formats information entered by users; it does not make professional social-work decisions.

## Features

- Session login, password hashing, registration, roles, CSRF protection, and case ownership checks
- Case creation, editing, archive status, search, filtering, and dashboard statistics
- Seven-section Social Case Study Report editor with repeatable family and treatment-plan rows
- Activity/progress log linked to each case
- Print-friendly report preview
- Case list previews available by hover, keyboard focus, or mobile tap
- Downloadable HTML case-study reports from the case list
- QR utility for survey URLs without storing URLs
- Prepared statements, output escaping, ignored environment credentials, and normalized tables

## Requirements and setup

1. Apache with PHP 8.3, MariaDB 10.11, and the PHP `mysqli` extension.
2. Create the `swassist` database and database user, or use the existing project database.
3. Copy `.env.example` to `.env` and set the local database values. `.env` is ignored by Git.
4. Import the schema:

	`mysql -u swassist_user -p swassist < database/schema.sql`

5. Optional: import fictional demo data:

	`mysql -u swassist_user -p swassist < database/seed.sql`

6. Point Apache's document root at this directory. The application is available at `http://swassist.local/` when that virtual host is configured. For a quick local check, run `php -S 127.0.0.1:8099 -t .`.

## Demo accounts

These accounts exist only after importing `database/seed.sql`:

- `demo_admin` / `AdminDemo123!`
- `demo_supervisor` / `SupervisorDemo123!`
- `demo_student` / `StudentDemo123!`

Change or remove demo accounts before using the system with real data.

## Security notes

Client records should be anonymized during development. Do not commit `.env`, uploaded files, real client data, passwords, or sensitive data to Git. Upload metadata tables and the ignored `uploads/` directory are prepared for future authenticated file delivery; direct file upload handling is intentionally not exposed until an authenticated download controller is added. In production, use HTTPS, restrictive PHP/Apache upload settings, backups, and access logging that excludes client content.

The QR preview uses the public QR Server image endpoint, so users should avoid putting confidential information in QR URLs.

## Development notes

The application intentionally uses a small front controller (`index.php`), shared procedural helpers, mysqli prepared statements, and no framework. The case-study editor is separated into `case-study.php`; the normalized schema keeps family members and treatment rows out of the main case record.

Future improvements include authenticated attachment delivery, supervisor review notes, activity edit/archive screens, and a local QR library for installations without outbound network access.
