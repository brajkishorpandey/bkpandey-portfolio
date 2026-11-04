import json
import re
import unittest
from html.parser import HTMLParser
from pathlib import Path
import sys
sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
import seo


class Metadata(unittest.TestCase):
    def graph(self, html):
        return json.loads(re.search(r'<script type="application/ld\+json">(.*?)</script>', html).group(1))['@graph']

    def test_profile_entity(self):
        graph = self.graph(seo.metadata('Professional profile', 'Visible biography', '/resume.html'))
        profile = next(x for x in graph if x['@type'] == 'ProfilePage')
        person = next(x for x in graph if x['@type'] == 'Person')
        self.assertEqual(profile['mainEntity']['@id'], person['@id'])
        self.assertIn('https://www.linkedin.com/in/brajkishorpandey/', person['sameAs'])

    def test_article_dates_and_script_safety(self):
        title = '</script><script>alert(1)</script>'
        markup = seo.metadata(title, 'A & B', '/blog/example.html', {'category':'Payments', 'published_at':None, 'source_url':'https://www.linkedin.com/example'})
        article = next(x for x in self.graph(markup) if x['@type'] == 'BlogPosting')
        self.assertEqual(article['headline'], title)
        self.assertNotIn('datePublished', article)
        self.assertNotIn('<script>alert', markup)
        self.assertIn('property="og:type" content="article"', markup)

    def test_article_cover_and_breadcrumbs(self):
        markup = seo.metadata('Title', 'Description', '/blog/example.html', {'category':'Payments', 'attachment':'guide.pdf', 'published_at':'2026-10-01'})
        graph = self.graph(markup)
        article = next(x for x in graph if x['@type'] == 'BlogPosting')
        self.assertEqual(article['image'], ['https://bkpandey.com/assets/images/blog/guide.webp'])
        crumbs = next(x for x in graph if x['@type'] == 'BreadcrumbList')['itemListElement']
        self.assertEqual([x['position'] for x in crumbs], [1, 2, 3])
        self.assertEqual(article['datePublished'], '2026-10-01')

if __name__ == '__main__':
    unittest.main()
