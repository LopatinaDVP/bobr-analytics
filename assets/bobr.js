(function(w,d,n){
var c=w.bobr;if(!c||!c.u||!n.sendBeacon&&!w.fetch)return;
var id=Math.random().toString(36).slice(2,10),L=location,vis=0,t0=d.visibilityState=='visible'?Date.now():0,sc=0,m={},F={},sent={};
function send(o){o.id=id;o.p=o.p||L.pathname+L.search;var b=JSON.stringify(o);
try{if(n.sendBeacon&&n.sendBeacon(c.u,b))return}catch(e){}
try{fetch(c.u,{method:'POST',body:b,keepalive:true,credentials:'omit'})}catch(e){}}
var tz='';try{tz=Intl.DateTimeFormat().resolvedOptions().timeZone||''}catch(e){}
send({t:'pv',ti:d.title.slice(0,200),r:d.referrer,w:screen.width,tz:tz,l:n.language||'',nf:c.nf||0});
function seen(){if(t0){vis+=Date.now()-t0;t0=0}}
function scroll(){var h=d.documentElement,s=Math.round((w.scrollY+w.innerHeight)/Math.max(h.scrollHeight,1)*100);if(s>sc)sc=Math.min(s,100)}
w.addEventListener('scroll',scroll,{passive:true});scroll();
try{var nav=performance.getEntriesByType('navigation')[0];if(nav)m.ttfb=Math.round(nav.responseStart)}catch(e){}
function po(type,f){try{new PerformanceObserver(function(l){l.getEntries().forEach(f)}).observe({type:type,buffered:true})}catch(e){}}
po('paint',function(e){if(e.name=='first-contentful-paint')m.fcp=Math.round(e.startTime)});
po('largest-contentful-paint',function(e){m.lcp=Math.round(e.startTime)});
po('layout-shift',function(e){if(!e.hadRecentInput)m.cls=Math.round(((m.cls||0)+e.value)*1e4)/1e4});
try{new PerformanceObserver(function(l){l.getEntries().forEach(function(e){if(e.interactionId&&(!m.inp||e.duration>m.inp))m.inp=Math.round(e.duration)})}).observe({type:'event',buffered:true,durationThreshold:16})}catch(e){}
function leave(){seen();scroll();send({t:'lv',v:Math.round(vis/1000),s:sc,m:m});
for(var k in F)if(!sent[k]){sent[k]=1;send({t:'ev',k:'fd',n:k})}}
d.addEventListener('visibilitychange',function(){if(d.visibilityState=='hidden')leave();else t0=Date.now()});
w.addEventListener('pagehide',leave);
d.addEventListener('click',function(e){var a=e.target.closest&&e.target.closest('a[href]');if(!a)return;var h=a.getAttribute('href')||'';
if(/^tel:/i.test(h))send({t:'ev',k:'tel'});else if(/^mailto:/i.test(h))send({t:'ev',k:'mail'});
else if(/^https?:/i.test(a.href)&&a.host!=L.host)send({t:'ev',k:'out',n:a.protocol+'//'+a.host+a.pathname})},true);
function fname(f){return (f.id||f.getAttribute('name')||f.getAttribute('aria-label')||(f.className+'').split(' ')[0]||'form').slice(0,120)}
d.addEventListener('input',function(e){var f=e.target.form;if(f)F[fname(f)]=1},true);
d.addEventListener('submit',function(e){var k=fname(e.target);sent[k]=1;send({t:'ev',k:'fs',n:k})},true);
})(window,document,navigator);
