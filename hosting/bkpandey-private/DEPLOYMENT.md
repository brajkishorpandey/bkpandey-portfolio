# Activate the MySQL blog

The public website includes a ready-to-use static blog with 29 articles and 18
PDF carousels. The PHP publishing backend uses the existing MySQL database once
it is configured on the owner's hosting. It is not connected to the database in
the temporary static preview.

## Upload layout

Upload the deployment package into the hosting account so these are siblings:

- `public_html/`: the site document root (HTML, assets, and PHP blog controllers).
- `bkpandey-private/`: application code, schema, seed data, templates, and private uploads.

If the domain uses another document-root name, set `BP_PRIVATE_DIR` to the
absolute private directory. This directory must stay outside any public web root.

The public blog is `/blog/`. The owner publishing area is `/admin`.
The included `/blog/.htaccess` routes article URLs through the MySQL renderer
after configuration; static files remain a fallback before backend activation.

## Requirements

PHP 8.2 or later, PDO MySQL, Fileinfo, HTTPS, MySQL 8 or MariaDB 10.11, and Apache mod_rewrite for
the stable `.html` article URLs. The account needs create-table privileges for
the initial setup. The seven application tables use the `bp_blog_` prefix and do not
modify unrelated tables.

## Shared-hosting owner setup

After uploading, open `/admin/setup` over HTTPS. Authenticate the existing
MySQL account through the password form. The installer initializes the seven application
tables, imports missing articles, and stores the runtime connection in
`bkpandey-private/database.php` with owner-only permissions. This runtime file
stays outside the document root and is never part of the source or download.

After database authentication, create a separate owner password for publishing.
The setup page removes itself once the owner account exists. The database form
is rate-limited and guarded by a secure session and CSRF token.

## Alternative: private environment

Set these variables in the hosting configuration used by PHP (not in public HTML,
JavaScript, a public `.env` file, or a source repository):

- `BP_DB_HOST`: the database host; for this hosting arrangement, localhost.
- `BP_DB_NAME`: the existing blog database name.
- `BP_DB_USER`: the existing database user.
- `BP_DB_PASSWORD`: the database password supplied to the hosting account.
- `BP_PRIVATE_DIR`: optional absolute path override.

The owner installer is restricted to the provided database identity. Its password is not embedded in the downloadable package. Configure it privately on the server.

For setup only, also supply `BP_ADMIN_EMAIL` and `BP_ADMIN_PASSWORD` (at least
14 characters, separate from the database password). Run with the hosting's PHP:

```bash
php /absolute/private/path/setup.php
```

Pass secrets through the process environment or an interactive hidden prompt,
rather than command-line arguments or shell history. Remove setup-only admin
password variables after the command succeeds. The owner account stores only
its password hash in MySQL. Re-running setup preserves edited posts and existing
admin passwords; it adds only missing seed articles.

The runtime must also receive the database environment. A shell export used only
for CLI setup does not automatically configure PHP-FPM or Apache. Configure the
site's PHP environment through the hosting administrator or server control panel.

## Publishing

Sign in over HTTPS. Create an article, select a category, add a summary and body,
and save it as draft or published. Archived articles stay in the database and
leave the public listing. Use `## ` for headings, `### ` for subheadings, and
`- ` for list items. Raw HTML is escaped.

The publishing form accepts a cover, PDF attachment, and up to 12 slideshow images at once. Multiple images become an animated slider with pause and keyboard controls. Choose PDF carousel or PDF download mode per article, set slide duration, and manage attachment order. The Website & résumé tab changes the homepage headline, introduction, portrait, and résumé PDF. All public portrait and résumé paths are routed to the latest configured file. PDF, JPEG, PNG,
and WebP files are checked against their content type, limited to 10 MB, given
random storage names, and kept outside the document root. Downloads of private
uploads require a published article or an authenticated owner session.

## LinkedIn import coverage

The initial collection uses publicly retrievable posts and the owner's existing
technical carousel files. It is a curated adaptation, with original source links
where an exact public post was found, not a complete export of the LinkedIn
account. Date labels appear only where the original publication date was known.
The full original PDFs are preserved as attachments.

`blog-import-manifest.json` records each imported title, source link, and matched
attachment. LinkedIn's full activity list redirected to sign-in during this run.
An account export with the remaining post text and media is needed to establish
complete coverage. Future articles can be added directly through the publisher.

## Validation and limits

The static pages and PDF assets are ready without a database. Live MySQL
connectivity, the hosting's PHP environment, HTTPS behavior, mod_rewrite, upload
permissions, and provider-side backups must be verified after deployment.
The temporary review site hosts the static publication only.

## Speed and cache

Public HTML and media authorization lookups use private atomic file caches.
Warm public blog requests do not connect to MySQL. Saving content changes the
cache generation immediately. Browser ETags support conditional responses.
The catalog retrieves a bounded list of up to 500 published articles and their
media in bulk, avoiding one attachment query per card. MySQL indexes cover
publication sorting, unique slugs, and reverse attachment lookup. Admin pages
and authentication responses use no-store and are never put in the public cache.

PDF.js 6.5.438 is self-hosted with its worker and optional decoding resources.
It is imported only when a PDF carousel approaches the viewport. One canvas
renders one page at a time. Uploaded PDFs support HTTP byte ranges. A download
link remains available if previewing is unsupported. Image sliders pause for
keyboard interaction, page visibility, and reduced-motion preferences.

Keep runtime database.php, uploads/, cache/, and setup-attempts.json out of
source archives. A deployment must preserve these server-only paths. Run
`python3 package.py /absolute/release.zip` for a full release or add `--patch`
for a code/HTML/PDF-viewer update that preserves the already-uploaded PDFs,
images, and fonts. Keep a host backup before applying updates.

Public app endpoints use clean routes (`/admin`, `/admin/setup`, `/api/site-settings`, `/api/media/ID/view`, `/media/profile`, and `/resume/download`). Direct script-suffix requests return 404. This reduces technology exposure; it does not make the server technology impossible to identify.

Website settings include a brief résumé, homepage introduction, contact heading and introduction, public email and telephone, WhatsApp, LinkedIn, location, and editable email subject/body templates. Account & password changes the owner password and invalidates existing authenticated sessions. The owner password policy is at least 14 characters.
