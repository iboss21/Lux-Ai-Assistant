(function(){
  const mount = document.getElementById('luxai-admin-chat');
  if(!mount) return;
  mount.innerHTML = '<div class="log"></div><div class="controls"><input type="text" style="flex:1" placeholder="Ask the assistant..."/><button class="button">Send</button></div>';
  const log = mount.querySelector('.log');
  const input = mount.querySelector('input');
  const btn = mount.querySelector('button');
  btn.addEventListener('click', async ()=>{
    const msg = input.value.trim(); if(!msg) return;
    log.insertAdjacentHTML('beforeend','<div><strong>You:</strong> '+msg.replace(/</g,'&lt;')+'</div>');
    input.value='';
    const res = await fetch(mount.dataset.endpoint+'/chat',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':mount.dataset.nonce},body:JSON.stringify({message:msg,provider:'auto'})});
    const j = await res.json();
    log.insertAdjacentHTML('beforeend','<div><strong>AI:</strong> '+(j.reply||'').replace(/</g,'&lt;')+'</div>');
    log.scrollTop = log.scrollHeight;
  });
})();