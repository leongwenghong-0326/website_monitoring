# Website Monitoring System with Telegram Alerts

Admin-only PHP + MySQL monitoring app for XAMPP. It checks websites, stores logs, sends Telegram alerts on status changes, and includes a public status page similar to [UptimeRobot status pages](https://stats.uptimerobot.com/rjqsmVv9qb).

## What you get

- Admin login (session + hashed password, show/hide password, forgot password)
- Add / edit / delete websites and intervals
- Dashboard with UP / DOWN counts, recent alerts, latest checks
- Search and filters (name, URL, UP, DOWN, today, last 7 days)
- Automatic monitoring engine (`cron/monitor.php`)
- Telegram alerts only when status changes (DOWN, recovery UP, optional slow warning)
- Public status page with overall status, 90-day uptime bars, and recent incidents

## URLs (after Apache + MySQL are running)

| Page | URL |
|---|---|
| Installer | http://localhost/website_monitoring/install.php |
| Admin login | http://localhost/website_monitoring/admin/login.php |
| Public status page | http://localhost/website_monitoring/status/ |

## 1. XAMPP setup

1. Start **Apache** and **MySQL** in XAMPP.
2. Confirm this project is at `C:\xampp\htdocs\website_monitoring`.
3. Open http://localhost/website_monitoring/install.php
4. Use the default XAMPP database values unless you changed them:
   - Host: `localhost`
   - Database: `website_monitoring`
   - Username: `root`
   - Password: *(empty)*
5. Create your admin username and password (minimum 6 characters).
6. Telegram fields are optional during install. You can add them later in **Settings**.
7. Save the **password reset key** and **cron key** shown at the end.

If the installer already ran, delete `install.lock` only if you really want to install again.

### Manual database import (optional)

If you prefer phpMyAdmin instead of the installer:

1. Import `sql/schema.sql`.
2. Edit `config/database.php` with your MySQL details.
3. Insert an admin password hash and settings rows yourself, then create `install.lock`.

The installer is the easier path.

## 2. First use

1. Log in at http://localhost/website_monitoring/admin/login.php
2. Open **Websites** → **Add website**
   - Name, full URL (`https://example.com`), interval in minutes
   - Tick **Show this website on the public status page** if it should appear publicly
3. Click **Run check now** on the dashboard to test immediately.
4. Open the public status page to see the UptimeRobot-style view.

## 3. Telegram bot setup

1. In Telegram, open **@BotFather**.
2. Send `/newbot` and follow the steps.
3. Copy the **bot token**.
4. Start a chat with your new bot and send any message (for example `hello`).
5. Open **@userinfobot** (or similar) and copy your numeric **Chat ID**.
6. In Admin → **Settings**, paste Bot token and Chat ID.
7. Click **Send test Telegram message**.

Alerts are sent only when:

- a website goes **DOWN**
- it comes back **UP**
- it first becomes **SLOW** (response time above the threshold)

No duplicate Telegram message is sent while the status stays the same.

Message format:

```
🔴 ALERT: Website DOWN

Website: Example
URL: https://example.com
Status: DOWN
Response time: timeout / n/a
HTTP code: n/a
Time: 20 Aug 2026, 08:30:00 AM
```

## 4. Automatic monitoring (important)

The engine checks each website only when its own interval has passed. Run the script **every 1 minute**.

### Windows (XAMPP) — Task Scheduler

1. Open **Task Scheduler**.
2. Create Task → name it `WebsiteMonitoring`.
3. Trigger: **Daily**, repeat every **1 minute**, for an unlimited duration.
4. Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\website_monitoring\cron\monitor.php`
5. On the last wizard page, tick **Open the Properties dialog**.
6. In Properties:
   - Run whether user is logged on or not (optional)
   - **Run with highest privileges**
   - Settings tab: allow task to be run on demand; if the task is already running, do not start a new instance

You can also double-click `cron\run-monitor.bat` to run one check from Explorer.

### Alternative: browser / wget URL

In **Settings**, copy the secret cron URL:

`http://localhost/website_monitoring/cron/monitor.php?key=YOUR_CRON_KEY`

Keep that key private.

### Linux crontab

```
* * * * * php /path/to/website_monitoring/cron/monitor.php
```

## 5. Public status page

The public page is modeled after UptimeRobot:

- Overall banner: All systems operational / Partial outage / Major outage
- Last updated + countdown refresh (60 seconds)
- Each service: Operational/Down, 90-day uptime bars, 90-day percentage
- Overall uptime for 24 hours / 7 / 30 / 90 days
- Recent incidents from the last 7 days
- Fullscreen mode and optional alert sound

Only websites with **Show on status page** enabled appear here. This is public — no login.

## 6. Forgot password

On the login page, open **Forgot password?**. You need:

- Admin username
- Password reset key (shown after install, and again in **Settings**)

## 7. Project structure

```
website_monitoring/
├── admin/                 Admin panel pages
├── assets/css + js        Styles and small scripts
├── config/database.php    MySQL connection
├── cron/monitor.php       Monitoring engine
├── includes/              Auth, Telegram, check logic, layout
├── sql/schema.sql         Database tables
├── status/index.php       Public status page
├── install.php            First-run installer
└── README.md
```

## 8. Database tables

- `admins` — single admin account
- `websites` — monitors, current status, interval, last check
- `logs` — every check result
- `alerts` — DOWN / recovery / slow events
- `settings` — Telegram, status page title, cron key, reset key

## 9. Requirements

- PHP 7.4+ with **pdo_mysql** and **curl**
- MySQL / MariaDB (XAMPP default is fine)
- Apache (or any PHP web server)

If website checks fail with cURL errors, make sure `extension=curl` is enabled in `C:\xampp\php\php.ini`, then restart Apache.
