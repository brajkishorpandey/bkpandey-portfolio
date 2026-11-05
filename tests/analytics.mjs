import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';

const code = await readFile(new URL('../dist/assets/js/analytics.js', import.meta.url));
const {setupAnalytics} = await import('data:text/javascript;base64,' + code.toString('base64'));
class Element {
 children = [];
 handlers = {};
 append(...children) { this.children.push(...children); }
 addEventListener(name, handler) { this.handlers[name] = handler; }
 setAttribute() {}
 focus() {}
 querySelector() { return this.children.find(child => child.type === 'button'); }
}
const footer = new Element();
globalThis.document = {
 head: new Element(), body: new Element(), createElement: () => new Element(),
 querySelector: () => footer, referrer: 'https://www.linkedin.com/path?private=1',
};
globalThis.window = {};
globalThis.location = {pathname: '/admin', origin: 'https://bkpandey.com', search: ''};
const preferences = new Map();
globalThis.localStorage = {getItem: key => preferences.get(key), setItem: (key, value) => preferences.set(key, value)};

setupAnalytics('G-PORTFOLIO');
assert.equal(document.body.children.length, 0, 'Admin pages must never load tracking');
location.pathname = '/work/nairagram.html';
location.search = '?email=private%40example.org&utm_source=linkedin';
setupAnalytics('G-PORTFOLIO');
const [panel] = document.body.children;
assert.equal(document.head.children.length, 0, 'No tag before an opt-in');
panel.children[2].handlers.click();
assert.equal(document.head.children.length, 0, 'Declining keeps the tag blocked');
assert.equal(preferences.get('bp-analytics:G-PORTFOLIO'), 'deny');
footer.children[0].handlers.click();
panel.children[1].handlers.click();
assert.equal(document.head.children.length, 1, 'Allowing analytics loads one tag');
const config = window.dataLayer.find(command => command[0] === 'config')[2];
assert.equal(config.page_referrer, 'https://www.linkedin.com');
assert.equal(config.page_location, 'https://bkpandey.com/work/nairagram.html?utm_source=linkedin&utm_medium=referral&utm_campaign=portfolio_profile');
assert.equal(config.allow_google_signals, false);
console.log('Google Analytics consent and attribution checks passed.');
