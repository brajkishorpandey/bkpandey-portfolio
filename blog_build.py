import json,pathlib,re,html,xml.sax.saxutils
import build
ROOT=pathlib.Path(__file__).parent;OUT=ROOT/'dist';BLOG=OUT/'blog';BLOG.mkdir(exist_ok=True)
posts=json.loads((ROOT/'blog-content.json').read_text());H=html.escape
def markdown(text):
 result=[];inside=False
 for line in text.splitlines():
  if line.startswith('- '):
   if not inside:result.append('<ul>');inside=True
   result.append('<li>'+H(line[2:])+'</li>');continue
  if inside:result.append('</ul>');inside=False
  if line.startswith('## '):result.append('<h2>'+H(line[3:])+'</h2>')
  elif line.startswith('### '):result.append('<h3>'+H(line[4:])+'</h3>')
  elif line.strip():result.append('<p>'+H(line)+'</p>')
 if inside:result.append('</ul>')
 return ''.join(result)
def image(p):
 return '/assets/images/blog/'+p['attachment'][:-4]+'.webp' if p['attachment'] else None
def tile(p,i):
 cover=f'<img src="{image(p)}" width="800" height="800" loading="lazy" alt="Cover of {H(p["title"],quote=True)}">' if image(p) else f'<div class="blog-type-art tone-{i%4}"><span>BP / FIELD NOTES</span><strong>{H(p["category"])}</strong><b aria-hidden="true">✳</b></div>'
 return f'<article class="blog-card" data-category="{H(p["category"])}" data-search="{H((p["title"]+" "+p["excerpt"]+" "+p["category"]+" "+p["slug"]+" "+p["body"]).lower(),quote=True)}"><a class="blog-cover" href="/blog/{p["slug"]}.html">{cover}</a><div class="blog-card-meta"><span>{H(p["category"])}</span><span>{"PDF included" if p["attachment"] else "Article"}</span></div><h2><a href="/blog/{p["slug"]}.html">{H(p["title"])}</a></h2><p>{H(p["excerpt"])}</p><a class="blog-read" href="/blog/{p["slug"]}.html">Read article <span aria-hidden="true">↗</span></a></article>'

