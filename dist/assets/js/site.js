(()=>{'use strict';
const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches;
const toggle=document.querySelector('.menu-toggle'),nav=document.querySelector('.nav-links');
const closeMenu=()=>{nav?.classList.remove('open');toggle?.setAttribute('aria-expanded','false');toggle?.setAttribute('aria-label','Open navigation');};
toggle?.addEventListener('click',()=>{const open=nav.classList.toggle('open');toggle.setAttribute('aria-expanded',String(open));toggle.setAttribute('aria-label',open?'Close navigation':'Open navigation');});
nav?.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMenu));
document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeMenu();}});
document.addEventListener('click',e=>{if(!e.target.closest('.header'))closeMenu();});
const progress=document.querySelector('.progress');let ticking=false;
function scrollUpdate(){const height=document.documentElement.scrollHeight-innerHeight;if(progress)progress.style.width=(height>0?scrollY/height*100:0)+'%';ticking=false;}
addEventListener('scroll',()=>{if(!ticking){requestAnimationFrame(scrollUpdate);ticking=true;}},{passive:true});scrollUpdate();
if('IntersectionObserver' in window){
 if(!reduced)document.documentElement.classList.add('motion-ready');
 const observer=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');observer.unobserve(e.target);}}),{threshold:.06});document.querySelectorAll('.reveal').forEach(el=>observer.observe(el));
 const sections=[...document.querySelectorAll('main>section[id]')];const sectionObserver=new IntersectionObserver(entries=>{entries.forEach(e=>{if(e.isIntersecting){nav?.querySelectorAll('a').forEach(a=>a.classList.toggle('active',a.getAttribute('href')==='/#'+e.target.id));}});},{rootMargin:'-25% 0px -60% 0px'});sections.forEach(s=>sectionObserver.observe(s));
 const countObserver=new IntersectionObserver(entries=>entries.forEach(e=>{if(!e.isIntersecting)return;countObserver.unobserve(e.target);if(reduced)return;const target=Number(e.target.dataset.count),suffix=e.target.dataset.suffix||'',prefix=e.target.dataset.prefix||'',start=performance.now();function tick(now){let t=Math.min((now-start)/1200,1);e.target.textContent=prefix+Math.round(target*(1-Math.pow(1-t,3)))+suffix;if(t<1)requestAnimationFrame(tick);}requestAnimationFrame(tick);}),{threshold:.7});document.querySelectorAll('[data-count]').forEach(el=>countObserver.observe(el));
}
const filters=document.querySelectorAll('.filter');const cards=document.querySelectorAll('.project-card');const resultStatus=document.getElementById('filter-status');
filters.forEach(btn=>btn.addEventListener('click',()=>{filters.forEach(b=>{b.classList.remove('active');b.setAttribute('aria-pressed','false');});btn.classList.add('active');btn.setAttribute('aria-pressed','true');let n=0;cards.forEach(card=>{const show=btn.dataset.filter==='all'||card.dataset.category===btn.dataset.filter;card.hidden=!show;if(show){n++;card.classList.add('visible');}});if(resultStatus)resultStatus.textContent=`Showing ${n} ${btn.dataset.filter==='all'?'projects':btn.dataset.filter+' projects'}`;}));
let settings={contact_email:'brajkishorpandey@gmail.com',email_subject:'Portfolio enquiry: {service}',email_body:"Hi Braj,\n\nI’m {name}.\nEmail: {email}\nInterested in: {service}\n\n{message}\n\nBest,\n{name}"};
const applyLines=(el,text)=>{if(!el||!text)return;el.replaceChildren(...text.split('\n').flatMap((line,i)=>i?[document.createElement('br'),document.createTextNode(line)]:[document.createTextNode(line)]));};
const settingsReady=fetch('/api/site-settings',{credentials:'omit',cache:'no-cache'}).then(r=>r.ok?r.json():Promise.reject()).then(values=>{
 settings={...settings,...values};
 const email=settings.contact_email;
 document.querySelectorAll('a[href^="mailto:"]').forEach(a=>{a.href='mailto:'+encodeURIComponent(email);if(a.textContent.includes('brajkishorpandey@gmail.com'))a.textContent=email+(a.classList.contains('contact-email')?' ↗':'');});
 document.querySelectorAll('a[href^="tel:"]').forEach(a=>{a.href='tel:'+settings.contact_phone.replace(/[^+0-9]/g,'');a.textContent=settings.contact_phone;});
 document.querySelectorAll('a[href^="https://wa.me/"]').forEach(a=>a.href='https://wa.me/'+settings.contact_whatsapp);
 document.querySelectorAll('a[href^="https://www.linkedin.com/in/"]').forEach(a=>a.href=settings.contact_linkedin);
 if(settings.hero_title!=="I build systems\nthat move money.\nAnd businesses\nforward.")applyLines(document.querySelector('.hero-copy h1'),settings.hero_title);
 const hero=document.querySelector('.hero-description');if(hero)hero.textContent=settings.hero_summary;
 if(settings.contact_title!=="Have a complex\nchallenge?\nLet’s talk.")applyLines(document.querySelector('#contact h2'),settings.contact_title);
 const intro=document.querySelector('.contact-intro');if(intro)intro.textContent=settings.contact_intro;
 const place=document.querySelector('#contact .tiny-caption');if(place)place.textContent=settings.contact_location;
 const resume=document.querySelector('[data-resume-summary]');if(resume)resume.textContent=settings.resume_summary;
}).catch(()=>{});
const template=(text,data)=>text.replace(/\{(name|email|service|message)\}/g,(_,key)=>String(data.get(key)||''));
const brief=document.getElementById('brief-form');brief?.addEventListener('submit',async e=>{e.preventDefault();if(!brief.reportValidity())return;await settingsReady;const data=new FormData(brief);const subject=template(settings.email_subject,data).replace(/[\r\n]/g,' ');const body=template(settings.email_body,data);const email='mailto:'+encodeURIComponent(settings.contact_email)+'?subject='+encodeURIComponent(subject)+'&body='+encodeURIComponent(body);document.getElementById('brief-status').textContent='Your email app will open with this brief. Review it and press Send there.';location.href=email;});
document.querySelectorAll('.copy-email').forEach(button=>button.addEventListener('click',async()=>{await settingsReady;try{await navigator.clipboard.writeText(settings.contact_email);button.textContent='Email copied ✓';setTimeout(()=>button.textContent='Copy email',2500);}catch{button.textContent=settings.contact_email;}}));
})();
