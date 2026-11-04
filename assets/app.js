const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
let routers=[],shortcuts=[],overviewData=[],rateCache={},pppRateCache={},lastRefresh=0;
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const fmtRate=n=>{n=Number(n)||0;return n>=1e9?(n/1e9).toFixed(2)+' Gbps':n>=1e6?(n/1e6).toFixed(2)+' Mbps':n>=1e3?(n/1e3).toFixed(1)+' Kbps':n.toFixed(0)+' bps'};
async function api(action,data={},method='GET'){let url='api.php?action='+encodeURIComponent(action),opts={};if(method==='GET')url+='&'+new URLSearchParams(data);else{opts.method='POST';opts.body=data instanceof FormData?data:new URLSearchParams(data)}const res=await fetch(url,opts);const raw=await res.text();let j;try{j=JSON.parse(raw)}catch(_){const detail=raw.replace(/<[^>]*>/g,' ').replace(/\s+/g,' ').trim().slice(0,220);throw Error(`Server tidak mengirim JSON (HTTP ${res.status}). ${detail||'Periksa URL API dan konfigurasi Apache/PHP.'}`)}if(!res.ok||j.error)throw Error(j.error||`Permintaan gagal (HTTP ${res.status})`);return j}
function notify(m,b=false){const a=$('#alert');a.textContent=m;a.className='alert '+(b?'error':'success');setTimeout(()=>a.classList.add('hidden'),4500)}
function closeSidebar(){const sb=$('#sidebar');sb.classList.remove('open');$('#sidebarOverlay')?.classList.remove('show');$('#menuBtn')?.setAttribute('aria-expanded','false')}
function toggleSidebar(){const sb=$('#sidebar'),open=!sb.classList.contains('open');sb.classList.toggle('open',open);$('#sidebarOverlay')?.classList.toggle('show',open);$('#menuBtn')?.setAttribute('aria-expanded',String(open))}
function page(n){$$('.page').forEach(p=>p.classList.toggle('active',p.id==='page-'+n));$$('.nav').forEach(x=>x.classList.toggle('active',x.dataset.page===n));$('#pageTitle').textContent={dashboard:'Dashboard Monitoring',loadbalance:'Monitoring Load Balance',shortcuts:'Shortcut Aplikasi',settings:'Pengaturan',genieacs:'GenieACS Monitoring'}[n]||n;if(n==='genieacs')loadGenieacs();if(n==='settings')loadSettings();if(n==='shortcuts')loadSettings().then(()=>renderShortcuts());if(n==='loadbalance')loadLoadBalancePage();closeSidebar()}
$$('.nav').forEach(n=>n.onclick=()=>page(n.dataset.page));$$('[data-goto]').forEach(n=>n.onclick=()=>page(n.dataset.goto));$('#menuBtn').onclick=toggleSidebar;$('#sidebarClose').onclick=closeSidebar;$('#sidebarOverlay').onclick=closeSidebar;document.addEventListener('keydown',e=>{if(e.key==='Escape')closeSidebar()});
function clock(){if($('#clock'))$('#clock').textContent=new Date().toLocaleString('id-ID')}clock();setInterval(clock,30000);
// Theme preference: light is the default; user choice is kept in this browser.
const themeButton=$('#themeToggle');
function applyTheme(mode){const dark=mode==='dark';document.body.classList.toggle('dark',dark);if(themeButton){themeButton.textContent=dark?'☀ Light':'☾ Dark';themeButton.setAttribute('aria-pressed',String(dark));themeButton.title=dark?'Beralih ke Light Mode':'Beralih ke Dark Mode';}}
let savedTheme='light';try{savedTheme=localStorage.getItem('noc-dashboard-theme')||'light'}catch(e){}
applyTheme(savedTheme);
if(themeButton)themeButton.addEventListener('click',()=>{const next=document.body.classList.contains('dark')?'light':'dark';applyTheme(next);try{localStorage.setItem('noc-dashboard-theme',next)}catch(e){}});
$('#refreshBtn').onclick=loadOverview;
async function loadOverview(){
 try{
  const d=await api('overview');overviewData=d.routers;routers=d.routers;
  $('#totalRouters').textContent=d.totals.routers;$('#onlineRouters').textContent=d.totals.onlineRouters;$('#onlineCount').textContent=d.totals.online;$('#offlineCount').textContent=d.totals.offline;
  $('#routerCards').innerHTML=routers.length?routers.map(r=>`<article class="router-card"><div class="router-card-top"><div class="router-symbol">⌘</div><span class="status ${r.connected?'online':'offline'}">${r.connected?'● Terhubung':'● Terputus'}</span></div><h3>${esc(r.label)}</h3><div class="identity">${esc(r.identity||r.error||'Tidak tersedia')}</div>${r.connected?`<div class="spec-grid"><div><small>BOARD / TYPE</small><b>${esc(r.type||r.board)}</b></div><div><small>SERIAL NUMBER</small><b>${esc(r.serial)}</b></div><div><small>ROUTEROS</small><b>${esc(r.version)}</b></div><div><small>UPTIME</small><b>${esc(r.uptime)}</b></div></div><div class="router-counts"><span><b>${r.online}</b> Online</span><span><b>${r.offline}</b> Offline</span></div><div class="monitored-ifs">Interface: ${esc((r.monitor_interfaces||[]).join(', ')||'Belum dipilih')}</div>`:`<p class="error-text">${esc(r.error||'Koneksi gagal')}</p>`}<button class="btn light full" onclick="editRouter('${r.id}')">⚙ Pengaturan Router</button></article>`).join(''):'<div class="empty">Belum ada MikroTik. Tambahkan router melalui Pengaturan.</div>';
  $('#onlineBadge').textContent=d.totals.online+' Online';$('#offlineBadge').textContent=d.totals.offline+' Offline';
  const online=routers.flatMap(r=>r.pppoe_online||[]),offline=routers.flatMap(r=>r.pppoe_offline||[]);
  renderPppoeTable('allOnlineRows',online,'online');renderPppoeTable('allOfflineRows',offline,'offline');
  renderTraffic(routers,'trafficGrid');
  renderTraffic(routers,'trafficGridLB');
 }catch(e){notify(e.message,true)}
}
function renderPppoeTable(id,rows,type){
 const q=($('#'+(type==='online'?'onlineSearch':'offlineSearch'))?.value||'').toLowerCase();
 const filtered=rows.filter(r=>(r.username+' '+r.ip+' '+r.router).toLowerCase().includes(q));
 const target=$('#'+id);
 target.innerHTML=filtered.length?filtered.map(r=>{
  if(type==='online'){
   const key=(r.router_id||r.router)+'|'+r.username,now=Date.now(),old=pppRateCache[key];let down=0,up=0;
   const rx=Number(r.rx_bytes)||0,tx=Number(r.tx_bytes)||0;
   if(old&&now>old.time){const sec=(now-old.time)/1000;up=Math.max(0,(rx-old.rx)/sec*8);down=Math.max(0,(tx-old.tx)/sec*8);}
   pppRateCache[key]={rx,tx,time:now};
   const downText=r.traffic_available?fmtRate(down):'—',upText=r.traffic_available?fmtRate(up):'—';
   return `<tr><td><b>${esc(r.username)}</b></td><td class="mono">${esc(r.ip)}</td><td>${esc(r.router)}</td><td>${esc(r.uptime)}</td><td><span class="rate-down">↓ ${downText}</span></td><td><span class="rate-up">↑ ${upText}</span></td><td><button class="btn tiny" onclick="remoteIp('${esc(r.ip)}')">Remote ↗</button> <button class="btn tiny danger" onclick="kickPppoe('${esc(r.router_id)}','${esc(r.session_id)}','${esc(r.username)}')">Kick</button></td></tr>`;
  }
  return `<tr><td><b>${esc(r.username)}</b></td><td class="mono">${esc(r.ip)}</td><td>${esc(r.router)}</td><td>${esc(r.service)}</td></tr>`;
 }).join(''):`<tr><td colspan="${type==='online'?7:4}" class="empty">Tidak ada data.</td></tr>`;
}
async function kickPppoe(routerId,sessionId,username){
 if(!confirm(`Putuskan sesi PPPoE ${username}? Pelanggan akan terputus sementara dan dapat login kembali.`))return;
 try{await api('kick_pppoe',{router:routerId,session_id:sessionId},'POST');notify(`Sesi ${username} berhasil di-kick.`);await loadOverview();}
 catch(e){notify('Kick gagal: '+e.message,true)}
}
window.kickPppoe=kickPppoe;
$('#onlineSearch').oninput=()=>renderPppoeTable('allOnlineRows',routers.flatMap(r=>r.pppoe_online||[]),'online');
$('#offlineSearch').oninput=()=>renderPppoeTable('allOfflineRows',routers.flatMap(r=>r.pppoe_offline||[]),'offline');
function remoteIp(ip){window.open('http://'+ip,'_blank','noopener')}
function renderTraffic(list,targetId){
 const all=list.flatMap(r=>(r.traffic||[]).map(t=>({...t,router:r.label,id:r.id})));
 const target=$('#'+targetId);if(!target)return;
 target.innerHTML=all.length?all.map(t=>{
  const key=t.id+'|'+t.name,now=Date.now(),old=rateCache[key];let rx=0,tx=0;
  if(old&&now>old.time){const sec=(now-old.time)/1000;rx=Math.max(0,(t.rx_bytes-old.rx)/sec*8);tx=Math.max(0,(t.tx_bytes-old.tx)/sec*8);}
  if(!old){rx=Number(t.rx_bps)||0;tx=Number(t.tx_bps)||0;}
  rateCache[key]={rx:t.rx_bytes,tx:t.tx_bytes,time:now};
  return `<article class="traffic-card"><div class="traffic-card-head"><div><small>${esc(t.router)}</small><h3>${esc(t.name)}</h3></div><span class="if-state ${t.running?'up':'down'}">${t.running?'UP':'DOWN'}</span></div><div class="traffic-values"><div><span class="rx-dot"></span><small>DOWNLOAD / RX</small><b>${fmtRate(rx)}</b></div><div><span class="tx-dot"></span><small>UPLOAD / TX</small><b>${fmtRate(tx)}</b></div></div><div class="traffic-bar"><span style="width:${Math.min(100,rx/(rx+tx||1)*100)}%"></span></div><div class="traffic-foot"><span>RX ${fmtRate(t.rx_bps)}</span><span>TX ${fmtRate(t.tx_bps)}</span></div></article>`;
 }).join(''):'<div class="empty">Belum ada interface dipilih. Atur interface WAN pada menu Pengaturan.</div>';
}
async function loadLoadBalancePage(){
 try{
  const d=await api('get_settings');routers=d.routers||[];
  const sel=$('#lbRouterSelect');const current=sel.value;
  sel.innerHTML='<option value="">Pilih router...</option>'+routers.map(r=>`<option value="${esc(r.id)}">${esc(r.label)}</option>`).join('');
  if(current&&routers.some(r=>r.id===current))sel.value=current;
  else if(routers.length)sel.value=routers[0].id;
  await loadLBInterfaces();
 }catch(e){notify(e.message,true)}
}
async function loadLBInterfaces(){
 const id=$('#lbRouterSelect').value,box=$('#lbInterfaceList');
 if(!id){box.innerHTML='<div class="empty">Belum ada router. Tambahkan router melalui Pengaturan.</div>';return}
 const router=routers.find(r=>r.id===id);box.innerHTML='<div class="empty">Membaca interface dari MikroTik...</div>';
 try{
  const d=await api('interfaces',{router:id});const selected=router.monitor_interfaces||[];
  const names=d.interfaces||[];
  box.innerHTML=names.length?names.map((name,i)=>`<label class="lb-if-option"><input type="checkbox" value="${esc(name)}" ${selected.includes(name)?'checked':''}><span><b>${esc(name)}</b><small>${selected.includes(name)?'Sedang dipantau':'Tidak dipantau'}</small></span></label>`).join(''):'<div class="empty">Tidak ada interface ditemukan.</div>';
  $('#lbSaveInfo').textContent=`${selected.length} interface sedang dipantau pada ${router.label}.`;
 }catch(e){box.innerHTML=`<div class="empty error-text">Gagal membaca interface: ${esc(e.message)}</div>`}
}
$('#lbRouterSelect').onchange=loadLBInterfaces;
async function openLBRouterModal(){
 try {
  const settings=await api('get_settings');
  routers=settings.routers||[];
  if(routers.length>=5){notify('Maksimal 5 router sudah terdaftar. Hapus salah satu router terlebih dahulu.',true);return;}
  const form=$('#routerForm');form.reset();form.elements.namedItem('id').value='';
  $('#modalTitle').textContent='Tambah Router Load Balance';
  $('#testResult').textContent='Masukkan identitas dan akses API MikroTik, lalu klik Simpan Router.';
  showModal();
 } catch(err) { notify('Form tambah router gagal dibuka: '+err.message,true); }
}
window.openLBRouterModal=openLBRouterModal;

