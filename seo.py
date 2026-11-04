"""Public page metadata. Structured data describes the visible owner and content."""
import html
import json

DOMAIN = 'https://bkpandey.com'
PERSON = DOMAIN + '/resume.html#person'


def metadata(title, description, path='/', article=None):
    url = DOMAIN + path
    full_title = title if 'Brajkishor Pandey' in title else title + ' | Brajkishor Pandey'
    person = {'@type': 'Person', '@id': PERSON, 'name': 'Brajkishor Pandey',
              'alternateName': 'Braj', 'url': DOMAIN + '/resume.html',
              'image': DOMAIN + '/media/profile',
              'jobTitle': 'Head of Engineering & Fintech Systems Architect',
              'sameAs': ['https://www.linkedin.com/in/brajkishorpandey/'],
              'knowsAbout': ['Cross-border payments', 'Fintech architecture', 'Payment APIs',
                             'Double-entry ledgers', 'Software engineering', 'Engineering leadership']}
    page = {'@type': 'ProfilePage' if path == '/resume.html' else 'WebPage',
            '@id': url + '#page', 'url': url, 'name': full_title, 'description': description,
            'inLanguage': 'en', 'isPartOf': {'@id': DOMAIN + '/#website'}}
    if path in ('/', '/resume.html'):
        page['mainEntity'] = {'@id': PERSON}
    graph = [person, {'@type': 'WebSite', '@id': DOMAIN + '/#website',
                      'url': DOMAIN + '/', 'name': 'Brajkishor Pandey',
                      'publisher': {'@id': PERSON}, 'inLanguage': 'en'}, page]
    image = DOMAIN + '/assets/images/social-card.png'
    if article:
        if article.get('attachment'):
            image = DOMAIN + '/assets/images/blog/' + article['attachment'][:-4] + '.webp'
        post = {'@type': 'BlogPosting', '@id': url + '#article', 'headline': title,
                'description': description, 'url': url, 'mainEntityOfPage': {'@id': url + '#page'},
                'author': {'@id': PERSON}, 'publisher': {'@id': PERSON}, 'inLanguage': 'en',
                'articleSection': article['category'], 'image': [image]}
        if article.get('published_at'):
            post['datePublished'] = article['published_at']
        if article.get('source_url'):
            post['isBasedOn'] = article['source_url']
        page['mainEntity'] = {'@id': url + '#article'}
        graph.append(post)
    if path != '/':
        crumbs = [('Home', DOMAIN + '/')]
        if path.startswith('/blog/') and path != '/blog/':
            crumbs.append(('Blog', DOMAIN + '/blog/'))
        crumbs.append((title, url))
        graph.append({'@type': 'BreadcrumbList', 'itemListElement': [
            {'@type': 'ListItem', 'position': i + 1, 'name': name, 'item': link}
            for i, (name, link) in enumerate(crumbs)]})
    def meta(attr, key, value):
        return f'<meta {attr}="{key}" content="{html.escape(value, quote=True)}">'
    out = '<!-- SEO:start --><title>' + html.escape(full_title) + '</title>'
    out += meta('name', 'description', description) + f'<link rel="canonical" href="{url}">'
    for key, value in {'og:type': 'article' if article else 'website', 'og:site_name': 'Brajkishor Pandey',
                       'og:title': full_title, 'og:description': description, 'og:url': url,
                       'og:image': image, 'og:image:alt': title,
                       'og:locale': 'en_IN'}.items():
        out += meta('property', key, value)
    for key, value in {'twitter:card': 'summary_large_image', 'twitter:title': full_title,
                       'twitter:description': description, 'twitter:image': image,
                       'twitter:image:alt': title, 'author': 'Brajkishor Pandey'}.items():
        out += meta('name', key, value)
    # JSON must not be able to close its script element.
    data = json.dumps({'@context': 'https://schema.org', '@graph': graph}, ensure_ascii=False).replace('<', '\\u003c').replace('>', '\\u003e').replace('&', '\\u0026')
    return out + '<script type="application/ld+json">' + data + '</script><link rel="alternate" type="application/rss+xml" title="Brajkishor Pandey — Blog" href="/blog/feed.xml"><!-- SEO:end -->'
