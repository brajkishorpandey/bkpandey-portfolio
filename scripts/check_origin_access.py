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

def check(path,origin=None,headers=None):
    connection=DirectOrigin(origin) if origin else http.client.HTTPSConnection(HOST,timeout=20,context=ssl.create_default_context())
    try:
        connection.request('GET',path+'?origin_check='+str(time.time_ns()),headers={'Cache-Control':'no-cache',**(headers or {})})
        response=connection.getresponse();response.read(4096)
        return {'status':response.status,'server':response.getheader('Server'),'cloudflare_ray':response.getheader('CF-Ray'),'cache':response.getheader('CF-Cache-Status')}
    except (OSError,ssl.SSLError,http.client.HTTPException) as error:
        return {'error':type(error).__name__}
    finally:connection.close()

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--origin',required=True);parser.add_argument('--probe',required=True);args=parser.parse_args()
    ipaddress.ip_address(args.origin)
    if not re.fullmatch(r'origin-policy-check-[a-f0-9]{12}',args.probe):raise SystemExit('Use the isolated policy probe name.')
    data=json.loads((Path(__file__).resolve().parents[1]/'cloudflare-origin-ranges.json').read_text())
    networks=[ipaddress.ip_network(value) for value in data['ipv4']+data['ipv6']]
    try:
        addresses=sorted({item[4][0] for item in socket.getaddrinfo(HOST,443,type=socket.SOCK_STREAM)})
        print(json.dumps({'dns':[{'address':address,'cloudflare':any(ipaddress.ip_address(address) in network for network in networks)} for address in addresses]}))
    except OSError:print(json.dumps({'dns_error':True}))
    for variant,path in [('connection-peer','/'+args.probe+'/index.html'),('baseline','/'+args.probe+'-baseline/check.html'),('server-address','/'+args.probe+'-litespeed/check.html')]:
        print(json.dumps({'variant':variant,'route':'public',**check(path)}))
        print(json.dumps({'variant':variant,'route':'direct-origin',**check(path,args.origin)}))
        print(json.dumps({'variant':variant,'route':'direct-origin-forged-headers',**check(path,args.origin,{'CF-Connecting-IP':'173.245.48.1','X-Forwarded-For':'173.245.48.1','X-Real-IP':'173.245.48.1'})}))
