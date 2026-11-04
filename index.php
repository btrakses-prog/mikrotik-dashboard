<?php
require __DIR__.'/config.php';
$config = app_config();
$branding = $config['branding'] ?? [];
?>
<!doctype html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($branding['title'] ?? APP_NAME)?></title>
<link rel="stylesheet" href="assets/style.css?v=7">
</head><body>
<aside class="sidebar" id="sidebar">
  <div class="brand">
    <div class="brand-logo"><?php if(!empty($branding['logo'])): ?><img src="<?=htmlspecialchars($branding['logo'])?>"><?php else: ?><span>MT</span><?php endif; ?></div>
    <div class="brand-copy"><b><?=htmlspecialchars($branding['title'] ?? APP_NAME)?></b><small>Network dashboard</small></div>
    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Tutup menu">×</button>
  </div>
  <nav>
    <button class="nav active" data-page="dashboard"><span>▦</span> Dashboard</button>
    <button class="nav" data-page="loadbalance"><span>⇅</span> Load Balance</button>
    <button class="nav" data-page="shortcuts"><span>↗</span> Shortcut</button>
    <button class="nav" data-page="settings"><span>⚙</span> Pengaturan</button>
  </nav>
  <div class="side-bottom"><span class="pulse"></span> Local management panel</div>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<main class="main">
<header class="topbar">
  <button class="menu-btn" id="menuBtn">☰</button>
  <div><div class="eyebrow">MIKROTIK MANAGEMENT</div><h1 id="pageTitle">Dashboard</h1></div>
  <div class="top-actions"><span id="clock"></span><button class="btn light" id="refreshBtn">↻ Refresh</button></div>
</header>
<section class="banner" id="banner" style="<?php if(!empty($branding['banner'])): ?>background-image:linear-gradient(90deg,rgba(10,25,45,.85),rgba(10,25,45,.15)),url('<?=htmlspecialchars($branding['banner'])?>');<?php endif; ?>">
 <div><span class="banner-tag">NETWORK OVERVIEW</span><h2>Semua koneksi, dalam satu halaman.</h2><p>Pantau router dan akses perangkat pelanggan dengan lebih cepat.</p></div>
</section>
<div id="alert" class="alert hidden"></div>
<section class="page active" id="page-dashboard">
 <div class="section-head traffic-head dashboard-traffic-head"><div><h2>Monitoring Load Balance</h2><p>Traffic WAN real-time dari seluruh router yang dipantau</p></div><span class="live-label"><i></i> LIVE • UPDATE 5 DETIK</span></div>
 <div id="trafficGrid" class="traffic-grid"><div class="empty">Menunggu data interface...</div></div>
 <div class="stats">
  <article class="stat"><div class="stat-icon blue">⌘</div><div><small>Total Router</small><strong id="totalRouters">0</strong><em>Terdaftar</em></div></article>
  <article class="stat"><div class="stat-icon green">●</div><div><small>Router Online</small><strong id="onlineRouters">0</strong><em>API terhubung</em></div></article>
  <article class="stat"><div class="stat-icon teal">↑</div><div><small>PPPoE Online</small><strong id="onlineCount">0</strong><em>Session aktif</em></div></article>
  <article class="stat"><div class="stat-icon orange">○</div><div><small>PPPoE Offline</small><strong id="offlineCount">0</strong><em>Secret tidak aktif</em></div></article>
 </div>
 <div class="section-head"><div><h2>Daftar MikroTik</h2><p>Informasi identitas dan status koneksi router</p></div><button class="btn primary" data-goto="settings">＋ Tambah Router</button></div>
 <div id="routerCards" class="router-grid"><div class="empty">Memuat data router...</div></div>
 <div class="pppoe-columns">
  <div class="pppoe-panel"><div class="pppoe-title"><div><h2>PPPoE Online</h2><p>Session aktif dari semua router</p></div><span class="status online" id="onlineBadge">0 Online</span></div><input class="table-search" id="onlineSearch" placeholder="Cari username / IP online..."><div class="table-wrap"><table><thead><tr><th>USERNAME</th><th>IP ADDRESS</th><th>ROUTER</th><th>UPTIME</th><th>DOWNLOAD</th><th>UPLOAD</th><th>AKSI</th></tr></thead><tbody id="allOnlineRows"><tr><td colspan="7" class="empty">Memuat...</td></tr></tbody></table></div></div>
  <div class="pppoe-panel"><div class="pppoe-title"><div><h2>PPPoE Offline</h2><p>Secret tanpa session aktif</p></div><span class="status offline" id="offlineBadge">0 Offline</span></div><input class="table-search" id="offlineSearch" placeholder="Cari username / IP offline..."><div class="table-wrap"><table><thead><tr><th>USERNAME</th><th>REMOTE IP</th><th>ROUTER</th><th>SERVICE</th></tr></thead><tbody id="allOfflineRows"><tr><td colspan="4" class="empty">Memuat...</td></tr></tbody></table></div></div>
 </div>

