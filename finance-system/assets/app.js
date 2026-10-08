// Client-side validation and small interactions
document.querySelectorAll('form[data-confirm]').forEach(f =>
  f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

document.querySelectorAll('form[data-validate]').forEach(f =>
  f.addEventListener('submit', e => {
    const pw = f.querySelector('[name=password]'), cf = f.querySelector('[name=confirm]');
    if (pw && cf && pw.value !== cf.value) { e.preventDefault(); alert('Passwords do not match.'); }
    const amt = f.querySelector('[name=amount]');
    if (amt && !(parseFloat(amt.value) > 0)) { e.preventDefault(); alert('Amount must be greater than zero.'); }
  }));

document.querySelectorAll('[data-print]').forEach(b => b.addEventListener('click', () => window.print()));
document.querySelectorAll('.alert.ok').forEach(a => setTimeout(() => a.remove(), 4000));

// Receipt scan (fills the form from a photo)
const scanBtn = document.getElementById('scan');
if (scanBtn) scanBtn.addEventListener('click', async () => {
  const f = document.getElementById('receipt').files[0], msg = document.getElementById('scanmsg');
  if (!f) { msg.textContent = 'Choose a receipt photo first.'; return; }
  msg.textContent = 'Reading receipt...';
  const fd = new FormData(); fd.append('receipt', f); fd.append('csrf', document.querySelector('[name=csrf]').value);
  try {
    const r = await (await fetch('scan.php', { method: 'POST', body: fd })).json();
    if (r.error) { msg.textContent = r.error; return; }
    const form = scanBtn.closest('form');
    if (r.total) form.amount.value = r.total; if (r.date) form.tx_date.value = r.date;
    if (r.merchant) form.description.value = r.merchant; if (r.category) form.category.value = r.category;
    form.type.value = 'expense'; msg.textContent = 'Filled in. Please check the details before saving.';
  } catch (e) { msg.textContent = 'Could not scan right now.'; }
});

// Browser notifications for FinTrack alerts (works while the app is open)
if ('Notification' in window && document.querySelector('.nav')) {
  const ask = document.getElementById('enable-notify');
  const poll = async () => {
    if (Notification.permission !== 'granted') return;
    try {
      const a = await (await fetch('alerts.php?json=1')).json(), seen = JSON.parse(localStorage.getItem('ft_seen') || '[]');
      a.filter(x => x[0] !== 'info' && !seen.includes(x[1])).slice(0, 3).forEach(x => new Notification('FinTrack', { body: x[1] }));
      localStorage.setItem('ft_seen', JSON.stringify(a.map(x => x[1])));
    } catch (e) {}
  };
  if (ask) ask.addEventListener('click', () => Notification.requestPermission().then(poll));
  poll(); setInterval(poll, 300000);
}

// Offline support: register the service worker
if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js').catch(() => {});
