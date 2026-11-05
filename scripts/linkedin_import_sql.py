"""Build a reviewable, idempotent data import; never accepts executable uploads.

Run locally and import the resulting SQL through the owner's database tool.
Existing articles, owner edits and uploads are preserved. No credentials in output.
Refresh the application's public cache after importing through an external tool.
"""
import argparse
import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def literal(value):
    return 'NULL' if value is None else "CONVERT(0x" + str(value).encode().hex() + " USING utf8mb4) COLLATE utf8mb4_unicode_ci"


def generate(batch):
    sql = ['ROLLBACK;', 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;', 'START TRANSACTION;']
    # Insert oldest first so the public id tie-breaker follows source order.
    for post in reversed(batch['posts']):
        slug, source = literal(post['slug']), literal(post['source_url'])
        sql += [f"INSERT IGNORE INTO bp_blog_categories(name) VALUES ({literal(post['category'])});",
                f"SET @bp_new = NOT EXISTS(SELECT 1 FROM bp_blog_posts WHERE slug={slug} OR source_url={source});",
                'INSERT INTO bp_blog_posts(category_id,slug,title,excerpt,body,status,source_url,origin,published_at,created_at,updated_at,pdf_display) '
                + 'SELECT c.id,' + ','.join(literal(post[k]) for k in ['slug','title','excerpt','body'])
                + ",'published'," + source + ',' + literal(post['origin'])
                + ",UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP()," + literal('carousel' if post.get('attachment') else 'file')
                + f" FROM bp_blog_categories c WHERE c.name={literal(post['category'])} AND @bp_new=1;",
                f'SET @bp_post=(SELECT id FROM bp_blog_posts WHERE slug={slug});']
        assets = []
        if post.get('attachment'):
            name=post['attachment'];assets += [(name,'/assets/documents/blog/'+name,'application/pdf',0),
                                              (name[:-4]+'.webp','/assets/images/blog/'+name[:-4]+'.webp','image/webp',1)]
        elif post.get('cover_image'):
            name=post['cover_image'];assets += [(name,'/assets/images/blog/'+name,'image/webp',1)]
        for name, public_path, mime, cover in assets:
            sql += ['INSERT INTO bp_blog_assets(original_name,public_path,mime_type) SELECT '
                    + ','.join(literal(v) for v in [name,public_path,mime]) + ' WHERE @bp_new=1;',
                    'SET @bp_asset=LAST_INSERT_ID();',
                    f'INSERT INTO bp_blog_post_assets(post_id,asset_id,is_cover) SELECT @bp_post,@bp_asset,{cover} WHERE @bp_new=1;']
    for item in batch.get('backfill_sources',[]):
        sql.append('UPDATE bp_blog_posts SET source_url='+literal(item['source_url'])
                   +' WHERE slug='+literal(item['slug'])+' AND source_url IS NULL AND version=1;')
    sql += ['COMMIT;', "SELECT COUNT(*) AS published_articles FROM bp_blog_posts WHERE status='published';"]
    return '\n'.join(sql)+'\n'


if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--output',type=Path,required=True)
    args=parser.parse_args();batch=json.loads((ROOT/'content/linkedin-import-20261104.json').read_text())
    args.output.write_text(generate(batch));print(f"Prepared {len(batch['posts'])} new article candidates and {len(batch.get('backfill_sources',[]))} source backfills.")
