# Cloudflare-only website origin

The owner's allowlist contains 15 IPv4 and seven IPv6 Cloudflare networks.
`cloudflare-origin-ranges.json` records the exact networks and their public
source: https://www.cloudflare.com/ips/ .

The candidate gate is `hosting/cloudflare-origin-gate.conf`. Apache checks the
connection peer (`CONN_REMOTE_ADDR`). LiteSpeed uses its server address
(`REMOTE_ADDR`), whose handling is verified against the live host before
activation. Neither branch reads `CF-Connecting-IP`, `X-Forwarded-For`, or other
request headers as evidence that a connection came from Cloudflare.

If LiteSpeed's server settings are changed to restore visitor addresses, this
rule must be reviewed by the host. It must not be used with an unsafe setting
that trusts forwarded IP headers from arbitrary clients. Live preflight tests
check both Cloudflare admission and direct denial with forged proxy headers.

The conditional denial is processed after ordinary child-directory authorization
rules, so a child `Require all granted` does not reopen the origin. Existing
denials for sensitive files continue to apply to allowed Cloudflare traffic.

## Activation and recovery

1. Keep the domain's web DNS records proxied through Cloudflare. Delegating
   nameservers alone does not enable the HTTP proxy.
2. Test the gate on an isolated static path on the actual hosting server.
3. Preserve a copy of the current root `.htaccess` outside `public_html`.
4. Add the tested gate to the root `.htaccess`, preserving existing routes,
   headers, and file protections. Check the homepage, admin, blog, and assets.
5. Confirm direct-origin connections are denied, including forged proxy headers.

Recovery uses the separate cPanel File Manager at the hosting provider. Restore
the private backup if Cloudflare proxying is disabled or the host changes its
proxy architecture. The rule applies to this website's HTTP/HTTPS requests;
hosting account services use their own access controls.

This is a web-server access rule, not a server-wide network firewall. Cloudflare
addresses are shared across customers. Account-specific origin authentication
and a host-managed network firewall provide additional protection when supported.

GitHub validation runs real Apache checks for direct denial, forged proxy headers,
child-directory authorization, IPv4/IPv6, and visitor-IP restoration. Local runs
skip those integration checks when Apache is unavailable.

Keep this allowlist aligned with Cloudflare's published networks when they
change. Do not broaden it with visitor IPs, loopback, or header-based exceptions.
