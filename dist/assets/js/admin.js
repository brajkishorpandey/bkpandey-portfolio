(() => {
 const toggle = document.querySelector('.admin-menu-toggle');
 if (!toggle) return;
 const nav = document.getElementById(toggle.getAttribute('aria-controls'));
 if (!nav) return;
 toggle.setAttribute('aria-expanded', 'false');
 toggle.hidden = false;
 toggle.addEventListener('click', () => {
  toggle.setAttribute('aria-expanded', String(toggle.getAttribute('aria-expanded') === 'false'));
 });
 nav.addEventListener('keydown', event => {
  if (event.key === 'Escape' && toggle.offsetWidth && toggle.getAttribute('aria-expanded') === 'true') {
   toggle.setAttribute('aria-expanded', 'false');
   toggle.focus();
  }
 });
})();