</section>
<section class="page" id="page-loadbalance">
 <div class="section-head"><div><h2>Monitoring Traffic Load Balance</h2><p>Pantau download, upload, dan status interface WAN seluruh router.</p></div><div class="lb-head-actions"><span class="live-label"><i></i> LIVE • UPDATE 5 DETIK</span><button type="button" class="btn primary" id="addLBRouter" onclick="openLBRouterModal()">＋ Tambah Router Load Balance</button></div></div>
 <div id="trafficGridLB" class="traffic-grid"><div class="empty">Menunggu data interface...</div></div>
 <div class="panel lb-config-panel">
  <div class="section-head"><div><h2>Pengaturan Interface WAN</h2><p>Pilih router lalu centang interface yang digunakan untuk load balance.</p></div></div>
  <label class="lb-router-label">Pilih Router<select id="lbRouterSelect"><option value="">Pilih router...</option></select></label>
  <div id="lbInterfaceList" class="lb-interface-list"><div class="empty">Pilih router untuk membaca daftar interface.</div></div>
  <div class="lb-actions"><small id="lbSaveInfo" class="muted">Pilih interface WAN yang ingin dipantau.</small><button class="btn primary" id="saveLBInterfaces">Simpan Interface</button></div>
 </div>
</section>
<section class="page" id="page-shortcuts">
 <div class="section-head"><div><h2>Shortcut aplikasi</h2><p>Akses cepat aplikasi operasional</p></div></div>
 <div id="shortcutGrid" class="shortcut-grid"></div>
</section>
<section class="page" id="page-settings">
 <div class="settings-grid">
  <div class="panel">
   <div class="section-head"><div><h2>Pengaturan MikroTik</h2><p>Kelola konektivitas API hingga 5 router</p></div><button class="btn primary" id="newRouter">＋ Router</button></div>
   <div id="routerSettings"></div>
  </div>
  <div class="panel">
   <h2>Kelola Shortcut</h2><p class="muted">Tambah, ubah, atau hapus tautan aplikasi dari sini.</p><div class="section-head"><div></div><button type="button" class="btn primary" id="addShortcut">＋ Tambah Shortcut</button></div><div id="shortcutEditor"></div>
  </div>
  <div class="panel">
   <h2>Branding Dashboard</h2><p class="muted">Ganti logo, banner, dan nama halaman.</p>
   <form id="brandingForm" enctype="multipart/form-data">
    <label>Nama Dashboard<input name="title" id="brandTitle" maxlength="60"></label>
    <label>Subjudul<input name="subtitle" id="brandSubtitle" maxlength="100"></label>
    <label>Logo (PNG, JPG, WEBP)<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
    <label>Banner (PNG, JPG, WEBP)<input type="file" name="banner" accept="image/png,image/jpeg,image/webp"></label>
    <button class="btn primary">Simpan Branding</button>
   </form>
   <div class="note">Saran: gunakan logo transparan dan banner landscape 1600 × 400 px.</div>
  </div>
 </div>
</section>
<footer> MikroTik Shortcut Dashboard <span>•</span> RouterOS API</footer>
</main>
<div class="modal hidden" id="routerModal"><div class="modal-card"><div class="modal-head"><h2 id="modalTitle">Tambah MikroTik</h2><button class="x" data-close>×</button></div>
<form id="routerForm"><input type="hidden" name="id">
<label>Nama Router<input name="label" required placeholder="Contoh: MikroTik KRB1"></label>
<div class="form-row"><label>Host / IP<input name="host" required placeholder="192.168.88.1"></label><label>Port API<input name="port" type="number" value="8728" required></label></div>
<div class="form-row"><label>Username API<input name="username" required></label><label>Password API<input name="password" type="password" placeholder="Isi untuk mengganti password"></label></div>
<label>Interface yang dipantau (pisahkan dengan koma)<input name="monitor_interfaces" placeholder="ether1, ether2, sfp-sfpplus1"><small class="field-help">Isi interface WAN/load balance yang ingin dipantau. Nama harus sesuai RouterOS.</small></label><label class="check"><input type="checkbox" name="tls"> Gunakan API-SSL (TLS)</label>
<div class="modal-actions"><button type="button" class="btn light" data-close>Batal</button><button type="button" class="btn light" id="testRouter">Tes Koneksi</button><button class="btn primary">Simpan Router</button></div>
<p id="testResult" class="hint"></p></form></div></div>
<script src="assets/app.js?v=7"></script>
</body></html>
