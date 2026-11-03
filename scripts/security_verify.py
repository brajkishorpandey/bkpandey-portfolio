"""Bounded, non-destructive verification for the owner's production website.

No password guesses, valid email submissions, account changes, uploaded scripts,
or content changes. Never prints response bodies, cookies, tokens, or secrets.
"""
import http.client
from html.parser import HTMLParser
import json
import re
import ssl
import time
from urllib.parse import urlencode

HOST = 'bkpandey.com'
checks = []
observations = []


def request(path, method='GET', headers=None, body=None, secure=True):
    connection = (http.client.HTTPSConnection(HOST, timeout=20, context=ssl.create_default_context())
                  if secure else http.client.HTTPConnection(HOST, timeout=20))
    try:
        separator = '&' if '?' in path else '?'
        connection.request(method, path + separator + 'security_check=' + str(time.time_ns()), body,
                           {'Cache-Control': 'no-cache', **(headers or {})})
        response = connection.getresponse()
        pairs = response.getheaders()
        text = response.read(524288).decode('utf-8', errors='replace')
        return response.status, {k.lower(): v for k, v in pairs}, pairs, text
    finally:
        connection.close()


def record(name, passed, **evidence):
    entry = {'check': name, 'passed': bool(passed), **evidence}
    checks.append(entry)
    print(json.dumps(entry), flush=True)


class Form(HTMLParser):
    def __init__(self):
        super().__init__()
        self.csrf = ''

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('name') == 'csrf':
            self.csrf = attrs.get('value', '')


def main():
    with ssl.create_default_context().wrap_socket(__import__('socket').create_connection((HOST, 443), timeout=20), server_hostname=HOST) as connection:
        record('TLS certificate and hostname verification', True, negotiated=connection.version(), expires=connection.getpeercert().get('notAfter'))
    status, headers, _, _ = request('/', secure=False)
    location = headers.get('location', '')
    record('HTTP upgrades to HTTPS', status in {301, 302, 307, 308} and location.startswith('https://' + HOST + '/'), status=status)

    for path in ['/', '/blog/', '/admin']:
        status, headers, pairs, text = request(path)
        record('Available secure page ' + path, status == 200, status=status)
        for key, expected in [('x-content-type-options', 'nosniff'), ('x-frame-options', 'SAMEORIGIN'), ('referrer-policy', 'strict-origin-when-cross-origin')]:
            record(path + ' ' + key, headers.get(key, '').lower() == expected.lower())
        hsts = re.search(r'max-age=(\d+)', headers.get('strict-transport-security', ''))
        record(path + ' HSTS', hsts and int(hsts[1]) >= 31536000)
        csp = headers.get('content-security-policy', '')
        record(path + ' CSP', all(value in csp for value in ["default-src 'self'", "object-src 'none'", "base-uri 'self'", "form-action 'self'", "frame-ancestors 'self'"]))
        record(path + ' PHP version hidden', 'x-powered-by' not in headers and not re.search(r'(PHP/|LiteSpeed/|Apache/)[0-9]', headers.get('server', ''), re.I))
        record(path + ' no diagnostic disclosure', not re.search(r'(Fatal error:|Stack trace:|SQLSTATE\[|Warning:.*on line \d|/home/bkpandey/)', text))
        if path == '/admin':
            record('Anonymous admin receives sign-in only', 'name="password"' in text and 'name="action" value="save"' not in text)
            record('Admin responses cannot be cached', 'no-store' in headers.get('cache-control', ''))
            record('Admin is excluded from indexing', 'noindex' in headers.get('x-robots-tag', ''))
            cookies = [v for k, v in pairs if k.lower() == 'set-cookie' and v.startswith('bp_publisher=')]
            record('Publisher session cookie flags', bool(cookies) and all(all(flag in value.lower() for flag in ['secure', 'httponly', 'samesite=strict']) for value in cookies))
            form = Form(); form.feed(text)
            record('Unpredictable admin CSRF token present', bool(re.fullmatch('[0-9a-f]{64}', form.csrf)))
            cookie = '; '.join(value.split(';', 1)[0] for value in cookies)
            for token, expected in [('', 'form expired'), (form.csrf, 'sign in before making changes')]:
                # Cache invalidation is the least-impact state action if an authorization bug exists.
                data = urlencode({'action': 'cache', 'csrf': token})
                post_status, post_headers, _, post_text = request('/admin', 'POST', {'Cookie': cookie, 'Content-Type': 'application/x-www-form-urlencoded'}, data)
                record('Admin rejects ' + ('missing CSRF' if not token else 'unauthenticated action with valid CSRF'), post_status == 200 and expected in post_text.lower() and 'name="password"' in post_text and 'no-store' in post_headers.get('cache-control', ''))

    for path in ['/admin?site=1', '/admin?database=1', '/admin?contact=1', '/admin?new=1', '/admin?account=1']:
        status, _, _, text = request(path)
        record('Admin menu requires login ' + path, status == 200 and 'name="password"' in text and 'name="action" value="site"' not in text)

    for path in ['/.env', '/.git/config', '/.user.ini', '/php.ini', '/adminer.php', '/index.php', '/index%2ephp', '/blog/admin.php', '/media/contact.php', '/bkpandey-private/database.php', '/bkpandey-private/', '/uploads/', '/storage/', '/assets/', '/admin/setup', '/api/media/2147483647/view']:
        status, _, _, _ = request(path)
        record('Protected path ' + path, status in {403, 404}, status=status)

    status, _, _, text = request('/api/site-settings')
    settings = json.loads(text) if status == 200 else {}
    sensitive = [key for key in settings if re.search(r'(password|secret|token|db_|storage_key|admin_id)', key, re.I)]
    record('Public settings exclude credentials', status == 200 and not sensitive)

    for name, method, headers, body in [
        ('Missing form header', 'GET', {}, None),
        ('Cross-origin submission', 'POST', {'Origin': 'https://example.test', 'X-BP-Contact': '1', 'Content-Type': 'application/json'}, '{}'),
        ('Unsupported contact method', 'PUT', {}, None),
        ('Unverified contact submission', 'POST', {'Origin': 'https://bkpandey.com', 'X-BP-Contact': '1', 'Content-Type': 'application/json'}, '{}'),
    ]:
        status, headers, _, _ = request('/api/contact', method, headers, body)
        record('Contact blocks ' + name, status in {400, 403, 405, 429, 503}, status=status)
        record('Contact error is not cached ' + name, 'no-store' in headers.get('cache-control', ''))
    status, _, _, _ = request('/api/contact', headers={'X-BP-Contact': '1', 'Sec-Fetch-Site': 'same-origin'})
    observations.append({'contact_form': 'disabled-or-unconfigured' if status == 503 else 'enabled' if status == 200 else 'unverified', 'status': status})

    status, headers, _, _ = request('/resume/download', headers={'Range': 'bytes=0-31'})
    record('Resume MIME and range handling', status == 206 and headers.get('content-type', '').startswith('application/pdf') and headers.get('content-range', '').startswith('bytes 0-31/'))
    status, _, _, _ = request('/resume/download', headers={'Range': 'bytes=0-1,4-5'})
    record('Invalid multiple ranges rejected', status == 416, status=status)
    result = {'checks': len(checks), 'passed': sum(item['passed'] for item in checks), 'observations': observations}
    print(json.dumps({'summary': result}), flush=True)
    if result['passed'] != result['checks']:
        raise SystemExit('Security checks failed; inspect redacted check names above.')


if __name__ == '__main__':
    main()
