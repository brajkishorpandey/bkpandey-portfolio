"""Exercise the origin gate with real Apache, including restored visitor IPs."""
import grp
import http.client
import ipaddress
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import socket
import subprocess
import tempfile
import time
import unittest

ROOT=Path(__file__).resolve().parents[1]
GATE=(ROOT/'hosting/cloudflare-origin-gate.conf').read_text()
LS_GATE=(ROOT/'hosting/cloudflare-origin-litespeed.conf').read_text()

class OriginRanges(unittest.TestCase):
    def test_only_the_configured_cloudflare_networks_are_present(self):
        ranges=json.loads((ROOT/'cloudflare-origin-ranges.json').read_text())
        networks=ranges['ipv4']+ranges['ipv6']
        self.assertEqual(len(ranges['ipv4']),15)
        self.assertEqual(len(ranges['ipv6']),7)
        self.assertEqual(len(networks),len(set(networks)))
        for network in networks:
            ipaddress.ip_network(network,strict=True)
        self.assertEqual(re.findall(r"-ipmatch '([^']+)'",GATE),networks)
        self.assertEqual(re.findall(r'^\s*Require ip (.+)$',LS_GATE,re.M),networks)
        self.assertNotIn('%{REMOTE_ADDR}',GATE)
        self.assertIn('%{CONN_REMOTE_ADDR}',GATE)
        self.assertNotIn('HTTP:',GATE)
        self.assertNotIn('HTTP_',GATE)
        self.assertNotIn('HTTP',LS_GATE)

@unittest.skipUnless(shutil.which('apache2'),'Apache is installed in GitHub validation')
class ApacheOriginGate(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp=tempfile.TemporaryDirectory(prefix='bp-origin-')
        cls.base=Path(cls.temp.name);cls.base.chmod(0o755)
        cls.public=cls.base/'www';cls.public.mkdir(mode=0o755)
        (cls.public/'probe.txt').write_text('origin probe')
        (cls.public/'private.txt').write_text('private fixture')
        child=cls.public/'child';child.mkdir(mode=0o755)
        (child/'probe.txt').write_text('child probe')
        (child/'.htaccess').write_text('Require all granted\n')
        (cls.public/'.htaccess').write_text(GATE)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1',0));cls.port=sock.getsockname()[1]
        cls.ipv6=False
        try:
            with socket.socket(socket.AF_INET6) as sock:sock.bind(('::1',0));cls.ipv6=True
        except OSError:pass
        modules=Path('/usr/lib/apache2/modules')
        user='www-data' if os.getuid()==0 else pwd.getpwuid(os.getuid()).pw_name
        group='www-data' if os.getuid()==0 else grp.getgrgid(os.getgid()).gr_name
        config=[f'ServerRoot "/etc/apache2"', 'ServerName localhost',f'PidFile "{cls.base}/httpd.pid"',f'DefaultRuntimeDir "{cls.base}"',f'Listen 127.0.0.1:{cls.port}',f'User {user}',f'Group {group}',f'ErrorLog "{cls.base}/error.log"','LogLevel warn']
        if cls.ipv6:config.append(f'Listen [::1]:{cls.port}')
        for name in ['mpm_event','authz_core','authz_host','remoteip']:
            config.append(f'LoadModule {name}_module "{modules}/mod_{name}.so"')
        config+=['RemoteIPHeader CF-Connecting-IP','RemoteIPTrustedProxy 127.0.0.1 ::1',f'DocumentRoot "{cls.public}"',f'<Directory "{cls.public}">','AllowOverride All','Require all granted','</Directory>']
        cls.config=cls.base/'httpd.conf';cls.config.write_text('\n'.join(config)+'\n')
        subprocess.run(['apache2','-t','-f',str(cls.config)],check=True,capture_output=True)
        cls.process=subprocess.Popen(['apache2','-f',str(cls.config),'-DFOREGROUND'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
        for _ in range(100):
            if cls.process.poll() is not None:raise RuntimeError((cls.base/'error.log').read_text())
            try:
                with socket.create_connection(('127.0.0.1',cls.port),timeout=.2):return
            except OSError:time.sleep(.05)
        raise RuntimeError('Apache test server did not start')

    @classmethod
    def tearDownClass(cls):
        cls.process.terminate()
        try:cls.process.wait(timeout=5)
        except subprocess.TimeoutExpired:cls.process.kill();cls.process.wait(timeout=5)
        cls.temp.cleanup()

    def tearDown(self):
        (self.public/'.htaccess').write_text(GATE)

    def request(self,path='/probe.txt',headers=None,host='127.0.0.1'):
        connection=http.client.HTTPConnection(host,self.port,timeout=5)
        try:
            connection.request('GET',path,headers=headers or {})
            response=connection.getresponse();response.read();return response.status
        finally:connection.close()

    def test_direct_connection_is_denied(self):
        self.assertEqual(self.request(),403)

    def test_forwarded_headers_cannot_claim_a_cloudflare_peer(self):
        for address in ['173.245.48.1','2606:4700::1']:
            with self.subTest(address=address):
                self.assertEqual(self.request(headers={'CF-Connecting-IP':address,'X-Forwarded-For':address,'X-Real-IP':address}),403)

    def test_child_authorization_cannot_open_the_origin(self):
        self.assertEqual(self.request('/child/probe.txt'),403)

    def test_allowed_peer_survives_visitor_ip_restoration(self):
        # Only the isolated test copy permits loopback. Production has no exception.
        (self.public/'.htaccess').write_text(GATE.replace('173.245.48.0/20','127.0.0.1/32'))
        self.assertEqual(self.request(headers={'CF-Connecting-IP':'198.51.100.17'}),200)

    def test_existing_private_file_denial_is_preserved(self):
        gate=GATE.replace('173.245.48.0/20','127.0.0.1/32')
        (self.public/'.htaccess').write_text(gate+'\n<Files "private.txt">\nRequire all denied\n</Files>\n')
        self.assertEqual(self.request('/private.txt'),403)

    def test_ipv6_peer_and_restored_visitor(self):
        if not self.ipv6:self.skipTest('IPv6 loopback is unavailable on this runner')
        self.assertEqual(self.request(host='::1'),403)
        (self.public/'.htaccess').write_text(GATE.replace('2400:cb00::/32','::1/128'))
        self.assertEqual(self.request(host='::1',headers={'CF-Connecting-IP':'198.51.100.17'}),200)

    def test_native_gate_is_not_overridden_by_child_directory_grant(self):
        (self.public/'.htaccess').write_text(LS_GATE)
        self.assertEqual(self.request('/child/probe.txt'),403)

    def test_native_gate_preserves_sensitive_file_denial(self):
        gate=LS_GATE.replace('173.245.48.0/20','127.0.0.1/32')
        (self.public/'.htaccess').write_text(gate+'\n<Files "private.txt">\nRequire all denied\n</Files>\n')
        self.assertEqual(self.request('/probe.txt'),200)
        self.assertEqual(self.request('/private.txt'),403)

if __name__=='__main__':unittest.main()
