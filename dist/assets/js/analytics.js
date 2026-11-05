const sourceNames = new Set(['linkedin', 'contra', 'clutch', 'goodfirms', 'wellfound', 'github']);

export function setupAnalytics(id) {
 const path = location.pathname;
 if (!/^G-[A-Z0-9]{4,20}$/.test(id)
     || !/^\/(?:$|(?:index|resume|archive|privacy)\.html$|blog\/?$|(?:blog|work)\/[a-z0-9-]+\.html$)/.test(path)) return;
 const preferenceKey = 'bp-analytics:' + id;
 let preference = '';
 try { preference = localStorage.getItem(preferenceKey) || ''; } catch {}
 let active = false;
 const disableKey = 'ga-disable-' + id;

 function start() {
  if (active) return;
  active = true;
  window[disableKey] = false;
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  gtag('consent', 'default', {
   analytics_storage: 'denied', ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
  });
  gtag('consent', 'update', {analytics_storage: 'granted'});
  gtag('js', new Date());
  const page = new URL(path, location.origin);
  const source = new URLSearchParams(location.search).get('utm_source');
  if (sourceNames.has(source)) {
   page.searchParams.set('utm_source', source);
   page.searchParams.set('utm_medium', 'referral');
   page.searchParams.set('utm_campaign', 'portfolio_profile');
  }
  let referrer = '';
  try { referrer = new URL(document.referrer).origin; } catch {}
  gtag('config', id, {
   allow_google_signals: false,
   allow_ad_personalization_signals: false,
   cookie_domain: 'none',
   page_location: page.href,
   page_referrer: referrer,
  });
  const script = document.createElement('script');
  script.async = true;
  script.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
  document.head.append(script);
 }

 function choose(value) {
  preference = value;
  try { localStorage.setItem(preferenceKey, value); } catch {}
  panel.hidden = true;
  settings.focus();
  if (value === 'allow') {
   start();
  } else if (active) {
   window[disableKey] = true;
   for (const name of ['_ga', '_ga_' + id.slice(2)]) {
    document.cookie = name + '=; Max-Age=0; Path=/; Secure; SameSite=Lax';
   }
   location.reload();
  }
 }

 const settings = document.createElement('button');
 settings.type = 'button';
 settings.className = 'btn';
 settings.textContent = 'Analytics choices';

 const panel = document.createElement('section');
 panel.className = 'analytics-preferences';
 panel.setAttribute('aria-label', 'Analytics choices');
 panel.hidden = preference === 'allow' || preference === 'deny';
 const explanation = document.createElement('p');
 explanation.textContent = 'Allow Google Analytics to measure visits to this portfolio. You can change your choice in the footer.';
 panel.append(explanation);
 for (const [label, value] of [['Allow analytics', 'allow'], ['Decline', 'deny']]) {
  const button = document.createElement('button');
  button.type = 'button';
  button.className = value === 'allow' ? 'btn btn-primary' : 'btn';
  button.textContent = label;
  button.addEventListener('click', () => choose(value));
  panel.append(button);
 }
 settings.addEventListener('click', () => {
  panel.hidden = !panel.hidden;
  if (!panel.hidden) panel.querySelector('button').focus();
 });
 document.querySelector('.footer-links')?.append(settings);
 document.body.append(panel);
 if (preference === 'allow') start();
}
