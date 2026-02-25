if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js');

const $ = (s,p=document)=>p.querySelector(s);
const $$ = (s,p=document)=>Array.from(p.querySelectorAll(s));
const toast = (t)=>{ const el=$('#toast'); el.textContent=t; el.classList.add('show'); setTimeout(()=>el.classList.remove('show'),2200); };

$$('nav button[data-tab]').forEach(btn=>btn.onclick=()=>{
  $$('.tab').forEach(t=>t.classList.remove('active'));
  $('#tab-'+btn.dataset.tab).classList.add('active');
});

let currentLobbyId = null;
let localStream = null;
const peers = new Map();

async function api(action, data={}, method='POST') {
  const opts = {method};
  if (method === 'POST') {
    const fd = new FormData(); fd.append('action', action);
    Object.entries(data).forEach(([k,v])=>fd.append(k,v)); opts.body = fd;
  }
  const url = method==='GET' ? `/api.php?action=${encodeURIComponent(action)}&`+new URLSearchParams(data) : '/api.php';
  const r = await fetch(url, opts); const j = await r.json(); if (!j.ok) throw new Error(j.error); return j.data;
}

async function refreshLobbies() {
  const list = await api('list_lobbies',{},'GET');
  $('#lobbyList').innerHTML = list.map(l=>`<div class="lobby-item"><b>${l.name}</b> | ${l.scenario_name} | ${l.member_count}/30 <button onclick="joinLobby(${l.id})">ورود</button></div>`).join('') || 'لابی موجود نیست';
}
window.joinLobby = async (id) => {
  const pass = prompt('رمز (در صورت نیاز)');
  await api('join_lobby',{lobby_id:id,password:pass||''});
  currentLobbyId=id; $('#roomPanel').classList.remove('hidden'); $('#roomName').textContent='#'+id; toast('وارد لابی شدید');
  pollChat(); pollSignals();
};

$('#createLobbyForm')?.addEventListener('submit', async e=>{
  e.preventDefault(); const fd=new FormData(e.target); const data=Object.fromEntries(fd.entries());
  await api('create_lobby',data); toast('لابی ساخته شد'); e.target.reset(); refreshLobbies();
});

$('#chatForm')?.addEventListener('submit', async e=>{
  e.preventDefault(); if(!currentLobbyId) return;
  const msg=e.target.message.value.trim(); if(!msg) return;
  await api('chat_send',{lobby_id:currentLobbyId,message:msg}); e.target.reset(); pollChat();
});

async function pollChat(){
  if(!currentLobbyId) return;
  const items = await api('chat_fetch',{lobby_id:currentLobbyId},'GET');
  $('#chatBox').innerHTML = items.map(i=>`<div><b>${i.display_name}:</b> ${i.message}</div>`).join('');
}
setInterval(()=>{ if(currentLobbyId) pollChat(); },3000);

$$('[data-buy-avatar]').forEach(btn=>btn.onclick=async()=>{ await api('buy_avatar',{avatar_id:btn.dataset.buyAvatar}); toast('آواتار خریداری شد'); location.reload(); });
$$('[data-buy-item]').forEach(btn=>btn.onclick=async()=>{ await api('buy_item',{item_id:btn.dataset.buyItem}); toast('خرید موفق'); location.reload(); });

$('#receiptForm')?.addEventListener('submit', async e=>{e.preventDefault(); await api('submit_receipt',Object.fromEntries(new FormData(e.target).entries())); toast('رسید ثبت شد'); e.target.reset();});
$('#profileForm')?.addEventListener('submit', async e=>{e.preventDefault(); await api('profile_update',Object.fromEntries(new FormData(e.target).entries())); toast('ذخیره شد');});

