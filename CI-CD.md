# Activate automatic deployment

The included `.github/workflows/deploy.yml` is ready for a GitHub repository.
It is not active until the repository, production environment, and its secrets
are configured. There is no deployment credential in the code or archive.

1. Source and workflow are in `brajkishorpandey/bkpandey-portfolio`, with `main`
   as the deployment branch.
2. In GitHub, create the `production` environment and restrict it to `main`.
   A required reviewer is optional if you prefer approval before a release.
3. Add separate production environment secrets:

   | Secret | Value |
   | --- | --- |
   | `FTPS_HOST` | Provider's FTP hostname with a matching valid TLS certificate |
   | `FTPS_USERNAME` | Dedicated deployment FTP account |
   | `FTPS_PASSWORD` | That deployment account's password |
   | `FTPS_ROOT` | Public FTP root; `/` for the existing public-folder login |
   | `FTPS_PRIVATE_USERNAME` | Separate FTP login scoped to `/home/bkpandey/bkpandey-private` |
   | `FTPS_PRIVATE_PASSWORD` | That private-folder FTP account password |
   | `FTPS_PRIVATE_ROOT` | `/` for a login scoped to the private folder |

4. Enable branch protection, restrict repository write access, and enable 2FA.
5. Run **Validate and deploy portfolio** manually for the first release. Confirm
   the successful run and live homepage, blog, PDF viewer, and admin sign-in.
   Subsequent pushes to `main` deploy automatically after checks pass.

The existing `braj@bkpandey.com` login is scoped to `public_html`. Use it for public files and a separate login scoped to the private backend directory. An account-home login that reaches both siblings is also supported, but two scoped logins are preferable. The script refuses
to place private PHP in the public directory. Certificate errors must be fixed
using the provider's correct hostname; do not turn certificate validation off.
The first deployment uploads all release files; later deployments compare
SHA-256 hashes against a private manifest and send changed files only.

Rollback: retrieve the changed files from
`bkpandey-private/releases/COMMIT_SHA/` through cPanel and restore them to their
original relative paths. Database content and owner uploads are preserved;
this workflow does not run destructive schema migrations or delete remote data.
Database updates are versioned separately. After backing up MySQL, apply them
through the authenticated **Admin → Database** screen or the private CLI.
See `DATABASE.md` for setup, checksums, locking, and interrupted-run handling.

Public app endpoints use clean routes (`/admin`, `/admin/setup`, `/api/site-settings`, `/api/media/ID/view`, `/media/profile`, and `/resume/download`). Direct script-suffix requests return 404. This reduces technology exposure; it does not make the server technology impossible to identify.

Verified status: source is uploaded to `brajkishorpandey/bkpandey-portfolio`.
The first automatic run passed validation. Deployment cannot authenticate until
production environment secrets are supplied. The connected GitHub tools can
write code and workflows but do not expose secret-management operations.
A new private-folder FTP account password must be entered by its owner through
cPanel. The one-time application owner password must also be entered through
`/admin/setup`. No owner or deployment password is included in this repository.
