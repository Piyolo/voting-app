# School Voting System

A secure online voting system for a school election. Students log in with
their Student ID, vote once per position, and anyone can view live results.
Built with vanilla PHP (PDO), PostgreSQL, and no frameworks — designed to run
entirely on free-tier hosting.

- **Frontend:** HTML / CSS / vanilla JavaScript
- **Backend:** Raw PHP with PDO (`pdo_pgsql`)
- **Database:** PostgreSQL (Neon, free tier)
- **Hosting:** Render (free Web Service)
- **Domain:** `VOTINGACC.LINKPC.NET` (Freedomain), CNAME'd to Render

---

## 1. File Structure

```
voting-app/
├── config/
│   └── db.php              # DB connection (reads DATABASE_URL)
├── includes/
│   └── functions.php        # Session, CSRF, escaping helpers
├── assets/
│   ├── style.css
│   └── logo.png              # placeholder — replace with real school logo
├── admin/
│   ├── login.php
│   ├── index.php             # add positions/candidates, view turnout
│   └── logout.php
├── index.php                 # student login
├── dashboard.php              # voting page
├── vote.php                   # AJAX vote handler
├── results.php                 # public live results
├── logout.php
├── schema.sql                  # full schema + seed data
├── .gitignore
└── README.md
```

---

## 2. Security Notes

- **All queries use PDO prepared statements** — no string-concatenated SQL anywhere.
- **Passwords** are stored with `password_hash()` and checked with `password_verify()`. Nothing else ever sees a plaintext password.
- **Double-voting is prevented two ways:**
  1. `votes` has a `UNIQUE (user_id, position_id)` constraint.
  2. `vote.php` locks the user's row (`SELECT ... FOR UPDATE`) inside a transaction and re-checks `has_voted` before inserting, closing the race-condition window between two rapid submissions.
- **CSRF tokens** are required on every state-changing form (login, voting, admin actions).
- **Sessions** use `httponly`, `samesite=Lax` cookies, and `session_regenerate_id()` on login to prevent session fixation.
- **Admin panel** is protected by a separate bcrypt-hashed password (`ADMIN_PASSWORD_HASH` env var) and never displays voter passwords — only names, turnout counts, and vote tallies.

---

## 3. Local Development

You'll need PHP 8+ with the `pdo_pgsql` extension, and a local PostgreSQL instance.

```bash
# Create a local database
createdb voting_app

# Load schema + seed data
psql voting_app -f schema.sql

# Serve the app
php -S localhost:8000
```

Local DB credentials are read from environment variables (with defaults),
so you can also run e.g.:

```bash
DB_HOST=localhost DB_NAME=voting_app DB_USER=postgres DB_PASS=postgres php -S localhost:8000
```

Test login: **Student ID** `987654321`, **Password** `password123`.

---

## 4. Deploy to Production (Neon + Render + Freedomain)

### Step 1 — Push code to GitHub
```bash
git init
git add .
git commit -m "Initial commit: school voting system"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/voting-app.git
git push -u origin main
```

### Step 2 — Create the Neon database
1. Go to [neon.tech](https://neon.tech) and create a free project.
2. Once created, copy the **connection string** (looks like
   `postgres://user:password@ep-xxxx.us-east-2.aws.neon.tech/neondb?sslmode=require`).
3. Open the Neon SQL editor (or use `psql`) and run the contents of `schema.sql`
   to create the tables and load the seed data:
   ```bash
   psql "postgres://user:password@ep-xxxx.neon.tech/neondb?sslmode=require" -f schema.sql
   ```

### Step 3 — Create the Render Web Service
1. Go to [render.com](https://render.com) → **New +** → **Web Service**.
2. Connect your GitHub repo.
3. Environment: **PHP**.
4. **Start Command:**
   ```
   php -S 0.0.0.0:10000
   ```
5. Under **Environment Variables**, add:
   | Key | Value |
   |---|---|
   | `DATABASE_URL` | the Neon connection string from Step 2 |
   | `ADMIN_PASSWORD_HASH` | a bcrypt hash for your real admin password (see below) |

   Generate an admin hash locally:
   ```bash
   php -r "echo password_hash('your-real-admin-password', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   If you don't set `ADMIN_PASSWORD_HASH`, the panel falls back to a default
   development password (`admin123`) — **do not leave this in production.**

6. Deploy. Render will build and start the service; you'll get a URL like
   `https://voting-app-xxxx.onrender.com`.

### Step 4 — Point the domain
1. In **Freedomain**, edit DNS for `VOTINGACC.LINKPC.NET` and add a **CNAME**
   record pointing to your Render subdomain (`voting-app-xxxx.onrender.com`).
2. In Render, go to your service → **Settings** → **Custom Domains** → add
   `VOTINGACC.LINKPC.NET`. Render will verify the CNAME and issue a free
   TLS certificate automatically.
3. Once DNS propagates, the site is live at `https://votingacc.linkpc.net`.

### Step 5 — Verify
- Visit `/index.php` and log in with the seed test account, or a real student account.
- Visit `/results.php` to confirm live results render without login.
- Visit `/admin/login.php` to confirm the admin panel is reachable and password-protected.

---

## 5. Adding Real Students & Candidates

### Option A — Admin panel (recommended)
Log in at `/admin/login.php` to add positions and candidates through the UI.
Student accounts are not manageable through the admin panel by design (to
avoid ever exposing a path to view/reset passwords insecurely) — add them via
SQL as shown below, or extend the admin panel yourself if you need a UI for it.

### Option B — Manual SQL seeding
Generate a password hash for each student:
```bash
php -r "echo password_hash('their-password', PASSWORD_DEFAULT), PHP_EOL;"
```
Then insert:
```sql
INSERT INTO users (student_id, name, password, department, year_level)
VALUES ('202300123', 'Juan Dela Cruz', 'PASTE_HASH_HERE', 'FICT', 'BSIT 2nd year');
```

Add positions and candidates the same way `schema.sql` does:
```sql
INSERT INTO positions (title) VALUES ('Auditor');

INSERT INTO candidates (position_id, first_name, last_name)
SELECT id, 'Ana', 'Lopez' FROM positions WHERE title = 'Auditor';
```

---

## 6. Resetting an Election

To reset votes and turnout (e.g., before a new election) without deleting
positions/candidates/students:
```sql
DELETE FROM votes;
UPDATE users SET has_voted = 0;
```

---

## 7. Known Limitations / Ideas for Extension

- Candidate photos are stored as a URL string (`photo` column) — host images
  externally or add file-upload handling to the admin panel if needed.
- The admin panel doesn't manage student accounts through the UI; this is
  intentional to keep the "admin cannot access voter passwords" guarantee
  simple and auditable. Extend carefully if you add this.
- `results.php` auto-refreshes every 15 seconds via a meta tag; swap in
  `fetch()` + `setInterval` if you want it to update without a full page reload.
