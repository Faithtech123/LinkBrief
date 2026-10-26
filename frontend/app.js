const API = new URL(window.LINKBRIEF_API_URL || '../backend', window.location.href).href.replace(/\/$/, '');
async function api(action, data=null){
  const res = await fetch(`${API}/?action=${encodeURIComponent(action)}`, {method:'POST', credentials:'include', headers:{'Content-Type':'application/json'}, body: data?JSON.stringify(data):null});
  const response = await res.json().catch(() => { throw new Error('The server returned an invalid response'); });
  if (!res.ok && !response.error) throw new Error('The request could not be completed');
  return response;
}
async function apiGet(action){
  const res = await fetch(`${API}/?action=${encodeURIComponent(action)}`, {credentials:'include'});
  const response = await res.json().catch(() => { throw new Error('The server returned an invalid response'); });
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
async function copyText(text){
  try{
    if(navigator.clipboard && window.isSecureContext){
      await navigator.clipboard.writeText(text);
    }else{
      const input=document.createElement('textarea');
      input.value=text;
      input.setAttribute('readonly','');
      input.style.position='fixed';
      input.style.opacity='0';
      document.body.appendChild(input);
      input.select();
      const copied=document.execCommand('copy');
      input.remove();
      if(!copied) throw new Error('Clipboard access is not available in this browser');
    }
    toast('Link copied to clipboard');
  }catch(error){
    toast(error.message || 'Could not copy the link','error');
  }
}