$('#saveLBInterfaces').onclick=async()=>{
 const id=$('#lbRouterSelect').value;if(!id){notify('Pilih router terlebih dahulu.',true);return}
 const selected=$$('#lbInterfaceList input[type="checkbox"]:checked').map(x=>x.value);
 try{await api('save_monitor_interfaces',{router:id,interfaces:JSON.stringify(selected)},'POST');notify('Interface Load Balance berhasil disimpan.');await loadOverview();await loadLoadBalancePage()}catch(e){notify(e.message,true)}
};
let genieRows=[],genieSort={key:'username',dir:1};
async function loadGenieacs(){
 const body=$('#genieRows'); if(!body)return; body.innerHTML='<tr><td colspan="7" class="empty">Mengambil data GenieACS...</td></tr>';
 try{
  const d=await api('genieacs_devices');genieRows=d.rows||[];
  $('#genieTotal').textContent=genieRows.length;$('#genieOnline').textContent=genieRows.filter(x=>x.status==='online').length;$('#genieOffline').textContent=genieRows.filter(x=>x.status==='offline').length;$('#genieWeak').textContent=genieRows.filter(x=>x.rx!==null&&Number(x.rx)<=-24).length;
  const filter=$('#genieRouterFilter'),old=filter.value;filter.innerHTML='<option value="">Semua router</option>'+[...new Set(genieRows.map(x=>x.router))].map(x=>`<option value="${esc(x)}">${esc(x)}</option>`).join('');filter.value=old;
  renderGenieRows();
  if(d.errors&&d.errors.length)notify('GenieACS: '+d.errors.map(x=>x.router+': '+x.error).join(' | '),true);
 }catch(e){body.innerHTML=`<tr><td colspan="7" class="empty error-text">${esc(e.message)}</td></tr>`}
}
function renderGenieRows(){
 const q=($('#genieSearch')?.value||'').toLowerCase(),router=$('#genieRouterFilter')?.value||'',status=$('#genieStatusFilter')?.value||'';
 const rows=genieRows.filter(x=>(!router||x.router===router)&&(!status||x.status===status)&&[x.username,x.serial,x.model,x.router].join(' ').toLowerCase().includes(q));
 const key=genieSort.key,dir=genieSort.dir;
 rows.sort((a,b)=>{
  let av=a[key],bv=b[key];
  if(key==='rx'){av=av===null?-999:Number(av);bv=bv===null?-999:Number(bv)}
  else if(key==='last_inform'){av=av?new Date(av).getTime():0;bv=bv?new Date(bv).getTime():0}
  else {av=String(av??'').toLowerCase();bv=String(bv??'').toLowerCase()}
  if(av<bv)return -1*dir;if(av>bv)return 1*dir;return 0;
 });
 $$('.genie-table th[data-sort]').forEach(th=>{
  const active=th.dataset.sort===key;th.classList.toggle('sorted',active);
  th.setAttribute('aria-sort',active?(dir===1?'ascending':'descending'):'none');
  const label=th.dataset.label||th.textContent.replace(/[▲▼↕]/g,'').trim();
  th.textContent=label+(active?(dir===1?' ▲':' ▼'):' ↕');
 });
 $('#genieRows').innerHTML=rows.length?rows.map(x=>{const rx=x.rx===null?'N/A':Number(x.rx).toFixed(2)+' dBm';const cls=x.rx===null?'unknown':x.rx>-20?'good':x.rx>-24?'warn':'bad';return `<tr><td><b>${esc(x.username)}</b></td><td><span class="status ${x.status}">${x.status==='online'?'● Online':'● Offline'}</span></td><td>${esc(x.model)}</td><td class="mono">${esc(x.serial)}</td><td><span class="rx-level ${cls}">${rx}</span></td><td>${x.last_inform?esc(new Date(x.last_inform).toLocaleString('id-ID')):'-'}</td><td>${esc(x.router)}</td></tr>`}).join(''):'<tr><td colspan="7" class="empty">Tidak ada perangkat yang cocok.</td></tr>';
}
$$('.genie-table th[data-sort]').forEach(th=>th.addEventListener('click',()=>{
 const key=th.dataset.sort;if(genieSort.key===key)genieSort.dir*=-1;else genieSort={key,dir:1};renderGenieRows();
}));
$('#refreshGenieacs')?.addEventListener('click',loadGenieacs);$('#genieSearch')?.addEventListener('input',renderGenieRows);$('#genieRouterFilter')?.addEventListener('change',renderGenieRows);$('#genieStatusFilter')?.addEventListener('change',renderGenieRows);
// GenieACS hanya diperbarui saat halaman dibuka atau tombol Refresh ditekan.
$('#testGenieacs')?.addEventListener('click',async()=>{const f=new FormData($('#routerForm'));try{const d=await api('test_genieacs',f,'POST');$('#genieTestResult').textContent='✓ '+d.message;$('#genieTestResult').className='hint success-text'}catch(e){$('#genieTestResult').textContent='✕ '+e.message;$('#genieTestResult').className='hint error-text'}});

