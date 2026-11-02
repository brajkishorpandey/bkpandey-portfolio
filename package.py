"""Create shared-hosting release archives without private runtime files."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import argparse
ROOT=Path(__file__).parent
EXCLUDE={'database.php','setup-attempts.json'}
def package(output,patch=False):
 output=Path(output);output.parent.mkdir(parents=True,exist_ok=True)
 with ZipFile(output,'w',ZIP_DEFLATED,compresslevel=6) as z:
  for base,prefix in [(ROOT/'dist','public_html'),(ROOT/'hosting/public_html','public_html'),(ROOT/'hosting/bkpandey-private','bkpandey-private')]:
   for p in sorted(base.rglob('*')):
    if not p.is_file() or p.name in EXCLUDE or any(x in {'cache','uploads','__pycache__'} for x in p.relative_to(base).parts):continue
    if patch and base==ROOT/'dist' and p.relative_to(base).as_posix().startswith('assets/documents/blog/'):continue
    target=prefix+'/'+p.relative_to(base).as_posix()
    # The hosting overlay owns Apache/PHP configuration.
    if base==ROOT/'dist' and p.name=='.htaccess':continue
    z.write(p,target)
  z.write(ROOT/'hosting/bkpandey-private/DEPLOYMENT.md','START_HERE.md')
  z.write(ROOT/'blog-import-manifest.json','blog-import-manifest.json')
  for source in list(ROOT.glob('*.py'))+list(ROOT.glob('*.json'))+list(ROOT.glob('*.md'))+list((ROOT/'.github').rglob('*'))+list((ROOT/'scripts').rglob('*.py'))+list((ROOT/'tests').glob('*')):
   if source.is_file() and '__pycache__' not in source.parts:z.write(source,'source/'+source.relative_to(ROOT).as_posix())
 print(output,output.stat().st_size,'bytes')
if __name__=='__main__':
 ap=argparse.ArgumentParser();ap.add_argument('output');ap.add_argument('--patch',action='store_true');a=ap.parse_args();package(a.output,a.patch)
