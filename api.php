<?php
require __DIR__.'/config.php';
require __DIR__.'/lib/RouterOSClient.php';
header('X-Content-Type-Options: nosniff');

function router_by_id(array $config, string $id): ?array {
    foreach (($config['routers'] ?? []) as $router) if ((string)$router['id'] === $id) return $router;
    return null;
}
function connect_router(array $router): RouterOSClient {
    $api = new RouterOSClient(6); $api->connect($router); return $api;
}
function safe_router(array $r): array {
    unset($r['password']); return $r;
}
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$config = app_config();

try {
switch ($action) {
case 'overview':
    $result = [];
    $totalOnline = 0; $totalOffline = 0; $onlineRouters = 0;
    foreach ($config['routers'] as $r) {
        try {
            $api = connect_router($r);
            $identity = $api->command('/system/identity/print')[0] ?? [];
            $resource = $api->command('/system/resource/print')[0] ?? [];
            $routerboard = $api->command('/system/routerboard/print')[0] ?? [];
            $active = $api->command('/ppp/active/print');
            $pppInterfaces = $api->command('/interface/print', ['.proplist'=>'.id,name,type,rx-byte,tx-byte,running']);
            $pppIfByName = [];
            foreach ($pppInterfaces as $pi) {
                $ifName = (string)($pi['name'] ?? '');
                if ($ifName !== '') $pppIfByName[$ifName] = $pi;
            }
            $secrets = $api->command('/ppp/secret/print');
            $onlineNames = [];
            foreach ($active as $a) $onlineNames[$a['name'] ?? ''] = true;
            $offline = 0;
            foreach ($secrets as $s) if (!isset($onlineNames[$s['name'] ?? ''])) $offline++;
            $online = count($active);
            $pppoeOnline = [];
            foreach ($active as $a) {
                $username = (string)($a['name'] ?? '-');
                $if = $pppIfByName['<pppoe-'.$username.'>'] ?? ($pppIfByName['pppoe-'.$username] ?? []);
                $pppoeOnline[] = ['username'=>$username,'ip'=>$a['address']??'-','uptime'=>$a['uptime']??'-','service'=>$a['service']??'-','status'=>'online','router'=>$r['label'],'router_id'=>$r['id'],'session_id'=>$a['.id']??'','rx_bytes'=>(int)($if['rx-byte']??0),'tx_bytes'=>(int)($if['tx-byte']??0),'interface'=>$if['name']??'','traffic_available'=>!empty($if)];
            }
            $pppoeOffline = [];
            foreach ($secrets as $s) {
                $name = $s['name'] ?? '';
                if (!isset($onlineNames[$name])) $pppoeOffline[] = ['username'=>$name,'ip'=>$s['remote-address']??'-','uptime'=>'-','service'=>$s['service']??'-','status'=>'offline','router'=>$r['label']];
            }
            $traffic = [];
            $wanted = $r['monitor_interfaces'] ?? [];
            if ($wanted) {
                $interfaces = $api->command('/interface/print');
                $byName = [];
                foreach ($interfaces as $iface) $byName[$iface['name'] ?? ''] = $iface;
                foreach ($wanted as $ifname) {
                    if (!isset($byName[$ifname])) continue;
                    $stats = $api->command('/interface/monitor-traffic', ['interface'=>$ifname, 'once'=>'']);
                    $st = $stats[0] ?? [];
                    $traffic[] = ['name'=>$ifname,'rx_bps'=>(int)($st['rx-bits-per-second']??0),'tx_bps'=>(int)($st['tx-bits-per-second']??0),'rx_bytes'=>(int)($byName[$ifname]['rx-byte']??0),'tx_bytes'=>(int)($byName[$ifname]['tx-byte']??0),'running'=>($byName[$ifname]['running']??'false')==='true'];
                }
            }
            $totalOnline += $online; $totalOffline += $offline; $onlineRouters++;
            $result[] = [
                'id'=>$r['id'], 'label'=>$r['label'], 'connected'=>true,
                'identity'=>$identity['name'] ?? $r['label'],
                'board'=>$resource['board-name'] ?? ($routerboard['model'] ?? '-'),
                'type'=>$routerboard['model'] ?? ($resource['board-name'] ?? '-'),
                'serial'=>$routerboard['serial-number'] ?? '-',
                'version'=>$resource['version'] ?? '-',
                'uptime'=>$resource['uptime'] ?? '-',
                'online'=>$online, 'offline'=>$offline, 'pppoe_online'=>$pppoeOnline, 'pppoe_offline'=>$pppoeOffline, 'traffic'=>$traffic, 'monitor_interfaces'=>$wanted
            ];
            $api->close();
        } catch (Throwable $e) {
            $result[] = ['id'=>$r['id'],'label'=>$r['label'],'connected'=>false,'error'=>$e->getMessage(),'online'=>0,'offline'=>0];
        }
    }
    json_response(['routers'=>$result,'totals'=>['routers'=>count($config['routers']),'onlineRouters'=>$onlineRouters,'online'=>$totalOnline,'offline'=>$totalOffline]]);
case 'pppoe':
    $id = (string)($_GET['router'] ?? '');
    $status = ($_GET['status'] ?? 'online') === 'offline' ? 'offline' : 'online';
    $r = router_by_id($config, $id);
    if (!$r) json_response(['error'=>'Router tidak ditemukan'],404);
    $api = connect_router($r);
    $active = $api->command('/ppp/active/print');
    $secrets = $api->command('/ppp/secret/print');
    $activeMap = [];
    foreach ($active as $a) $activeMap[$a['name'] ?? ''] = $a;
    $rows = [];
    if ($status === 'online') {
        foreach ($active as $a) $rows[] = ['username'=>$a['name']??'-','ip'=>$a['address']??'-','uptime'=>$a['uptime']??'-','service'=>$a['service']??'-','status'=>'online','router'=>$r['label']];
    } else {
        foreach ($secrets as $s) {
            $name = $s['name'] ?? '';
            if (!isset($activeMap[$name])) $rows[] = ['username'=>$name,'ip'=>$s['remote-address']??($s['local-address']??'-'),'uptime'=>'-','service'=>$s['service']??'-','status'=>'offline','router'=>$r['label']];
        }
    }
    $api->close();
    json_response(['rows'=>$rows,'router'=>$r['label'],'status'=>$status]);
case 'kick_pppoe':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method tidak diizinkan'],405);
    $id = (string)($_POST['router'] ?? '');
    $sessionId = trim((string)($_POST['session_id'] ?? ''));
    if ($sessionId === '' || !preg_match('/^\*[A-Fa-f0-9]+$/', $sessionId)) json_response(['error'=>'ID sesi PPPoE tidak valid'],400);
    $r = router_by_id($config, $id);
    if (!$r) json_response(['error'=>'Router tidak ditemukan'],404);
    $api = connect_router($r);
    $api->command('/ppp/active/remove', ['.id'=>$sessionId]);
    $api->close();
    json_response(['ok'=>true,'message'=>'Sesi PPPoE berhasil diputus. Pelanggan dapat tersambung kembali dengan profile terbaru.']);
case 'interfaces':
    $id = (string)($_GET['router'] ?? '');
    $r = router_by_id($config, $id);
    if (!$r) json_response(['error'=>'Router tidak ditemukan'],404);
    $api = connect_router($r);
    $items = $api->command('/interface/print');
    $api->close();
    json_response(['interfaces'=>array_values(array_map(fn($x)=>$x['name']??'', $items))]);
case 'save_monitor_interfaces':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method tidak diizinkan'],405);
    $id = (string)($_POST['router'] ?? '');
    $selected = json_decode($_POST['interfaces'] ?? '[]', true);
    if (!is_array($selected)) json_response(['error'=>'Daftar interface tidak valid'],400);
    $found = false;
    foreach ($config['routers'] as &$item) {
        if ((string)$item['id'] === $id) {
            $item['monitor_interfaces'] = array_values(array_unique(array_filter(array_map('trim', $selected), function($v){ return $v !== ''; })));
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) json_response(['error'=>'Router tidak ditemukan'],404);
    save_config($config);
    json_response(['ok'=>true,'message'=>'Interface monitoring berhasil disimpan']);
case 'save_router':
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_response(['error'=>'Method tidak diizinkan'],405);
    $id = trim($_POST['id'] ?? '');
    $existing = $id !== '' ? router_by_id($config,$id) : null;
    if (!$existing && count($config['routers']) >= 5) json_response(['error'=>'Maksimal 5 router'],400);
    $router = [
        'id'=>$existing['id'] ?? bin2hex(random_bytes(5)),
        'label'=>trim($_POST['label'] ?? ''),
        'host'=>trim($_POST['host'] ?? ''),
        'port'=>(int)($_POST['port'] ?? 8728),
        'username'=>trim($_POST['username'] ?? ''),
        'password'=>(trim($_POST['password'] ?? '') !== '' ? $_POST['password'] : ($existing['password'] ?? '')),
        'tls'=>isset($_POST['tls']) && $_POST['tls'] === 'on',
        'monitor_interfaces'=>array_values(array_filter(array_map('trim', explode(',', $_POST['monitor_interfaces'] ?? ''))))
    ];
    if (!$router['label'] || !$router['host'] || !$router['username'] || !$router['password']) json_response(['error'=>'Lengkapi nama, host, username dan password API'],400);
    if ($existing) {
        foreach ($config['routers'] as &$item) if ($item['id'] === $id) $item = $router;
        unset($item);
    } else $config['routers'][] = $router;
    save_config($config);
    json_response(['ok'=>true,'message'=>'Konfigurasi router tersimpan']);
case 'delete_router':
    $id = (string)($_POST['id'] ?? '');
    $config['routers'] = array_values(array_filter($config['routers'], fn($r)=>$r['id'] !== $id));
    save_config($config); json_response(['ok'=>true]);
case 'test_router':
    $r = [
        'host'=>trim($_POST['host']??''),'port'=>(int)($_POST['port']??8728),
        'username'=>trim($_POST['username']??''),'password'=>$_POST['password']??'',
        'tls'=>isset($_POST['tls']) && $_POST['tls']==='on',
        'monitor_interfaces'=>array_values(array_filter(array_map('trim', explode(',', $_POST['monitor_interfaces'] ?? ''))))
    ];
    if (!$r['password'] && !empty($_POST['id'])) {
        $old = router_by_id($config,(string)$_POST['id']); if ($old) $r['password']=$old['password'];
    }
    $api=connect_router($r);
    $identity=$api->command('/system/identity/print')[0]['name']??'Router';
    $api->close(); json_response(['ok'=>true,'message'=>'Terhubung ke '.$identity]);
case 'save_branding':
    $branding = $config['branding'] ?? [];
    $branding['title'] = trim($_POST['title'] ?? 'MikroTik Shortcut');
    $branding['subtitle'] = trim($_POST['subtitle'] ?? 'Network Management Dashboard');
    foreach (['logo','banner'] as $field) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) continue;
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) json_response(['error'=>'Upload gagal'],400);
        $f = $_FILES[$field];
        if ($f['size'] > 3*1024*1024) json_response(['error'=>'Ukuran gambar maksimal 3 MB'],400);
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $ext = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime] ?? null;
        if (!$ext) json_response(['error'=>'Format gambar harus PNG, JPG, atau WEBP'],400);
        $name = $field.'-'.bin2hex(random_bytes(5)).'.'.$ext;
        if (!move_uploaded_file($f['tmp_name'],UPLOAD_DIR.'/'.$name)) json_response(['error'=>'Tidak dapat menyimpan gambar'],500);
        $branding[$field] = 'uploads/'.$name;
    }
    $config['branding']=$branding; save_config($config);
    json_response(['ok'=>true,'message'=>'Branding tersimpan. Muat ulang halaman untuk melihat perubahan.']);
case 'get_settings':
    $routers=array_map('safe_router',$config['routers']);
    json_response(['routers'=>$routers,'branding'=>$config['branding']??[],'shortcuts'=>$config['shortcuts']??[]]);
case 'save_shortcuts':
    $items=json_decode($_POST['items']??'[]',true);
    if (!is_array($items)) json_response(['error'=>'Format shortcut tidak valid'],400);
    $clean=[];
    foreach(array_slice($items,0,30) as $item) {
        $url=trim((string)($item['url']??''));
        if (!preg_match('~^(https?://|#)~i',$url)) continue;
        $clean[]=['label'=>mb_substr(trim((string)($item['label']??'')),0,50),'url'=>$url,'icon'=>'link'];
    }
    $config['shortcuts']=$clean; save_config($config); json_response(['ok'=>true]);
default: json_response(['error'=>'Aksi tidak dikenal'],404);
}
} catch (Throwable $e) {
    json_response(['error'=>$e->getMessage()],500);
}
