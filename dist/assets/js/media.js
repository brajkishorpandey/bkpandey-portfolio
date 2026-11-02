const reduced = matchMedia('(prefers-reduced-motion: reduce)');
for (const el of document.querySelectorAll('[data-image-carousel]')) {
  const slides=[...el.querySelectorAll('.image-slide')];
  if(slides.length<2)continue;
  let current=0,paused=reduced.matches,visible=false,timer;
  const pause=el.querySelector('[data-pause]');
  function show(n){current=(n+slides.length)%slides.length;slides.forEach((s,i)=>s.hidden=i!==current);el.querySelector('[data-counter]').textContent=`${current+1} / ${slides.length}`;}
  function schedule(){clearInterval(timer);if(!paused&&visible&&!document.hidden)timer=setInterval(()=>show(current+1),Number(el.dataset.interval)||6000);}
  function setPaused(v){paused=v;pause.textContent=paused?'Play slideshow':'Pause slideshow';pause.setAttribute('aria-pressed',String(paused));schedule();}
  el.querySelector('[data-prev]').onclick=()=>{show(current-1);setPaused(true);};
  el.querySelector('[data-next]').onclick=()=>{show(current+1);setPaused(true);};
  pause.onclick=()=>setPaused(!paused);
  el.onkeydown=e=>{if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();show(current+(e.key==='ArrowRight'?1:-1));setPaused(true);}};
  el.addEventListener('focusin',()=>setPaused(true));
  document.addEventListener('visibilitychange',schedule);
  reduced.addEventListener('change',e=>{if(e.matches)setPaused(true);});
  new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;schedule();}).observe(el);
  setPaused(paused);
}
let pdfLibrary;
function getPDF(){return pdfLibrary??=import('/assets/vendor/pdfjs/pdf.min.mjs').then(lib=>{lib.GlobalWorkerOptions.workerSrc='/assets/vendor/pdfjs/pdf.worker.min.mjs';return lib;});}
const observer=new IntersectionObserver(entries=>{for(const entry of entries)if(entry.isIntersecting){observer.unobserve(entry.target);startPDF(entry.target);}},{rootMargin:'150px'});
for(const el of document.querySelectorAll('[data-pdf-carousel]'))observer.observe(el);
async function startPDF(el){
  const status=el.querySelector('[data-pdf-status]'),canvas=el.querySelector('canvas'),prev=el.querySelector('[data-prev]'),next=el.querySelector('[data-next]'),counter=el.querySelector('[data-counter]');
  let pdf,page=1,busy=false;
  async function render(n){
    if(busy||!pdf)return;busy=true;prev.disabled=next.disabled=true;status.hidden=false;status.textContent='Loading page…';
    try{
      const p=await pdf.getPage(n),base=p.getViewport({scale:1});
      const width=Math.min(el.clientWidth-32,1000),scale=width/base.width,viewport=p.getViewport({scale});
      const ratio=Math.min(devicePixelRatio||1,2);canvas.width=Math.floor(viewport.width*ratio);canvas.height=Math.floor(viewport.height*ratio);canvas.style.width='100%';canvas.style.maxWidth=`${viewport.width}px`;
      await p.render({canvasContext:canvas.getContext('2d'),viewport,transform:ratio===1?null:[ratio,0,0,ratio,0,0]}).promise;
      page=n;canvas.hidden=false;status.hidden=true;counter.textContent=`Page ${page} / ${pdf.numPages}`;canvas.setAttribute('aria-label',`Page ${page} of ${pdf.numPages}. Download the PDF for its full accessible text.`);p.cleanup();
    }catch(e){status.textContent='Preview could not load. You can still download the PDF below.';}
    finally{busy=false;prev.disabled=page<=1;next.disabled=page>=pdf.numPages;}
  }
  prev.onclick=()=>render(Math.max(1,page-1));next.onclick=()=>render(Math.min(pdf.numPages,page+1));
  el.onkeydown=e=>{if(e.key==='ArrowLeft'||e.key==='ArrowRight'){e.preventDefault();render(Math.max(1,Math.min(pdf.numPages,page+(e.key==='ArrowRight'?1:-1))));}};
  status.textContent='Loading PDF preview…';
  try{const lib=await getPDF();pdf=await lib.getDocument({url:el.dataset.src,disableAutoFetch:true,disableStream:true,isEvalSupported:false,cMapUrl:'/assets/vendor/pdfjs/cmaps/',cMapPacked:true,standardFontDataUrl:'/assets/vendor/pdfjs/standard_fonts/',wasmUrl:'/assets/vendor/pdfjs/wasm/',iccUrl:'/assets/vendor/pdfjs/iccs/'}).promise;await render(1);}
  catch(e){status.textContent='Preview could not load. You can still download the PDF below.';}
}
