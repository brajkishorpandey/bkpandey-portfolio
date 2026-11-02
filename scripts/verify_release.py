from pathlib import Path
from html.parser import HTMLParser
from urllib.parse import urlsplit,unquote
import sys
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from scripts.deploy_ftps import release_files
root=Path(__file__).resolve().parents[1];files=release_files(root);errors=[]
class Links(HTMLParser):
 def handle_starttag(self,tag,attrs):
  for key,value in attrs:
   if key not in {'href','src','data-src'} or not value or not value.startswith('/') or value.startswith('//'):continue
   path=unquote(urlsplit(value).path)
   if path.endswith('.php') or path in {'/','/blog/'}:continue
   if not (root/'dist'/path.lstrip('/')).is_file():errors.append(path)
for html in (root/'dist').rglob('*.html'):Links().feed(html.read_text())
if errors:raise SystemExit('Missing local assets: '+', '.join(sorted(set(errors))))
for path in files:
 if any(part in {'cache','uploads','database.php','setup-attempts.json'} for part in Path(path).parts):raise SystemExit('Private runtime file in release.')
print(f'Validated local links and {len(files)} deployable files; runtime secrets excluded.')
