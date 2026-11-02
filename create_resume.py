from pathlib import Path
import json
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, KeepTogether, Table, TableStyle
from reportlab.lib.styles import ParagraphStyle
from reportlab.lib import colors
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from reportlab.lib.enums import TA_LEFT
from xml.sax.saxutils import escape

ROOT=Path(__file__).parent
DEST=ROOT/'dist/assets/documents/Brajkishor_Pandey_Resume.pdf'
DATA=json.loads((ROOT/'content.json').read_text())['projects']
pdfmetrics.registerFont(TTFont('DejaVu','/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'))
pdfmetrics.registerFont(TTFont('DejaVuBold','/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf'))
pdfmetrics.registerFontFamily('DejaVu',normal='DejaVu',bold='DejaVuBold',italic='DejaVu',boldItalic='DejaVuBold')
ink=colors.HexColor('#182820');muted=colors.HexColor('#506257');green=colors.HexColor('#426c35');line=colors.HexColor('#d6dfd1')
styles={
 'name':ParagraphStyle('name',fontName='DejaVuBold',fontSize=25,leading=30,textColor=ink,spaceAfter=8),
 'title':ParagraphStyle('title',fontName='DejaVu',fontSize=10.5,leading=16,textColor=green,spaceAfter=9),
 'contact':ParagraphStyle('contact',fontName='DejaVu',fontSize=8,leading=13,textColor=muted,spaceAfter=17),
 'section':ParagraphStyle('section',fontName='DejaVuBold',fontSize=10,leading=15,textColor=green,spaceBefore=17,spaceAfter=9),
 'role':ParagraphStyle('role',fontName='DejaVuBold',fontSize=10,leading=15,textColor=ink,spaceBefore=8,spaceAfter=3),
 'meta':ParagraphStyle('meta',fontName='DejaVu',fontSize=8.2,leading=13,textColor=muted,spaceAfter=6),
 'body':ParagraphStyle('body',fontName='DejaVu',fontSize=8.6,leading=14,textColor=ink,spaceAfter=7),
 'bullet':ParagraphStyle('bullet',fontName='DejaVu',fontSize=8.4,leading=13.5,textColor=ink,leftIndent=10,firstLineIndent=-10,spaceAfter=5),
 'small':ParagraphStyle('small',fontName='DejaVu',fontSize=7.6,leading=12,textColor=muted,spaceAfter=5),
}
story=[]
def p(text,style='body'):return Paragraph(text,styles[style])
def add(text,style='body'):story.append(p(text,style))
def bullet(text):add('- '+escape(text),'bullet')
def sec(text):add(text.upper(),'section')
def role(name,meta,bullets):
 story.append(KeepTogether([p(name,'role'),p(meta,'meta'),p('- '+escape(bullets[0]),'bullet')]))
 for b in bullets[1:]:bullet(b)
def top(label):
 add('BRAJKISHOR PANDEY','name');add(label,'title')
def foot(canvas,doc):
 canvas.saveState();w,h=doc.pagesize;canvas.setStrokeColor(line);canvas.line(43,43,w-43,43)
 canvas.setFont('DejaVu',7);canvas.setFillColor(muted);canvas.drawString(43,29,'Brajkishor Pandey | bkpandey.com | Updated November 2026')
 canvas.drawRightString(w-43,29,f'{doc.page}');canvas.restoreState()

top('Head of Engineering | Fintech & Payment Systems Architect')
add('Chandigarh, India | <link href="mailto:brajkishorpandey@gmail.com">brajkishorpandey@gmail.com</link> | +91 95698 43767<br/><link href="https://bkpandey.com">bkpandey.com</link> | <link href="https://www.linkedin.com/in/brajkishorpandey/">linkedin.com/in/brajkishorpandey</link>','contact')
sec('Professional summary')
add('Engineering leader and hands-on software architect with <b>18+ years of experience</b> across fintech, cross-border payments, mobility, commerce, enterprise platforms, and data systems. Lead architecture, delivery, production reliability, security, payment integrations, and engineering teams. Combine PHP/Laravel depth with Python, Node.js, cloud operations, databases, and practical AI/ML.')
sec('Leadership & platform highlights')
for x in ['Supported $1B+ in cross-border payment volume at Nairagram.','Scaled the engineering function from 5 to 20 members.','Worked across 10+ pay-in gateway integrations and 50+ payout partner APIs.','Own delivery across customer journeys, partner integrations, payout operations, reconciliation, and production.']:bullet(x)
sec('Professional experience')
role('Head of Engineering - Nairagram','January 2021 - Present | Lagos, Nigeria / Remote',[
'Own engineering strategy, platform architecture, technical roadmap, delivery governance, infrastructure, security, DevOps, and production reliability for a cross-border payment ecosystem.',
'Lead engineering across C2C, B2B/B2C, partner APIs, pay-in, payout, administrative operations, compliance-support workflows, settlement, reconciliation, reporting, and transaction monitoring.',
'Strengthen partner onboarding, authentication, routing, callbacks, retries, exception management, audit trails, payout recovery, and operational issue resolution.',
'Build and improve ID verification, fraud-detection support, analytics, BigQuery reporting, and Python forecasting using Prophet, XGBoost, and LightGBM.',
'Work with founders, product, finance, compliance, operations, and external providers; guide team growth, mentoring, release planning, and production response.'
])
role('Lead Developer / Team Lead - Ping Express','July 2017 - December 2020 | United States / Remote',[
'Led cross-functional development planning, technical delivery, architecture, code review, and production stabilization for a cross-border remittance platform.',
'Built sender/receiver, FX, payout, compliance, transaction-status, mobile/web API, partner-integration, and operational workflows.',
'Improved review, testing, mentoring, and release discipline; professional records report a 90% reduction in post-production defects.'
])

