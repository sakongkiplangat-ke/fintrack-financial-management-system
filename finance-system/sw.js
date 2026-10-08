// FinTrack service worker: caches the app shell so pages you have opened still load offline (read-only).
const CACHE='fintrack-v2', SHELL=['assets/style.css','assets/app.js','assets/icon.svg','offline.html'];
self.addEventListener('install',e=>e.waitUntil(caches.open(CACHE).then(c=>c.addAll(SHELL)).then(()=>self.skipWaiting())));
self.addEventListener('activate',e=>e.waitUntil(caches.keys().then(k=>Promise.all(k.filter(x=>x!==CACHE).map(x=>caches.delete(x)))).then(()=>self.clients.claim())));
self.addEventListener('fetch',e=>{
  if(e.request.method!=='GET')return;
  const u=new URL(e.request.url); if(u.origin!==location.origin)return;
  if(u.pathname.includes('/assets/')){e.respondWith(caches.match(e.request).then(r=>r||fetch(e.request)));return;}
  e.respondWith(fetch(e.request).then(res=>{ if(res.ok&&!u.pathname.includes('receipt')){const c=res.clone();caches.open(CACHE).then(x=>x.put(e.request,c));} return res; })
    .catch(()=>caches.match(e.request).then(r=>r||caches.match('offline.html'))));
});
