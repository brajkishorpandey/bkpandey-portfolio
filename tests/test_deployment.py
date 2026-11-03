import importlib.util,tempfile,unittest
from pathlib import Path
spec=importlib.util.spec_from_file_location('deploy',Path(__file__).parents[1]/'scripts/deploy_ftps.py');deploy=importlib.util.module_from_spec(spec);spec.loader.exec_module(deploy)
class ReleaseSafety(unittest.TestCase):
 def test_runtime_files_never_deploy(self):
  with tempfile.TemporaryDirectory() as d:
   root=Path(d)
   for name in ['dist/index.html','hosting/public_html/index.php','hosting/bkpandey-private/core.php','hosting/bkpandey-private/database.php','hosting/bkpandey-private/uploads/a.pdf','hosting/bkpandey-private/cache/session.json','hosting/bkpandey-private/contact-config.json','hosting/bkpandey-private/contact-state/limits.json','hosting/bkpandey-private/contact-config-temporary']:
    path=root/name;path.parent.mkdir(parents=True,exist_ok=True);path.write_text('fixture')
   files=deploy.release_files(root)
   self.assertEqual(set(files),{'public_html/index.html','public_html/index.php','bkpandey-private/core.php'})
 def test_hosting_apache_overlay_wins(self):
  with tempfile.TemporaryDirectory() as d:
   root=Path(d)
   for name in ['dist/.htaccess','hosting/public_html/.htaccess']:
    path=root/name;path.parent.mkdir(parents=True,exist_ok=True);path.write_text(name)
   self.assertEqual(deploy.release_files(root)['public_html/.htaccess'].read_text(),'hosting/public_html/.htaccess')
 def test_split_scoped_destinations(self):
  public=object();private=object()
  self.assertEqual(deploy.remote_target('public_html/index.php',public,private),(public,'index.php'))
  self.assertEqual(deploy.remote_target('bkpandey-private/core.php',public,private),(private,'core.php'))
  with self.assertRaises(ValueError):deploy.remote_target('public_html/../database.php',public,private)
if __name__=='__main__':unittest.main()
