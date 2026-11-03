"""Encode the exact allowlist for LiteSpeed's server-owned connection variables."""
import ipaddress
import json
from pathlib import Path

ROOT=Path(__file__).resolve().parents[1]
BYTE=r'(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])'

def peer_pattern(ranges):
    ipv4=[];ipv6=[]
    for value in ranges['ipv4']:
        network=ipaddress.ip_network(value,strict=True);octets=network.network_address.packed;bits=network.prefixlen;parts=[]
        for octet in octets:
            fixed=min(8,max(0,bits));bits-=8
            if fixed==8:parts.append(str(octet))
            elif fixed==0:parts.append(BYTE)
            else:parts.append('(?:'+'|'.join(str(n) for n in range(octet,octet+(1<<(8-fixed))))+')')
        ipv4.append(r'\.'.join(parts))
    for value in ranges['ipv6']:
        network=ipaddress.ip_network(value,strict=True)
        if network.prefixlen not in (29,32):raise ValueError('Review a new IPv6 prefix before generating a gate.')
        first=int(network.network_address)>>112;second=(int(network.network_address)>>96)&65535
        seconds=[format(n,'x') for n in range(second,second+(1<<(32-network.prefixlen)))]
        ipv6.append(format(first,'x')+':(?:'+'|'.join(seconds)+'):[0-9a-f:]+')
    return r'^(?:(?:::ffff:)?(?:'+'|'.join(ipv4)+')|(?:'+'|'.join(ipv6)+'))$'

def litespeed_gate(ranges):
    pattern=peer_pattern(ranges)
    return ('# BEGIN Cloudflare-only origin\n'
            '# PROXY_REMOTE_ADDR is set internally by LiteSpeed when it restores a visitor IP.\n'
            '# Never use an HTTP header to decide whether the connection is allowed.\n'
            'RewriteEngine On\nRewriteOptions InheritDownBefore\n'
            'RewriteCond %{ENV:PROXY_REMOTE_ADDR} !^$\n'
            'RewriteCond %{ENV:PROXY_REMOTE_ADDR} !'+pattern+' [NC]\n'
            'RewriteRule ^ - [F,END]\n'
            'RewriteCond %{ENV:PROXY_REMOTE_ADDR} ^$\n'
            'RewriteCond %{REMOTE_ADDR} !'+pattern+' [NC]\n'
            'RewriteRule ^ - [F,END]\n'
            '# END Cloudflare-only origin\n')

if __name__=='__main__':
    ranges=json.loads((ROOT/'cloudflare-origin-ranges.json').read_text())
    (ROOT/'hosting/cloudflare-origin-litespeed.conf').write_text(litespeed_gate(ranges))
