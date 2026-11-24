# GIYA

A localized travel companion for religious tourism and pilgrimage in Cebu.
Pilgrims find churches, Mass schedules and history in one place, plan custom
itineraries and Visita Iglesia routes, track the churches they visit, and ask
Giya AI. Administrators keep the church data, schedules and announcements up
to date from a web dashboard.

Laravel 13 · PHP 8.3 · PostgreSQL · Livewire 4 · Leaflet · English, Cebuano, Filipino

---

## What it does

**For pilgrims**

- Church pages: history, photos, guidelines, Mass schedules, feast days, ratings and reviews
- Map of Cebu with clustered pins, distances and "find my location"
- Custom itineraries: pick churches, reorder them, save the route (3 free, unlimited on Premium)
- Visita Iglesia planner: the main church of the 7 nearest towns, in one-way driving order
- Progress tracking: mark stops visited, or automatic check-in after about a minute near the church
- Giya AI chatbot, grounded in GIYA's own records (20 questions a day free)
- Follow churches for notifications in the bell and on the phone (Web Push)
- Share an itinerary by link; favorites; visit history; app feedback
- Sign in with email (6-digit code) or Google; light and dark theme; installable on the Home Screen
- Premium (weekly or monthly) through Maya

**For administrators**

- Churches: add, edit, photos, map pin, hide, CSV import
- Schedules by church, with repeating rules, drafts and copy from another church
- Users, plans and prices, transactions, reviews, app feedback
- Announcements, maintenance mode, activity log, insights
- Reports filtered by date, exported to CSV
- Signed out after 30 minutes without activity

---

## Run it on a new laptop

**Install first:** PHP 8.3+, Composer 2, PostgreSQL 16+, Git.

**Enable the PHP extensions.** Run `php --ini`, open that file, uncomment:

```ini
extension=pdo_pgsql
extension=pgsql
extension=fileinfo
extension=gd
extension=openssl
```

Check with `php -m`. All five must appear.

**Then:**

```bash
git clone https://github.com/lylede/Giya-app.git
cd Giya-app
composer install

cp .env.example .env          # PowerShell: Copy-Item .env.example .env
php artisan key:generate
```

**Create the database** in psql or pgAdmin:

```sql
CREATE DATABASE giya_db;
```

**Set your password** in `.env`:

```env
DB_DATABASE=giya_db
DB_USERNAME=postgres
DB_PASSWORD=your_postgres_password
```

**Finish:**

```bash
php artisan migrate --seed
php artisan storage:link
php artisan giya:tiles         # offline map tiles, 15-20 min
php artisan serve
```

Open http://127.0.0.1:8000. The seeded accounts are in
`database/seeders/UserSeeder.php`. Use them only on your own laptop.

### Optional services

GIYA runs without these; each one turns on a feature. Put the values in `.env`
on your laptop, or in the host's dashboard when online. **Never commit keys.**

