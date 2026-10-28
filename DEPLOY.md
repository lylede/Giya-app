# Deploying GIYA

GIYA runs on three free services, all in **Singapore** so they sit close to
each other and to the Philippines:

| Piece | Service | What it holds |
|---|---|---|
| Database | **Neon** (already set up) | All tables and data |
| App | **Vercel** (backup: **Render**) | The Laravel app, built from `Dockerfile` |
| Uploaded photos | **Supabase Storage** | New church photos and profile pictures |

> Why Supabase for photos: Vercel and Render wipe the app's own disk on every
> restart, so a photo saved there disappears. The photos already in
> `public/images/churches` ship with the app and are not affected.

Do the steps in order. Everything is done in a browser except step 0 and
step 5, which use your laptop's terminal.

---

## 0. Before you start (laptop)

1. Make sure the latest code is on GitHub (`main` branch).
2. Make an app key and **save the output** (it starts with `base64:`):
   ```powershell
   php artisan key:generate --show
   ```
   Use this one key everywhere from now on. Changing it later signs everyone
   out.

---

## 1. Supabase - storage for uploaded photos

1. Go to <https://supabase.com> -> **Start your project** -> sign in with GitHub.
2. **New project**
   - Name: `giya`
   - Database password: anything (GIYA does not use Supabase's database)
   - Region: **Southeast Asia (Singapore)**
3. Wait about 2 minutes for it to finish setting up.
4. Left menu -> **Storage** -> **New bucket**
   - Name: `giya-uploads`
   - **Public bucket: ON** (the photos must be viewable by anyone)
   - Save.
5. Left menu -> **Project Settings** -> **API Keys**. Copy two things:
   - **Project URL**, like `https://abcd1234.supabase.co` -> this is `SUPABASE_URL`
   - The **secret** key (`sb_secret_...`) - or, under *Legacy API keys*, the
     **service_role** key -> this is `SUPABASE_SERVICE_KEY`

   This key can change anything in your Supabase project. Put it only in
   Vercel/Render settings - **never in GitHub, never in the chat group in
   plain sight of others**.

> Supabase pauses a free project after **1 week with no activity**. Normal use
> of GIYA counts as activity. If it does pause, open the Supabase dashboard
> and click **Restore**.

---

## 2. Vercel - the app

1. Go to <https://vercel.com/signup> -> **Hobby** -> continue with **GitHub**,
   using the account that owns `lylede/Giya-app`.
2. **Add New...** -> **Project** -> find **Giya-app** -> **Import**.
3. **Framework Preset** should say **Container** (the repo's `vercel.json`
   asks for it). If it says *Vite* or *Other*, change it to **Container**.
4. Open **Environment Variables** and add every row of the table in
   [Environment variables](#environment-variables) below.
   For `APP_URL`, use `https://giya.vercel.app` for now - you fix it in step 3.
5. Click **Deploy**. The first build takes a few minutes (it installs PHP and
   Composer packages).

### If the build fails
Open the deployment -> **Build Logs**, copy the red part, and send it to
whoever is handling deployment. Common ones:

| Log says | Fix |
|---|---|
| `framework "container" is not supported` / builds with Vite | Project -> Settings -> General -> Framework Preset -> **Container** -> redeploy |
| `regions` not allowed on your plan | Delete the `"regions"` line from `vercel.json`, then in Project -> Settings -> Functions set the region to **Singapore (sin1)** |
| `composer install` errors | `composer.lock` is out of date: run `composer install` on a laptop, commit `composer.lock`, push |

---

## 3. After the first deploy

1. Copy the real address Vercel gave you (Project -> **Domains**, e.g.
   `https://giya-app.vercel.app`).
2. Project -> Settings -> **Environment Variables** -> edit `APP_URL` to that
   address -> **Save**.
3. **Deployments** -> the latest one -> **...** -> **Redeploy**.

Environment variable changes only take effect after a redeploy.

---

## 4. Check it works

Open the site and try, in this order:

- [ ] Home page loads with styles and church photos
- [ ] Sign in as admin
- [ ] Admin -> Destinations -> change a church's photo -> it shows on the church page
- [ ] Profile -> upload a profile picture
- [ ] Map shows tiles and church pins
- [ ] Giya AI answers a question (not "offline")

If a page shows **500 | Server Error**: Project -> **Logs** shows the reason.

---

## 5. Database changes (laptop)

The hosted app never changes the database structure by itself (several
copies could start at once and collide). When a commit adds a migration, run
it **once** from a laptop whose `.env` points at Neon:

```powershell
php artisan migrate
```

**Never run `migrate:fresh` against Neon** - it deletes everyone's data.

---

## Environment variables

Type these into Vercel (Project -> Settings -> Environment Variables) or
Render (service -> Environment). Values in *italics* come from your own
accounts.

| Name | Value |
|---|---|
| `APP_KEY` | *the `base64:...` key from step 0* |
| `APP_URL` | *your site address, e.g. `https://giya-app.vercel.app`* |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | *Neon host, e.g. `ep-....ap-southeast-1.aws.neon.tech`* |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `neondb` |
| `DB_USERNAME` | `neondb_owner` |
| `DB_PASSWORD` | *Neon password* |
| `DB_SSLMODE` | `require` |
| `GROQ_API_KEY` | *your Groq key* |
| `GROQ_MODEL` | `openai/gpt-oss-120b` |
| `SUPABASE_URL` | *from step 1.5* |
| `SUPABASE_SERVICE_KEY` | *from step 1.5* |
| `SUPABASE_BUCKET` | `giya-uploads` |
| `MAYA_BASE_URL` | `https://pg-sandbox.paymaya.com` |
| `MAYA_PUBLIC_KEY` | *sandbox public key (see `.env.example`)* |
| `MAYA_SECRET_KEY` | *sandbox secret key (see `.env.example`)* |

Already set inside the `Dockerfile` (no need to add): `APP_ENV=production`,
`APP_DEBUG=false`, `TRUSTED_PROXIES=*`, `FORCE_HTTPS=true`,
`SESSION_DRIVER=database`, `CACHE_STORE=database`, `LOG_CHANNEL=stderr`,
`MAIL_MAILER=log`.

> **Email:** `MAIL_MAILER=log` means password-reset emails are not actually
> sent; the link appears in the host's logs. Fine for a demo. Real emails
> need a mail service (ask before setting one up).

---

## Backup: Render

Use this if Vercel gives trouble before a deadline. Same Dockerfile, same
variables.

1. <https://render.com> -> sign up with GitHub.
2. **New** -> **Blueprint** -> pick **Giya-app**. Render reads `render.yaml`
   (free plan, Singapore).
3. Fill in the variables it asks for (same table as above). `APP_URL` is
   `https://<name>.onrender.com`.
4. **Apply**. First build takes several minutes.

Render's free app **sleeps after 15 minutes** without visitors; the next visit
takes about a minute to wake it. Open it a few minutes before a demo.

---

## How it behaves on the free plans

- **Vercel** stops the app after **5 minutes** with no visitors; the next
  visit starts it again (a few seconds). Open the site before a demo.
- **Neon** sleeps after 5 minutes idle; the first query wakes it.
- Both together can make the very first page after a quiet spell slow. Every
  page after that is normal speed.