story.append(PageBreak());top('Professional experience & technical capabilities')
sec('Earlier experience')
role('Senior PHP Developer / Team Leader - Dot Technologies','August 2014 - June 2017 | Panchkula, India',[
'Led PHP teams across custom web applications, CMS, commerce, API integrations, and database-driven platforms.',
'Translated specifications into tasks, estimates, schemas, and delivery plans; managed technical guidance, scope changes, escalations, and mentoring.'
])
role('Senior PHP Developer - Tech Prastish Software Solutions','May 2013 - August 2014 | Mohali, India',[
'Delivered Magento, BigCommerce, WordPress, Joomla, multilingual commerce, directory, and third-party API projects.',
'Built custom payment plugins and business-specific retail, wholesale, tailoring, and multi-store capabilities.'
])
role('PHP Programmer / Software Developer / Trainer','August 2007 - May 2013 | STN Technologies, Aditya Telecom, Spider Webbie, SLR Infotech',[
'Built CMS, government, legal, video-conferencing, desktop, and web applications; supported Linux/Windows hosting, technical training, troubleshooting, QA, and project coordination.'
])
sec('Technical capabilities')
rows=[
('Backend & APIs','PHP, Laravel, CodeIgniter, YII, CakePHP, Python, Django, Flask, FastAPI, Node.js, Express.js, REST APIs'),
('Frontend & mobile','React, JavaScript, jQuery, Angular integrations, Flutter backend APIs, MQTT, Firebase FCM, Google Maps, Mapbox'),
('Data & analytics','MySQL, PostgreSQL, MSSQL, MongoDB, Redis, BigQuery, query optimization, reporting, data pipelines'),
('Cloud & operations','AWS, AWS Lambda, DigitalOcean, Linux, Apache, PHP-FPM, WHM/cPanel, Git, CI/CD, monitoring, incident response'),
('Fintech & controls','Payment gateways, payout APIs, wallets, FX, reconciliation, double-entry design, KYC/KYB workflows, ID verification, fraud signals, audit trails'),
('AI & forecasting','Prophet, XGBoost, LightGBM, Python ML pipelines, forecasting, operational analytics, AI-assisted automation'),
('Commerce & enterprise','WordPress, Joomla, Shopify, OpenCart, Magento, BigCommerce, VirtueMart, ERP, POS, LMS/IMS')]
table=Table([[p(a,'small'),p(b,'body')] for a,b in rows],colWidths=[106,403],hAlign='LEFT')
table.setStyle(TableStyle([('VALIGN',(0,0),(-1,-1),'TOP'),('LEFTPADDING',(0,0),(-1,-1),0),('RIGHTPADDING',(0,0),(-1,-1),10),('TOPPADDING',(0,0),(-1,-1),6),('BOTTOMPADDING',(0,0),(-1,-1),5),('LINEBELOW',(0,0),(-1,-1),.35,line)]));story.append(table)
sec('Education & training')
add('<b>Bachelor of Technology, Computer Science</b> - H.P. University / I.I.T.T College of Engineering, 2008<br/><b>DOEACC O Level</b> - 2003')


story.append(PageBreak());top('Selected project portfolio | Payments & real-time platforms')
for slug in ['nairagram','nairagram-basket','ping-express','masterremit','pingmoney','rooo']:
 pr=next(x for x in DATA if x['slug']==slug)
 story.append(KeepTogether([p(escape(pr['name']),'role'),p(escape(pr['role']),'meta'),p(escape(pr['summary']),'body')]))
 bullet(pr['contributions'][0])
 

story.append(PageBreak());top('Selected project portfolio | Enterprise, data & architecture')
for slug in ['amitbookdepot','marineims','real-estate-data','multicurrency-ledger','stellar-remittance','payment-control-layer']:
 pr=next(x for x in DATA if x['slug']==slug)
 story.append(KeepTogether([p(escape(pr['name']),'role'),p(escape(pr['role']+' | '+pr['period']),'meta'),p(escape(pr['summary']),'body')]))
 bullet(pr['contributions'][0])
 
sec('Earlier web & commerce work')
add('SingleTact, Exadime, Pop-in.ch, Homegrown Creativity, Feadz, Selected Access, Raise Reach, Aktiebladet, Kursner, GrinderScape, My Mini Cab London, and other CMS, commerce, booking, and custom applications. These are historical project contributions.')

doc=SimpleDocTemplate(str(DEST),pagesize=(595.28,841.89),rightMargin=43,leftMargin=43,topMargin=41,bottomMargin=60,title='Brajkishor Pandey - Engineering Leadership Resume',author='Brajkishor Pandey')
doc.build(story,onFirstPage=foot,onLaterPages=foot)
print(DEST)
