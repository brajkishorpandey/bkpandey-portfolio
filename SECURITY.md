# Security and operations

Application PHP, database settings, cache, uploads, and rollback files belong
in `bkpandey-private/`, outside `public_html`. Public PHP files contain only
fixed entrypoints; they cannot load a user-selected filename. Public files are
organized into `assets/css`, `assets/js`, `assets/images`, `assets/documents`,
`assets/fonts`, and the self-hosted PDF viewer under `assets/vendor`.

Uploads require an authenticated owner session and a valid CSRF token. Only
validated PDF/JPEG/PNG/WebP content is accepted, with a matching extension and
10 MB limit. Files have random names and remain outside the public web root.
The file handler authorizes access, sends the correct content type, prevents
sniffing, applies a sandbox policy, and supports byte ranges. Draft attachments
are private. Published content is intentionally public. Previously downloaded
public files cannot be withdrawn from visitors' devices.

The database uses native prepared statements. Admin passwords are hashed;
passwords are never stored in public source. Login is rate limited by address
and account. Secure, HttpOnly, SameSite cookies, session expiry, CSRF checks,
and optimistic edit versions protect publishing. Owner setup requires the
existing database password and removes its controller after account creation.
Public Adminer access is denied; cPanel remains the database-management route.

Apache disables directory listing, CGI and server-side includes. It denies
configuration files, logs, backup archives and hidden paths; the ACME challenge
folder remains available. Browser policies restrict scripts and connections to
the site, prevent framing by other sites, require HTTPS, and disable unneeded
browser permissions. Private cache entries never contain credentials or sessions.

# Deployment security

The workflow validates PHP 8.5 and backend behavior before deployment. Pull
requests only validate; deployment accepts main-branch pushes or a manual main
run. Jobs use read-only repository permissions and pinned actions. FTPS checks
certificates, requires TLS 1.2+, encrypts data connections, and never executes
remote shell commands. Only changed release files are uploaded; database
settings, uploads, cache data and existing rollback files are excluded.
Changed remote files are backed up privately before replacement. The deployment
cache generation is refreshed after success. Check remaining disk space and
retain a suitable number of rollback releases through cPanel.

Use a dedicated deployment account that can reach the two application sibling
directories. Do not use the cPanel owner password as the deployment secret.
Configure GitHub's production environment for the main branch only, restrict
write access, and enable account 2FA. CODEOWNERS and Dependabot configuration
are included; branch protections must be enabled in GitHub before they enforce
review. Remove setup-only CREATE/ALTER privileges from the database account
after installation if no future schema migration needs them.

No application can guarantee that compromise is impossible. Server patching,
account security, provider backups and access reviews remain operational tasks.

Public app endpoints use clean routes (`/admin`, `/admin/setup`, `/api/site-settings`, `/api/media/ID/view`, `/media/profile`, and `/resume/download`). Direct script-suffix requests return 404. This reduces technology exposure; it does not make the server technology impossible to identify.