body='''<main id="main" data-blog-static><section class="page-hero" id="top"><div class="container"><div class="breadcrumb"><a href="/">Home</a><span>/</span><span>Blog</span></div><p class="eyebrow">The blog / Fintech & technology</p><h1>Notes from the systems<br>that <span class="green">move money.</span></h1><p class="lead">Practical perspectives on payments, architecture, security, AI, and engineering leadership. Collected from my LinkedIn writing and technical carousels, with original PDFs to explore.</p><div class="blog-summary"><span><strong>28</strong> Articles & perspectives</span><span><strong>18</strong> Downloadable PDFs</span><a href="https://www.linkedin.com/in/brajkishorpandey/" target="_blank" rel="noopener noreferrer">Follow on LinkedIn ↗</a></div></div></section><section class="container blog-list-section"><div class="blog-toolbar"><div class="field"><label for="blog-search">Find an article</label><input id="blog-search" type="search" placeholder="Search payments, ledgers, AI…" autocomplete="off"></div><div class="field"><label for="blog-category">Explore a topic</label><select id="blog-category"><option value="all">All topics</option>Payments</option>Architecture</option>Security</option>Ledgers</option>Compliance</option>AI &amp; Data</option>Blockchain</option>Leadership</option><option>Engineering</option></select></div></div><p class="blog-count" id="blog-count" role="status">Showing 28 articles</p><div class="blog-grid">'''+''.join(tile(p,i) for i,p in enumerate(posts))+'''</div><p id="blog-empty" class="blog-empty" hidden>No articles match this search. Try another keyword or topic.</p><div class="blog-connect"><div><p class="eyebrow">From an idea to an implementation</p><h2>Have a similar challenge?</h2></div>'''+build.btn('Let’s talk','/#contact',True)+'''</div></section></main><script defer src="/assets/js/blog.js"></script>'''
body=body.replace('<option value="all">All topics</option>Payments</option>Architecture</option>Security</option>Ledgers</option>Compliance</option>AI &amp; Data</option>Blockchain</option>Leadership</option><option>Engineering</option>','<option value="all">All topics</option>'+''.join('<option>'+H(c)+'</option>' for c in sorted(set(p['category'] for p in posts))))
body=body.replace('>28<','>'+str(len(posts))+'<').replace('Showing 28 articles','Showing '+str(len(posts))+' articles')
(BLOG/'index.html').write_text(build.head('Fintech & technology blog','Payment architecture, payout routing, ledgers, compliance, AI, and engineering perspectives by Brajkishor Pandey, with downloadable carousels.','/blog/')+build.nav()+body+build.footer())
for i,p in enumerate(posts):
 asset=''
 if p['attachment']:
  a='/assets/documents/blog/'+p['attachment'];thumb=image(p)
  asset=f'<section class="media-carousel pdf-carousel container" data-pdf-carousel data-src="{a}" role="region" aria-roledescription="carousel" aria-label="{H(p["title"])}" tabindex="0"><div class="media-stage pdf-stage"><canvas aria-label="PDF page preview" hidden></canvas><p data-pdf-status role="status">PDF preview loads when you reach this guide.</p></div><div class="media-controls"><button type="button" data-prev disabled aria-label="Previous PDF page">←</button><span data-counter aria-live="polite">PDF guide</span><button type="button" data-next disabled aria-label="Next PDF page">→</button><a class="btn btn-small" href="{a}" download>Download PDF ↓</a></div><p class="media-caption">Use the arrows or your keyboard to browse. Download the PDF for its full accessible text.</p></section>' 
 source=f'<a class="btn btn-small" href="{p["source_url"]}" target="_blank" rel="noopener noreferrer">Read original on LinkedIn ↗</a>' if p['source_url'] else ''
 date=' · Originally published '+p['published_at'] if p['published_at'] else ''
 body=f'<main id="main"><section class="page-hero" id="top"><div class="container"><div class="breadcrumb"><a href="/">Home</a><span>/</span><a href="/blog/">Blog</a><span>/</span><span>{H(p["category"])}</span></div><p class="eyebrow">{H(p["category"])} / {H(p["origin"])}</p><h1>{H(p["title"])}</h1><p class="lead">{H(p["excerpt"])}</p><div class="article-byline"><img src="/assets/images/braj-portrait.webp" width="40" height="40" alt=""><div><strong><a href="/resume.html" rel="author">Brajkishor Pandey</a></strong><p>Engineering leader · Fintech architect{date}</p></div></div></div></section><div class="container">{asset}</div><article class="article-body container"><div class="article-source-note">{("Adapted from my LinkedIn writing and related technical material." if p["source_url"] else "Adapted from my technical carousel. The original PDF is included above.")} {source}</div>{markdown(p["body"])}<div class="next-work"><div><p class="eyebrow">Continue exploring</p><h3>More from the blog.</h3></div>{build.btn("All articles","/blog/",True)}</div></article></main>'
 (BLOG/(p['slug']+'.html')).write_text(build.head(p['title'],p['excerpt'],'/blog/'+p['slug']+'.html',article=p)+build.nav()+body+build.footer())

# Reusable chrome for the PHP hosting package. PHP controllers stay outside dist.
templates=ROOT/'hosting/bkpandey-private/templates';templates.mkdir(parents=True,exist_ok=True)
(templates/'header.html').write_text(build.nav());(templates/'footer.html').write_text(build.footer())
private=templates.parent
(private/'seed.json').write_text(json.dumps(posts,ensure_ascii=False,indent=2))
manifest=[dict(slug=p['slug'],title=p['title'],source_url=p['source_url'],attachment=p['attachment'],origin=p['origin']) for p in posts]
(ROOT/'blog-import-manifest.json').write_text(json.dumps({'imported_at':'2026-11-02','coverage':'Publicly retrievable posts and owner-provided carousel files; not a verified complete LinkedIn history.','posts':manifest},ensure_ascii=False,indent=2))

site=OUT/'sitemap.xml';s=site.read_text();s=re.sub(r'<url><loc>https://bkpandey.com/blog/.*?</url>','',s);s=s.replace('</urlset>','<url><loc>https://bkpandey.com/blog/</loc></url>'+''.join('<url><loc>https://bkpandey.com/blog/'+p['slug']+'.html</loc></url>' for p in posts)+'</urlset>');site.write_text(s)
items=[]
for p in posts:
 items.append('<item><title>'+xml.sax.saxutils.escape(p['title'])+'</title><link>https://bkpandey.com/blog/'+p['slug']+'.html</link><guid>https://bkpandey.com/blog/'+p['slug']+'.html</guid><description>'+xml.sax.saxutils.escape(p['excerpt'])+'</description></item>')
(BLOG/'feed.xml').write_text('<?xml version="1.0" encoding="utf-8"?><rss version="2.0"><channel><title>Brajkishor Pandey - Fintech & Technology</title><link>https://bkpandey.com/blog/</link><description>Engineering perspectives and practical payment architecture notes.</description>'+''.join(items)+'</channel></rss>')
print('Built blog:',len(posts),'articles and one searchable index.')
