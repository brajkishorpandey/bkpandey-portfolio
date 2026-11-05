# Database installation and migrations

The production database already exists as `bkpandey_braj` on `localhost`.
Create databases and scoped users through cPanel, which controls account names
and quotas. The application never needs MySQL root access or `CREATE DATABASE`.
Keep `database.php`, uploads, cache, and migration files outside `public_html`.

## First activation

Open `https://bkpandey.com/admin/setup`, authenticate the existing database user,
and create the owner account. Use the database-user password; the cPanel
password can be different. Setup applies the versioned initial migration and
imports the initial articles once, preserving already imported/edited posts.
Choose a unique publisher password of at least 14 characters. Passwords and
database credentials are never committed or included in release archives.

## Apply a later release

1. Export a database backup through cPanel/phpMyAdmin before schema changes.
2. Deploy the trusted release source.
3. Sign in at `/admin`, open **Database**, inspect the migration list, and use
   **Apply pending migrations**. This action requires owner authentication and
   a valid CSRF token. It accepts no SQL, filenames, or uploaded migration code.

If the host provides terminal access, the equivalent commands are:

```sh
php /home/bkpandey/bkpandey-private/migrate.php status
php /home/bkpandey/bkpandey-private/migrate.php up
```

There is no public HTTP migration endpoint. GitHub's FTPS deployment uploads
trusted migration source but does not automatically execute database changes.

## Migration rules

- Add ordered files such as `002_add_feature.php` to the private `migrations/`
  directory. Each returns an array of complete SQL statements. The baseline
  `001_initial_schema.php` creates the seven application tables and adopts an
  existing installation using `CREATE TABLE IF NOT EXISTS`.
- Applied versions, SHA-256 checksums, state, statement progress, and timestamps
  are stored in `bp_schema_migrations`. Do not edit or remove applied files.
- A MySQL/MariaDB connection lock prevents two migration runners from applying
  updates concurrently. A second run skips applied versions.
- MySQL DDL can commit before an error. A running/failed migration blocks later
  runs for operator review; it is never automatically replayed. Check recorded
  progress and the actual schema, then restore a backup or repair deliberately.
  This tool does not promise transactional rollback or include a destructive
  `down` command.
- Keep changes backward compatible with the current application during file
  deployment. Do not use initial setup to reset an existing admin password.

The application user needs SELECT, INSERT, UPDATE, DELETE, CREATE, INDEX, ALTER,
and REFERENCES on this one database. Normal editing does not require DROP,
FILE, SUPER, GRANT, or global privileges. Provision only privileges required by
the actual migrations and remove unused schema privileges afterward if desired.

### Traffic reports

Migration `002_traffic_reporting` adds the daily summaries, daily keyed visitor estimates and the archive cursor. The reports are available to the site owner under **Traffic reports** in `/admin`.

Run the collector from cPanel Cron Jobs with the domain’s PHP CLI. For the initial import, add `--migrate`. The collector reads the domain’s two monthly gzip archives in the account’s `logs/` directory. Keep cPanel access log archiving enabled. The collector is safe to rerun as each batch and its cursor commit together.

```sh
/usr/local/bin/ea-php85 /home/bkpandey/bkpandey-private/traffic-collect.php --migrate
```

Schedule the collector every 15 minutes. Add `--mail` once the account’s outgoing relay is verified. The collector can send yesterday’s report once both archives have been imported after midnight in India. Archive updates determine when a complete report can be sent. Use `--send-date=YYYY-MM-DD` for a one-time delivery check on a completed imported day. The dashboard distinguishes mail accepted by the relay from inbox delivery.

Keep `traffic-state/` outside the document root and preserve its visitor key during releases. The dashboard stores no raw request IPs, user agents or referral URLs. Reports estimate unique visitors and bot clients per India calendar day. Requests that Cloudflare handles entirely at its edge are outside this data source.
