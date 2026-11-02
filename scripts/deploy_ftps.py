"""Incremental deployment using certificate-verified explicit FTPS. No remote commands."""
from pathlib import Path, PurePosixPath
from ftplib import FTP_TLS, error_perm
import hashlib, io, json, os, ssl, sys, uuid
ROOT=Path(__file__).resolve().parents[1]
PROTECTED={'database.php','setup-attempts.json','cache','uploads','releases','deploy-state.json'}
def release_files(root=ROOT):
    result={}
    for base,prefix in [(root/'dist','public_html'),(root/'hosting/public_html','public_html'),(root/'hosting/bkpandey-private','bkpandey-private')]:
        for path in sorted(base.rglob('*')):
            parts=path.relative_to(base).parts
            if any(p in PROTECTED or p=='__pycache__' for p in parts) or not path.is_file():continue
            if path.is_symlink():raise ValueError('Release files may not be symbolic links.')
            if base==root/'dist' and path.name=='.htaccess':continue
            remote=prefix+'/'+path.relative_to(base).as_posix()
            result[remote]=path
    return result

def ensure_directory(ftp,path,private=False):
    current=''
    for part in PurePosixPath(path).parts:
        if part in {'','.'}:continue
        if part=='..':raise ValueError('Parent paths are not allowed.')
        current=current+'/'+part if current else part
        try:ftp.mkd(current)
        except error_perm as e:
            if not str(e).startswith('550'):raise
        if private:ftp.sendcmd('SITE CHMOD 0700 '+current)

def upload_bytes(ftp,remote,data,private=False):
    ensure_directory(ftp,str(PurePosixPath(remote).parent),private)
    temp=remote+'.deploy-'+uuid.uuid4().hex
    ftp.storbinary('STOR '+temp,io.BytesIO(data))
    ftp.sendcmd('SITE CHMOD '+('0600 ' if private else '0644 ')+temp)
    ftp.rename(temp,remote)

def deploy():
    host=os.environ.get('FTPS_HOST','');user=os.environ.get('FTPS_USERNAME','');password=os.environ.get('FTPS_PASSWORD','')
    if not all([host,user,password]):raise ValueError('Configure FTPS_HOST, FTPS_USERNAME, and FTPS_PASSWORD as production environment secrets.')
    if '/' in host or ':' in host or any(c.isspace() for c in host):raise ValueError('FTPS_HOST must be a hostname without protocol or port.')
    context=ssl.create_default_context();context.minimum_version=ssl.TLSVersion.TLSv1_2
    ftp=FTP_TLS(context=context,timeout=60)
    ftp.connect(host,21);ftp.login(user,password);ftp.prot_p();ftp.set_pasv(True)
    remote_root=os.environ.get('FTPS_ROOT','').strip() or '/';ftp.cwd(remote_root)
    # The account must reach both sibling directories; do not nest application code in public_html.
    names={str(PurePosixPath(n).name) for n in ftp.nlst()}
    if not {'public_html','bkpandey-private'}.issubset(names):raise ValueError('FTPS_ROOT must contain public_html and bkpandey-private. Use an appropriately scoped deployment account.')
    previous={}
    try:
        chunks=[];ftp.retrbinary('RETR bkpandey-private/deploy-state.json',chunks.append);previous=json.loads(b''.join(chunks)).get('files',{})
    except error_perm as e:
        if not str(e).startswith('550'):raise
    files=release_files();state={};changed=[]
    for remote,path in files.items():
        digest=hashlib.sha256(path.read_bytes()).hexdigest();state[remote]=digest
        if previous.get(remote)!=digest:changed.append((remote,path))
    release=os.environ.get('RELEASE_ID','manual')
    if not all(c.isalnum() or c in '-_' for c in release):raise ValueError('Invalid release identifier.')
    # Application code first; public entrypoints last. Existing private runtime files are never touched.
    changed.sort(key=lambda item:(0 if item[0].startswith('bkpandey-private/') else 3 if item[1].name=='.htaccess' else 2 if item[1].suffix=='.php' else 1,item[0]))
    for remote,path in changed:
        private=remote.startswith('bkpandey-private/')
        # Preserve the previous version of every changed file for rollback.
        chunks=[]
        try:
            ftp.retrbinary('RETR '+remote,chunks.append)
        except error_perm as e:
            if not str(e).startswith('550'):raise
        else:
            upload_bytes(ftp,'bkpandey-private/releases/'+release+'/'+remote,b''.join(chunks),True)
        upload_bytes(ftp,remote,path.read_bytes(),private)
    upload_bytes(ftp,'bkpandey-private/cache/generation',uuid.uuid4().hex.encode(),True)
    upload_bytes(ftp,'bkpandey-private/deploy-state.json',json.dumps({'release':release,'files':state},sort_keys=True).encode(),True)
    ftp.quit();print(f'Deployed {len(changed)} changed files. Runtime data preserved; previous files retained for rollback.')
if __name__=='__main__':
    try:deploy()
    except Exception as error:
        # Never print server exception payloads, which can echo account or path details.
        if isinstance(error,ValueError):print(str(error),file=sys.stderr)
        else:print('FTPS deployment failed. Check the credentials, certificate, account root, and permissions.',file=sys.stderr)
        sys.exit(1)
