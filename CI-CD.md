# Activate automatic deployment

The included `.github/workflows/deploy.yml` is ready for a GitHub repository.
It is not active until the repository, production environment, and its secrets
are configured. There is no deployment credential in the code or archive.

1. Create a private repository in the owner's personal GitHub account and push
   this source with `main` as the default branch.
2. In GitHub, create the `production` environment and restrict it to `main`.
   A required reviewer is optional if you prefer approval before a release.
3. Add separate production environment secrets:

   | Secret | Value |
   | --- | --- |
   | `FTPS_HOST` | Provider's FTP hostname with a matching valid TLS certificate |
   | `FTPS_USERNAME` | Dedicated deployment FTP account |
   | `FTPS_PASSWORD` | That deployment account's password |
   | `FTPS_ROOT` | Directory containing both `public_html` and `bkpandey-private`; usually `/` in an account-home FTP login |

4. Enable branch protection, restrict repository write access, and enable 2FA.
5. Run **Validate and deploy portfolio** manually for the first release. Confirm
   the successful run and live homepage, blog, PDF viewer, and admin sign-in.
   Subsequent pushes to `main` deploy automatically after checks pass.

The existing FTP account must have an appropriate account root. A login scoped
only to `public_html` cannot deploy the private PHP sibling. The script refuses
to place private PHP in the public directory. Certificate errors must be fixed
using the provider's correct hostname; do not turn certificate validation off.
The first deployment uploads all release files; later deployments compare
SHA-256 hashes against a private manifest and send changed files only.

Rollback: retrieve the changed files from
`bkpandey-private/releases/COMMIT_SHA/` through cPanel and restore them to their
original relative paths. Database content and owner uploads are preserved;
this workflow does not run destructive schema migrations or delete remote data.

Public app endpoints use clean routes (`/admin`, `/admin/setup`, `/api/site-settings`, `/api/media/ID/view`, `/media/profile`, and `/resume/download`). Direct script-suffix requests return 404. This reduces technology exposure; it does not make the server technology impossible to identify.
