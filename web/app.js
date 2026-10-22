const API = new URL('../api', window.location.href).href.replace(/\/$/, '');
async function api(action, data=null){
  const res = await fetch(`${API}/?action=${encodeURIComponent(action)}`, {method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: data?JSON.stringify(data):null});
  const response = await res.json();
  if (!res.ok && !response.error) throw new Error('The request could not be completed');
  return response;
}
async function apiGet(action){
  const res = await fetch(`${API}/?action=${encodeURIComponent(action)}`, {credentials:'include'});
  const response = await res.json();
  if (!res.ok && !response.error) throw new Error('The request could not be completed');
  return response;
}
function toast(title, type='success'){
  const el=document.createElement('div');
  el.className=`toast${type==='error'?' error':''}`;
  el.textContent=title;
  document.getElementById('toasts').appendChild(el);
  setTimeout(()=>el.remove(),3500);
}