async function loadSettings(){
 try{const d=await api('get_settings');routers=d.routers;shortcuts=d.shortcuts||[];$('#brandTitle').value=d.branding.title||'MikroTik Shortcut';$('#brandSubtitle').value=d.branding.subtitle||'Network Management Dashboard';
 renderShortcutEditor();
 $('#routerSettings').innerHTML=routers.length?routers.map(r=>`<div class="setting-router"><div><b>${esc(r.label)}</b><small>${esc(r.host)}:${r.port} • ${r.tls?'API-SSL':'API'}<br>Monitor: ${esc((r.monitor_interfaces||[]).join(', ')||'Belum diatur')}<br>GenieACS: ${esc(r.genieacs_url||'Belum diatur')}</small></div><div><button class="btn tiny" onclick="editRouter('${r.id}')">Edit</button> <button class="btn tiny danger" onclick="deleteRouter('${r.id}')">Hapus</button></div></div>`).join(''):'<div class="empty">Belum ada router terdaftar.</div>';
 }catch(e){notify(e.message,true)}
}
const modal=$('#routerModal');function showModal(){modal.classList.remove('hidden');$('#testResult').textContent=''}function hideModal(){modal.classList.add('hidden')}
$$('[data-close]').forEach(b=>b.onclick=hideModal);
$('#newRouter').onclick=()=>{if(routers.length>=5){notify('Maksimal 5 router.',true);return}$('#routerForm').reset();$('#routerForm').elements.id.value='';$('#modalTitle').textContent='Tambah MikroTik';showModal()};
function editRouter(id){const r=routers.find(x=>x.id===id);if(!r)return;const f=$('#routerForm');f.reset();Object.entries(r).forEach(([k,v])=>{if(f.elements[k])f.elements[k].value=Array.isArray(v)?v.join(', '):v});f.elements.password.value='';$('#modalTitle').textContent='Edit MikroTik';showModal()}
$('#routerForm').onsubmit=async e=>{e.preventDefault();try{const isNew=!e.target.elements.id.value;await api('save_router',new FormData(e.target),'POST');hideModal();notify('Konfigurasi router tersimpan.');await loadSettings();await loadOverview();await loadLoadBalancePage();if(isNew&&routers.length){const added=routers[routers.length-1];$('#lbRouterSelect').value=added.id;await loadLBInterfaces()}}catch(err){notify(err.message,true)}};
$('#testRouter').onclick=async()=>{const f=new FormData($('#routerForm'));try{const d=await api('test_router',f,'POST');$('#testResult').textContent='✓ '+d.message;$('#testResult').className='hint success-text'}catch(e){$('#testResult').textContent='✕ '+e.message;$('#testResult').className='hint error-text'}};
async function deleteRouter(id){if(!confirm('Hapus router ini?'))return;try{await api('delete_router',{id},'POST');notify('Router dihapus.');loadSettings();loadOverview()}catch(e){notify(e.message,true)}}
$('#brandingForm').onsubmit=async e=>{e.preventDefault();try{await api('save_branding',new FormData(e.target),'POST');notify('Branding tersimpan.');setTimeout(()=>location.reload(),800)}catch(err){notify(err.message,true)}};
function renderShortcuts(){$('#shortcutGrid').innerHTML=shortcuts.map(s=>`<a class="shortcut-card" href="${esc(s.url)}" target="_blank" rel="noopener"><span class="shortcut-icon">↗</span><b>${esc(s.label)}</b><small>Buka aplikasi</small></a>`).join('')||'<div class="empty">Belum ada shortcut.</div>';renderShortcutEditor()}
function renderShortcutEditor(){const editor=$('#shortcutEditor');if(!editor)return;editor.innerHTML=shortcuts.map((s,i)=>`<div class="shortcut-edit"><input data-label="${i}" value="${esc(s.label)}" placeholder="Nama shortcut"><input data-url="${i}" value="${esc(s.url)}" placeholder="https://alamat-aplikasi"><button class="btn tiny danger" onclick="removeShortcut(${i})">×</button></div>`).join('')+'<button class="btn primary" id="saveShortcuts">Simpan Shortcut</button>';$('#saveShortcuts').onclick=saveShortcuts}
$('#addShortcut').onclick=()=>{shortcuts.push({label:'Shortcut baru',url:'https://'});renderShortcutEditor()};
function removeShortcut(i){shortcuts.splice(i,1);renderShortcutEditor()}
async function saveShortcuts(){shortcuts=shortcuts.map((s,i)=>({label:$(`[data-label="${i}"]`).value,url:$(`[data-url="${i}"]`).value}));try{await api('save_shortcuts',{items:JSON.stringify(shortcuts)},'POST');notify('Shortcut disimpan.');renderShortcuts()}catch(e){notify(e.message,true)}}
// Dashboard tetap auto-refresh setiap 5 detik; GenieACS hanya refresh manual.
loadOverview();
setInterval(loadOverview,5000);
