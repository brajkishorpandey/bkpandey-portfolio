# Contact email

The clean endpoint `/api/contact` sends plain-text messages only to the contact
address configured by the authenticated owner under **Website & résumé**.
The request cannot set a recipient, sender, CC, BCC, headers, or attachments.
The sender/envelope address is fixed as `contact@bkpandey.com`; visitor email is
validated and used only as Reply-To. Existing admin email subject/body templates
are rendered on the server and safely encoded.

## Enable sending

1. In Cloudflare Turnstile, create a **Managed** widget restricted to
   `bkpandey.com`. Production must use real keys, not Cloudflare's demo keys.
2. Sign in at `/admin`, choose **Contact email**, enter the site key and secret,
   and enable verified email. The owner must enter and submit new credentials.
   The secret is stored with mode 0600 in the private `contact-config.json` file
   outside `public_html`; it is never returned in public settings or admin HTML.
   Environment equivalents are `BP_TURNSTILE_SITEKEY`, `BP_TURNSTILE_SECRET`,
   and `BP_CONTACT_ENABLED=1`.
3. Use **Send test email** in that owner-only screen. It sends one fixed message
   to the configured recipient, limited to three tests per hour. Confirm receipt
   and check cPanel's mail delivery report and domain SPF/DKIM if delivery fails.

The public form remains disabled until verification is configured. A disabled
form shows the existing direct email/WhatsApp alternatives. No demo credentials,
test bypass, or fallback without verification is enabled in production.

## Abuse controls

- HTTPS, same-origin request context, a required custom request header, and an
  essential Secure/HttpOnly/SameSite=Strict session cookie scoped to the API.
- A random one-use form token, a minimum fill time, expiration, a hidden trap,
  strict field allowlist, bounded JSON input, and header-injection protection.
- Mandatory server-side Turnstile verification using verified HTTPS, with fixed
  hostname, action `contact`, and session binding checks. A provider error fails
  closed. Verification tokens cannot be accepted twice.
- Atomic file locking for rate reservations: 20 form requests/hour/IP, 5 send
  attempts/15 minutes/IP, 3 sends/hour/IP, 8/day/IP, 3/day/reply address, and global
  caps of 20/hour and 50/day. Owner tests have their own three/hour budget.
- Private bounded limiter state uses salted hashes rather than raw IP/email
  identifiers. Expired buckets are pruned when subsequent requests arrive.
  Message contents are not stored in the website database.

Rate limits count accepted reservations even if the mail service later rejects
a message. Mail acceptance confirms queuing, not inbox delivery. Hosting mail
logs and recipient spam filters remain outside application control.

These controls limit automation and abuse. No publicly accessible form can
guarantee every sender is human or block a determined distributed attacker.
The host's existing firewall and request protections remain necessary.

Runtime contact configuration and limiter files are excluded from Git, release
archives, and CI deployment. A normal release preserves them on the server.
