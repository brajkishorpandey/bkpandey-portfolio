"""Restore non-code media by hash and browser PDF.js from the locked npm package."""
from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
import hashlib,json,os,shutil,tempfile,urllib.request
ROOT=Path(__file__).resolve().parents[1]
def restore(entry):
    relative=entry['path'];target=(ROOT/relative).resolve()
    if not relative.startswith('dist/assets/') or not target.is_relative_to((ROOT/'dist/assets').resolve()):raise ValueError('Invalid media path.')
    if not entry['url'].startswith('https://bkpandey.com/assets/'):raise ValueError('Invalid media origin.')
    if target.is_file() and hashlib.sha256(target.read_bytes()).hexdigest()==entry['sha256']:return
    target.parent.mkdir(parents=True,exist_ok=True)
    temp=None
    try:
        with urllib.request.urlopen(entry['url'],timeout=90) as response,tempfile.NamedTemporaryFile(dir=target.parent,delete=False) as out:
            temp=Path(out.name);size=0;digest=hashlib.sha256()
            while chunk:=response.read(65536):
                size+=len(chunk)
                if size>entry['size']:raise ValueError('Media exceeds its verified size.')
                digest.update(chunk);out.write(chunk)
        if size!=entry['size'] or digest.hexdigest()!=entry['sha256']:raise ValueError('Media integrity check failed: '+relative)
        os.chmod(temp,0o644);os.replace(temp,target)
    finally:
        if temp and temp.exists():temp.unlink()
def prepare():
    entries=json.loads((ROOT/'assets-manifest.json').read_text())['assets']
    with ThreadPoolExecutor(max_workers=4) as pool:list(pool.map(restore,entries))
    source=ROOT/'node_modules/pdfjs-dist';destination=ROOT/'dist/assets/vendor/pdfjs'
    if source.is_dir():
        destination.mkdir(parents=True,exist_ok=True)
        for file in ['pdf.min.mjs','pdf.worker.min.mjs']:shutil.copy2(source/'build'/file,destination/file)
        for folder in ['cmaps','standard_fonts','wasm','iccs']:shutil.copytree(source/folder,destination/folder,dirs_exist_ok=True)
        shutil.copy2(source/'LICENSE',destination/'LICENSE.txt')
    elif not (destination/'pdf.min.mjs').is_file():raise RuntimeError('Run npm ci --ignore-scripts --omit=optional before preparing assets.')
    print(f'Restored {len(entries)} hash-verified media assets and the locked PDF viewer.')
if __name__=='__main__':prepare()
