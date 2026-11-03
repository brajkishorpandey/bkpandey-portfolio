"""Read-only public/origin checks for the owner's website. Never disables TLS."""
import argparse
import http.client
import ipaddress
import json
from pathlib import Path
import re
import socket
import ssl
import time

HOST='bkpandey.com'

class DirectOrigin(http.client.HTTPSConnection):
    def __init__(self,origin):
        super().__init__(HOST,timeout=20,context=ssl.create_default_context())
        self.origin=origin
    def connect(self):
        connection=socket.create_connection((self.origin,443),self.timeout)
        self.sock=self._context.wrap_socket(connection,server_hostname=HOST)

def check(path,origin=None,headers=None,plain_http=False):
    connection=http.client.HTTPConnection(origin,80,timeout=20) if plain_http else (DirectOrigin(origin) if origin else http.client.HTTPSConnection(HOST,timeout=20,context=ssl.create_default_context()))
    try:
        connection.request('GET',path+'?origin_check='+str(time.time_ns()),headers={'Host':HOST,'Cache-Control':'no-cache',**(headers or {})})
        response=connection.getresponse();response.read(4096)
        return {'status':response.status,'server':response.getheader('Server'),'cloudflare_ray':response.getheader('CF-Ray'),'cache':response.getheader('CF-Cache-Status')}
    except (OSError,ssl.SSLError,http.client.HTTPException) as error:
        return {'error':type(error).__name__}
    finally:connection.close()

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--origin',required=True);parser.add_argument('--probe');args=parser.parse_args()
    ipaddress.ip_address(args.origin)
    if args.probe and not re.fullmatch(r'origin-policy-check-[a-f0-9]{12}',args.probe):raise SystemExit('Use the isolated policy probe name.')
    data=json.loads((Path(__file__).resolve().parents[1]/'cloudflare-origin-ranges.json').read_text())
    networks=[ipaddress.ip_network(value) for value in data['ipv4']+data['ipv6']]
    try:
        addresses=sorted({item[4][0] for item in socket.getaddrinfo(HOST,443,type=socket.SOCK_STREAM)})
        print(json.dumps({'dns':[{'address':address,'cloudflare':any(ipaddress.ip_address(address) in network for network in networks)} for address in addresses]}))
    except OSError:print(json.dumps({'dns_error':True}))
    failures=[]
    if args.probe:
        paths=[('connection-peer','/'+args.probe+'/index.html',200),('child-authorization','/'+args.probe+'/child/check.html',200),('sensitive-file','/'+args.probe+'/blocked.txt',403),('baseline','/'+args.probe+'-baseline/check.html',200)]
    else:
        paths=[('homepage','/',200),('blog','/blog/',200),('blog-feed','/blog/feed.xml',200),('admin','/admin',200),('resume','/resume/download',200),('portrait','/media/profile',200),('stylesheet','/assets/css/site.css',200),('hidden-config','/.user.ini',403),('script-extension','/adminer.php',404)]
    forged={'CF-Connecting-IP':'173.245.48.1','X-Forwarded-For':'173.245.48.1','X-Real-IP':'173.245.48.1','Proxy-Remote-Addr':'173.245.48.1'}
    for variant,path,public_status in paths:
        for route,origin,headers in [('public',None,None),('direct-origin',args.origin,None),('direct-origin-forged-headers',args.origin,forged)]:
            result=check(path,origin,headers)
            expected=public_status if route=='public' else (200 if args.probe and variant=='baseline' else 403)
            print(json.dumps({'variant':variant,'route':route,'expected':expected,**result}))
            if result.get('status')!=expected:failures.append(variant+':'+route)
    if not args.probe:
        for route,headers in [('direct-origin-http',None),('direct-origin-http-forged-headers',forged)]:
            result=check('/',args.origin,headers,plain_http=True)
            print(json.dumps({'variant':'homepage','route':route,'expected':403,**result}))
            if result.get('status')!=403:failures.append(route)
    if failures:raise SystemExit('Origin access checks failed: '+', '.join(failures))
