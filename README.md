# CampusBite

CampusBite is a campus food ordering site for students, campus kitchens and administrators. It uses PHP sessions and PDO, MySQL 8.4, HTML, Bootstrap 5.3.8 and vanilla JavaScript.

## Run with Docker Compose

From this directory, run:

```sh
docker compose up --build -d
docker compose exec web php bin/install.php --demo
```

Open `http://127.0.0.1:8080/`. See [the one-page tester guide](docs/TESTER_GUIDE.md) for accounts and tasks. The default port is bound to this machine. Set `CAMPUSBITE_WEB_PORT` in `.env` if 8080 is occupied.

## Run with an existing PHP and MySQL installation

Use PHP 8.2 or later with `pdo_mysql` enabled and MySQL 8.4. Create an empty `campusbite` database and a MySQL user with privileges on that database. Copy `config.example.php` to `config.local.php` and enter the connection details. Environment variables `CAMPUSBITE_DB_HOST`, `CAMPUSBITE_DB_PORT`, `CAMPUSBITE_DB_DATABASE`, `CAMPUSBITE_DB_USERNAME` and `CAMPUSBITE_DB_PASSWORD` override the corresponding values.

```sql
CREATE DATABASE campusbite CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```sh
php -m
php bin/install.php --demo
php -S 127.0.0.1:8080 -t public
```

Use `public/` as the document root when configuring Apache or another web server. Only files under `public/` need to be reachable from a browser. PHP and MySQL Windows distributions are available from the project maintainers. [PHP for Windows](https://www.php.net/~windows/) · [MySQL Community Server](https://dev.mysql.com/downloads/mysql/8.4.html?os=3)

## Provision a fresh installation

For an installation with real accounts, create an empty database and set `CAMPUSBITE_ADMIN_USERNAME`, `CAMPUSBITE_ADMIN_EMAIL` and `CAMPUSBITE_ADMIN_PASSWORD` in the environment of the installer. Run `php bin/install.php` without `--demo`. For Compose, define those variables in the local `.env` and run `docker compose exec web php bin/install.php`. A second run preserves the administrator and existing data. The installer creates the schema and the initial administrator. [Installation script](bin/install.php)

For partner testing, use a separate database seeded with demo records. Run behind the team's PHP/MySQL host or lab network. To allow lab devices to reach a Compose deployment, set `CAMPUSBITE_BIND` to the intended lab interface address and share its URL with the group. Set custom Compose database passwords before creating its volume. For HTTPS behind a reverse proxy, set `session_secure` to `true` in the host configuration, or set `CAMPUSBITE_SESSION_SECURE=1` in the environment. The PHP development server is for local development.

## Reset test data

For a fresh Compose test cycle, stop the current web stack with `docker compose stop`, then run `docker compose -p campusbite-cycle2 up --build -d` and `docker compose -p campusbite-cycle2 exec web php bin/install.php --demo`. Each new project name creates a fresh volume and keeps the previous data available. Set a different web port if the previous stack must stay online. With native MySQL, create a new empty database, point the app at it, and rerun the demo installer. The demo installer checks that the target database starts with no users or kitchens.

## Features and source

| Profile | Feature | Implementation |
| --- | --- | --- |
| F01 | Student registration and role-based sign-in | `app/accounts.php` |
| F02 | Menu search and kitchen/category filters | `app/ordering.php`, `app/student-view.php` |
| F03 | Persistent single-kitchen cart | `app/ordering.php` |
| F04 | Pickup and campus delivery checkout | `app/ordering.php` |
| F05 | Student order details and status history | `app/order-view.php`, `app/ordering.php` |
| F06 | Vendor dishes, prices, categories and availability | `app/management.php` |
| F07 | Vendor order queue and status updates | `app/ordering.php`, `app/workspace-view.php` |
| F08 | Admin student, kitchen and vendor account management | `app/management.php`, `app/workspace-view.php` |

Checkout checks the current cart against the displayed prices, stores order and line-item snapshots in a transaction, and gives each submission a per-student unique key. Vendor status updates and history entries are transactional. Staff accounts are assigned to kitchens by the administrator. Disabled kitchens disappear from the student menu and their historical orders remain visible to the students who made them.

The demo uses English and Malaysian ringgit. The course profile and development plan describe the registration rules. The SA2 C++ source was unavailable for a line-by-line port. [Database schema](database/schema.sql) · [Demo data](database/seed.sql)

The locally bundled Bootstrap CSS is MIT licensed. The built-in ImageGen prompts and resulting assets are recorded below. [Bootstrap license](https://github.com/twbs/bootstrap/blob/v5.3.8/LICENSE)

- Hero image prompt. An editorial food photograph of glazed Korean-style chicken, rice and vegetables in an ivory bowl on a deep evergreen tabletop, with directional window light and no lettering. [`campus-bowl.webp`](public/assets/campus-bowl.webp)
- Menu image prompt. A three-column, two-row photographic contact sheet of a Korean chicken bowl, sesame tofu bowl, gyoza, chicken Caesar wrap, caprese toastie and iced matcha latte, with consistent window light and no lettering. [`menu-photos.webp`](public/assets/menu-photos.webp)