$('#coinsForm')?.addEventListener('submit', async e=>{e.preventDefault(); await api('admin_set_balance',Object.fromEntries(new FormData(e.target).entries())); toast('موجودی آپدیت شد');});
$('#roleForm')?.addEventListener('submit', async e=>{e.preventDefault(); await api('admin_set_role',Object.fromEntries(new FormData(e.target).entries())); toast('نقش آپدیت شد');});
$('#avatarAdminForm')?.addEventListener('submit', async e=>{e.preventDefault(); await api('admin_add_avatar',Object.fromEntries(new FormData(e.target).entries())); toast('آواتار اضافه شد'); location.reload();});
$('#scenarioForm')?.addEventListener('submit', async e=>{e.preventDefault(); await api('admin_add_scenario_role',Object.fromEntries(new FormData(e.target).entries())); toast('سناریو/نقش اضافه شد');});

async function loadReports() {
  const box = $('#reportList'); if (!box) return;
  const reps = await api('admin_reports',{},'GET');
  box.innerHTML = reps.map(r=>`<div class="report">${r.reporter} ➜ ${r.against_name} | ${r.reason} | ${r.status}</div>`).join('') || 'گزارشی نیست';
}

// Live voice (WebRTC mesh with DB signaling)
async function ensureLocalAudio() {
  if (!localStream) localStream = await navigator.mediaDevices.getUserMedia({audio:true,video:false});
  return localStream;
}

async function createPeer(remoteUserId, offer=false) {
  if (peers.has(remoteUserId)) return peers.get(remoteUserId);
  const pc = new RTCPeerConnection({iceServers:[{urls:'stun:stun.l.google.com:19302'}]});
  (await ensureLocalAudio()).getTracks().forEach(t=>pc.addTrack(t, localStream));
  pc.ontrack = ev => { const a = new Audio(); a.srcObject = ev.streams[0]; a.autoplay = true; };
  pc.onicecandidate = ev => {
    if (ev.candidate) api('voice_signal_push',{lobby_id:currentLobbyId,to_user:remoteUserId,signal_type:'ice',payload:JSON.stringify(ev.candidate)}).catch(()=>{});
  };
  peers.set(remoteUserId, pc);
  if (offer) {
    const d = await pc.createOffer(); await pc.setLocalDescription(d);
    await api('voice_signal_push',{lobby_id:currentLobbyId,to_user:remoteUserId,signal_type:'offer',payload:JSON.stringify(d)});
  }
  return pc;
}

async function pollSignals() {
  if(!currentLobbyId) return;
  try {
    const signals = await api('voice_signal_pull',{lobby_id:currentLobbyId},'GET');
    for (const s of signals) {
      const from = +s.from_user;
      const pc = await createPeer(from, false);
      const payload = JSON.parse(s.payload);
      if (s.signal_type === 'offer') {
        await pc.setRemoteDescription(payload);
        const ans = await pc.createAnswer(); await pc.setLocalDescription(ans);
        await api('voice_signal_push',{lobby_id:currentLobbyId,to_user:from,signal_type:'answer',payload:JSON.stringify(ans)});
      } else if (s.signal_type === 'answer') {
        await pc.setRemoteDescription(payload);
      } else if (s.signal_type === 'ice') {
        await pc.addIceCandidate(payload);
      }
    }
  } catch(e) {}
  setTimeout(pollSignals, 1200);
}

$('#joinVoice')?.addEventListener('click', async ()=>{ await ensureLocalAudio(); toast('میکروفون فعال شد.'); });
$('#muteMe')?.addEventListener('click', ()=>{ if(localStream) localStream.getAudioTracks().forEach(t=>t.enabled=!t.enabled); });

$('#likeBtn')?.addEventListener('click', async()=>{ const t=prompt('ID بازیکن'); if(t) await api('vote_react',{lobby_id:currentLobbyId,target_user_id:t,type:'like'}); toast('لایک ثبت شد');});
$('#dislikeBtn')?.addEventListener('click', async()=>{ const t=prompt('ID بازیکن'); if(t) await api('vote_react',{lobby_id:currentLobbyId,target_user_id:t,type:'dislike'}); toast('دیس‌لایک ثبت شد');});
$('#challengeBtn')?.addEventListener('click', async()=>{ const t=prompt('ID بازیکن'); if(t) await api('challenge',{lobby_id:currentLobbyId,target_user_id:t}); toast('چالش اضافه شد');});

refreshLobbies();
loadReports();
