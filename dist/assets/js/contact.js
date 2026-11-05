(()=>{'use strict';
const form=document.getElementById('brief-form');if(!form)return;
const send=form.querySelector('button[type="submit"]'),status=document.getElementById('brief-status'),slot=document.getElementById('contact-verification'),retry=document.getElementById('contact-retry');
let challenge=null,token='',widget=null,loading=false,submitted=false;
const message=text=>{status.textContent=text;};
function loadTurnstile(){
 if(window.turnstile)return Promise.resolve();
 return new Promise((resolve,reject)=>{const script=document.createElement('script');script.src='https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';script.async=true;script.onload=resolve;script.onerror=()=>{script.remove();reject(new Error('Verification could not load. Please refresh it or use the email link.'));};document.head.append(script);});
}
async function initialise(){
 if(loading||submitted)return;loading=true;send.disabled=true;retry.hidden=true;token='';
 message('Loading secure verification…');
 try{
  if(widget!==null&&window.turnstile){window.turnstile.remove(widget);widget=null;}slot.replaceChildren();
  const response=await fetch('/api/contact',{credentials:'same-origin',cache:'no-store',headers:{'X-BP-Contact':'1','Accept':'application/json'}});
  const result=await response.json().catch(()=>({}));if(!response.ok)throw new Error(result.message||'The contact form is temporarily unavailable. Please use the email link.');
  challenge=result;await loadTurnstile();
  widget=window.turnstile.render(slot,{sitekey:challenge.sitekey,action:'contact',cData:challenge.binding,theme:'dark',
   callback:value=>{token=value;send.disabled=false;message('Verification complete. Your message is ready to send.');},
   'expired-callback':()=>{token='';send.disabled=true;retry.hidden=false;message('Verification expired. Refresh it before sending.');},
   'error-callback':()=>{token='';send.disabled=true;retry.hidden=false;message('Verification could not complete. Refresh it or use the email link.');return true;}
  });
  message('Complete the verification before sending.');
 }catch(error){challenge=null;message(error.message||'The contact form is temporarily unavailable. Please use the email link.');retry.hidden=false;}
 finally{loading=false;}
}
retry?.addEventListener('click',initialise);
form.addEventListener('submit',async event=>{
 event.preventDefault();if(!form.reportValidity()||!challenge||!token||submitted)return;
 const values=new FormData(form);const body={csrf:challenge.csrf,turnstile:token};
 for(const key of ['name','email','service','message','website'])body[key]=String(values.get(key)||'');
 send.disabled=true;retry.hidden=true;message('Sending your message…');
 try{
  const response=await fetch('/api/contact',{method:'POST',credentials:'same-origin',cache:'no-store',headers:{'Content-Type':'application/json','X-BP-Contact':'1','Accept':'application/json'},body:JSON.stringify(body)});
  const result=await response.json().catch(()=>({}));if(!response.ok)throw new Error(result.message||'Your message could not be sent. Please use the email link.');
  submitted=true;form.reset();if(widget!==null&&window.turnstile)window.turnstile.remove(widget);slot.replaceChildren();send.textContent='Message submitted ✓';message(result.message);
 }catch(error){message(error.message||'Your message could not be sent. Please use the email link.');retry.hidden=false;}
 finally{token='';challenge=null;send.disabled=true;}
});
if (location.hash === '#contact' || !('IntersectionObserver' in window)) {
 initialise();
} else {
 const observer = new IntersectionObserver(entries => {
  if (entries.some(entry => entry.isIntersecting)) {
   observer.disconnect();
   initialise();
  }
 }, {rootMargin: '300px'});
 observer.observe(form);
}
})();
