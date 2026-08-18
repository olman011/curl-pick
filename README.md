# The Hog Line - weekly picks league

Mobile-first PHP 8 + MySQL app where invited members pick the winner of each of the 8 games
played on league night, and see weekly/season leaderboards plus team standings.

- Winner-only picks, editable until the weekly lock time (default 8:45 PM America/Chicago on game day)
- 18 teams, 8 games a week, 2 teams on a bye (byes are derived from the schedule automatically)
- Admin pages for teams, weekly schedule, scores, members, and invites
- Invite-only signup via link or QR code
- Password resets without SMTP: a member requests one, an admin issues a one-time link

## Requirements

- PHP 8.0+ with `pdo_mysql`
- MySQL 8 or MariaDB 10.4+
- Apache (an `.htaccess` is included) or nginx; document root must be `public/`

No Composer dependencies.

## Install

```bash
git clone https://github.com/olman011/curl-pick.git
cd curl-pick
cp config/config.example.php config/config.php   # then edit db credentials + base_url
mysql -u root -p -e "CREATE DATABASE hogline CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p hogline < db/schema.sql
php bin/create_admin.php "Your Name" you@example.com "a-strong-password"
```

Point the vhost document root at `curl-pick/public`. Nothing outside `public/` should be web
reachable - `config/config.php` holds the database password.

Sample nginx location block:

```nginx
root /var/www/curl-pick/public;
index index.php;
location / { try_files $uri $uri/ =404; }
location ~ \.php$ { include fastcgi_params; fastcgi_pass unix:/run/php/php8.2-fpm.sock; fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name; }
```

To click around with fake data first: `php bin/seed_demo.php` (18 teams, 3 weeks, 3 members with
password `curling123`). Run it only on an empty database.

## Running the league

1. **Teams** - add the 18 teams once (Admin -> Teams).
2. **Weeks** - create a week with its date; the lock time defaults to 8:45 PM and can be changed
   per week. Add the 8 matchups; whichever two active teams you leave out are shown as the bye.
3. **Members** - create an invite (Admin -> Invites), then share the link or have people scan the
   QR code. Multi-use invites work for onboarding a whole team at once.
4. **Scores** - after games, enter both scores on the week page. Saving a score marks the game
   final, which updates picks scoring and the team standings. Equal scores are a tie: no pick
   points, 1 standings point each.

## Scoring

- 1 point per correct winner; ties award nobody a point.
- Season leaderboard = total correct across all weeks.
- Standings: 2 points per win, 1 per tie, ranked by points then score differential.

## Local development

```bash
php -S 127.0.0.1:8000 -t public
```

## Layout

```
public/          web root (member pages + admin/)
src/             bootstrap, db, auth, helpers, league queries, layout
db/schema.sql    schema
bin/             CLI helpers (create_admin, seed_demo)
config/          config.example.php -> copy to config.php (git-ignored)
```
