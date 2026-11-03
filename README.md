# Brajkishor Pandey — bkpandey.com

Personal portfolio and PHP 8.5/MySQL publisher with résumé, profile, contact settings,
blog editing, image sliders, PDF carousels, secure uploads, and private caching.

## Build

```bash
npm ci --ignore-scripts --omit=optional --no-audit --no-fund
python3 scripts/prepare_assets.py
python3 build.py
python3 blog_build.py
php tests/blog.php
python3 scripts/verify_release.py
```

The GitHub source contains application code and the default portrait and résumé.
Other public media is restored from the existing website using the size and
SHA-256 values in `assets-manifest.json`. The self-hosted PDF viewer is restored
from the exact npm version and integrity recorded in `package-lock.json`.
No installation scripts are run. These restores happen automatically in CI.

## Automatic deployment

Pushes to `main` validate first and deploy over certificate-verified FTPS.
Pull requests validate without deploying. See [CI-CD.md](CI-CD.md) for the
production environment secrets and account-root requirement. This workflow is
not active until those secrets are configured; no credentials are in source.

## Admin and hosting layout

- `/admin`: publisher sign-in and content management.
- `/admin/setup`: one-time database connection and owner account creation.
- `public_html/`: HTML and public entrypoints; assets in CSS, JS, image, font,
  document, and PDF-viewer folders.
- `bkpandey-private/`: PHP controllers, database config, uploads and cache,
  outside the document root.

See [SECURITY.md](SECURITY.md) and
[hosting/bkpandey-private/DEPLOYMENT.md](hosting/bkpandey-private/DEPLOYMENT.md).

## Update content

Edit `content.json` for project descriptions or `build.py` for profile copy,
services, technical skills, work history, field notes, and contact details.
Then run `python3 build.py`. To rebuild the PDF, install ReportLab and run
`python3 create_resume.py`.

The portfolio uses the user's current professional resume, public LinkedIn
profile, earlier portfolio records, and archived versions of bkpandey.com.
Current professional records take priority over outdated archive details.
Recent private-client architecture and prototype work is presented without
client identifiers or confidential commercial details.

## Shared-hosting deployment

1. Make a backup of the existing website using the hosting control panel or FTPS.
2. Extract the deployment ZIP locally.
3. Upload the contents of its `public_html/` folder into the document root
   assigned to bkpandey.com. The directory may already be named `public_html`;
   upload its contents, rather than nesting a second `public_html` inside it.
4. The shared-hosting package uses `index.php` before its static `index.html` fallback. The hosting overlay `.htaccess` sets this, disables directory listing, and defines a 404 page.
5. Verify the homepage, project links, portrait, PDF download, and contact links.

The new site does not depend on old CSS, JavaScript, WordPress, or image files.
The deployment includes its own lightweight PHP homepage renderer.
Do not delete mail, DNS, SSL, server configuration, or unrelated hosted apps.

No credentials are included in the project or deployable files. Use explicit
FTPS with certificate validation for hosting uploads.

## Blog and MySQL publishing

`/blog/` includes 29 attributable articles and 18 original PDF carousels. The
static review works immediately. `hosting/` adds a PHP/MySQL publisher for the
owner's shared hosting, with categories, drafts, publication, archival, cover
images, multi-image slideshows, and PDF carousel/download modes. The owner can also update the homepage introduction, profile image, and résumé PDF. Keep `hosting/bkpandey-private/` outside the document
root. Read its `DEPLOYMENT.md` before enabling the database environment.

The database connection reads private environment variables or the server-only private configuration created by the HTTPS owner setup. Real FTP and
database passwords are not embedded in the source or deployment package.
The publisher is at `/admin` once configured on the PHP host.

The content collection is a curated adaptation of retrievable LinkedIn posts
and owner-provided technical PDFs. It is not a verified complete account export.
`blog-import-manifest.json` records the imported sources and related attachments.

## Contact behavior

Email, phone, WhatsApp, and LinkedIn links open their respective applications.
The brief form validates its fields and prepares a `mailto:` draft in the
visitor's email application. It does not submit a server-side form or store data.
The copy-email button uses the Clipboard API with a visible fallback.

## Accessibility and motion

Semantic navigation, keyboard-accessible native controls, visible focus rings,
labelled fields, responsive layouts, and reduced-motion preferences are included.
Animations use CSS and IntersectionObserver, with readable content when JavaScript
is unavailable. The font and image assets are local. No tracking scripts are added.

## Assets

The portrait was enhanced from a photo recovered from the owner's archived site.
The image edit preserves the source pose, sunglasses, and recognizable appearance.
Project artwork is explicitly labelled as concept illustration, not a product screenshot.
The Manrope font is distributed under the included SIL Open Font License.

## Verification

Reviewed desktop, phone, and tablet rendering; exercised mobile navigation,
project filtering, and project-page navigation; checked local asset/anchor links
and JavaScript syntax; rendered and visually inspected the four-page PDF.

PHP syntax was checked using WordPress's PHP WebAssembly CLI. A 60-check SQLite-backed
PDO test suite covers publication visibility, optimistic edit concurrency, SQL
parameter handling, HTML escaping, URL and slug validation, CSRF, login limits,
password verification, and attachment checks. Live MySQL and hosting behavior
require verification after deployment on the intended PHP/MySQL host.

Versioned migrations support fresh installs and adoption of the existing seven
tables. Checksums, database locking, and recorded failure progress guard updates.
The owner can apply pending trusted migrations through **Admin → Database**;
there is also a private CLI. GitHub Actions tests the runner against SQLite and
a MySQL 8.4 service. See `DATABASE.md` for activation and backup procedures.

Public app endpoints use clean routes (`/admin`, `/admin/setup`, `/api/site-settings`, `/api/media/ID/view`, `/media/profile`, and `/resume/download`). Direct script-suffix requests return 404. This reduces technology exposure; it does not make the server technology impossible to identify.