| Feature | Variables |
|---|---|
| Giya AI | `GROQ_API_KEY`, `GROQ_MODEL`, `GROQ_FALLBACK_MODEL` |
| Sign in with Google | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` |
| Premium payments | `MAYA_BASE_URL`, `MAYA_PUBLIC_KEY`, `MAYA_SECRET_KEY` |
| Phone notifications | `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT` (make them with `php artisan giya:push-keys`) |
| Photo storage online | `SUPABASE_URL`, `SUPABASE_SERVICE_KEY`, `SUPABASE_BUCKET` |
| Real emails | `MAIL_MAILER` and the mail service's settings (`log` writes them to the log instead) |
| Email code at sign-up | `GIYA_VERIFY_EMAIL=true` (off by default) |
| Error alerts to the team | `ERROR_ALERT_WEBHOOK`, `ERROR_ALERT_EMAIL` |
| One official address | `GIYA_OFFICIAL_URL` |

After changing `.env`: `php artisan config:clear`.

---

## Tests

```bash
php artisan test
```

Feature and unit tests live in `tests/`. Run them before every change; all must
pass.

---

## Useful commands

| Command | What it does |
|---|---|
| `php artisan giya:tiles` | Downloads offline map tiles |
| `php artisan giya:import-churches {file}` | Imports churches from a CSV, e.g. `database/data/churches.csv` (`--dry-run` first) |
| `php artisan giya:import-parishes` | Adds the Archdiocese parish list |
| `php artisan giya:import-mass-times {file}` | Imports Mass times from a CSV (`--dry-run` first) |
| `php artisan giya:import-festivals` | Adds Cebu's feasts and festivals |
| `php artisan giya:add-church-content` | Adds church history and descriptions |
| `php artisan giya:add-church-contacts` | Adds parish contact details |
| `php artisan giya:clean-drafts` | Lists (or `--delete`s) duplicate draft schedules |
| `php artisan giya:move-photos` | Moves local photos to Supabase Storage |
| `php artisan giya:push-keys` | Makes the key pair for phone notifications |
| `php artisan giya:push-test {email}` | Sends a test phone notification and explains any failure |
| `php artisan giya:maya-webhooks` | Registers the Maya payment webhook |

---

## Putting it online

See **[DEPLOY.md](DEPLOY.md)**: Neon (database) + Vercel (app, Render as
backup) + Supabase Storage (uploaded photos), all free and in Singapore.

Database changes are run from a laptop pointed at Neon with
`php artisan migrate`. Never run `migrate:fresh` on Neon: it deletes every
table and all live data.

---

## When it breaks

| Error | Fix |
|---|---|
| `could not find driver` | Extensions not enabled, or wrong `php.ini` |
| `password authentication failed` | Fix `.env`, then `php artisan config:clear` |
| `Connection refused` | PostgreSQL service not running |
| `No application encryption key` | `php artisan key:generate` |
| `relation "..." does not exist` | Wrong database in `.env`, or `migrate` not run |
| Images 404 | `php artisan storage:link` |
| Blank map | `php artisan giya:tiles` |
| "Find my location" does nothing | Use `127.0.0.1`, not a LAN IP: geolocation needs HTTPS or localhost |
| Giya AI says it is offline | `GROQ_API_KEY` missing or wrong; then `php artisan config:clear` |
| Phone notifications never arrive | `php artisan giya:push-test you@example.com` says why |
| `giya:push-keys` fails on Windows | Enable `extension=openssl`; the command finds `openssl.cnf` itself |
| Changes not showing | `php artisan view:clear` |
| VS Code shows many errors in `.blade.php` files | Install the recommended extensions it suggests (`.vscode/extensions.json`) |

Keep **one** database. Spares from earlier attempts cause errors that look like
code faults.

---

## After `git pull`

```bash
composer install
php artisan migrate
php artisan view:clear
```

---

## Project layout

```
app/
  Http/Controllers/      requests: pilgrim pages, Admin/, Auth/
  Http/Middleware/       admin only, admin idle timeout, maintenance, language, activity log
  Models/                23 models, one per table
  Services/              GiyaAssistant (AI), RoutePlanner, RouteService, MayaService
  Support/               notifications, Web Push, uploads, repeating schedules, Cebu regions
  Actions/Auth/          register, sign in, email code, password reset
  Policies/  Reports/  Rules/  Console/Commands/
bootstrap/app.php        middleware and error handling
config/                  settings read from .env
database/
  migrations/            the schema, in order
  seeders/               starting data
  data/                  church, parish, Mass time and festival source files
lang/en, ceb, fil/       every on-screen text in three languages
public/
  assets/js, assets/css/ scripts and styles (giya.css: design system and dark theme)
  sw.js                  service worker: offline page, phone notifications
  manifest.webmanifest   makes GIYA installable
  tiles/                 offline map tiles
resources/views/         Blade pages, components and Livewire sign-in forms
routes/web.php           every address and who may open it
tests/                   feature and unit tests
Dockerfile, docker/      the server used on Vercel and Render
```

---

## House rules

- On-screen text goes in `lang/en`, `lang/ceb` and `lang/fil`, never only in one.
- No long dashes in GIYA's text; use a comma, colon or plain hyphen.
- Keys and passwords go only in `.env` or the host's dashboard.
- Every change comes with tests, and the whole suite passes before it is shared.
