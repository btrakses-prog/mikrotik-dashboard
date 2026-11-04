# MikroTik Load Balance Dashboard — V13.2

Dashboard PHP + JSON untuk memantau hingga 5 router MikroTik RouterOS v7, PPPoE, Load Balance, dan GenieACS. Tidak memerlukan MySQL.

## Persyaratan
- PHP 8.0+ (paket PHP 8) atau PHP 7.4+ (paket PHP 7.4).
- Ekstensi PHP JSON dan akses socket/stream.
- Server dapat menjangkau MikroTik API, GenieACS NBI, dan `api.telegram.org`.

## Instalasi / update
1. Ekstrak paket ke folder web server.
2. Untuk update, backup aplikasi lama lalu timpa file aplikasi.
3. **Pertahankan `data/config.json`** agar konfigurasi router, GenieACS, shortcut, branding, dan notifikasi tidak hilang.
4. Pastikan folder `data` dan `uploads` dapat ditulis oleh user web server.
5. Buka dashboard, lalu periksa menu Pengaturan.

Contoh permission (sesuaikan lokasi instalasi):
```bash
sudo chown -R www-data:www-data /var/www/html/dashboard/data /var/www/html/dashboard/uploads
sudo chmod 750 /var/www/html/dashboard/data /var/www/html/dashboard/uploads
```

## Fitur utama
- Ringkasan router, identitas perangkat, uptime, dan status koneksi.
- PPPoE online/offline, pencarian, remote IP, dan Kick PPPoE.
- Monitoring traffic interface Load Balance dengan refresh otomatis.
- GenieACS per router: status ONU, PPP username, model, serial number, RX power, dan Last Inform; pengurutan tabel tersedia.
- Shortcut aplikasi, branding, serta tema terang/gelap.
- Notifikasi Telegram untuk router, WAN interface, ONU offline, RX buruk, dan PPPoE offline massal.
- Pengaturan notifikasi per jenis, cooldown/anti-spam, pengingat, pemulihan, ringkasan ONU, dan ambang monitoring.
- Pesan ONU/RX menyertakan PPPoE username dan serial number (SN) bila data GenieACS tersedia. Notifikasi PPPoE massal menyertakan daftar username offline dan SN yang berhasil dicocokkan.

## Konfigurasi MikroTik API
Batasi layanan API hanya dari IP server dashboard. Contoh RouterOS v7 (ubah alamat sesuai jaringan):
```routeros
/ip service set api disabled=no port=8728 address=192.168.200.100/32
/user group add name=dashboard-api policy=read,api
/user add name=dashboard-api group=dashboard-api password=GANTI_PASSWORD_KUAT
```
Jangan membuka API ke internet publik. Gunakan VPN/firewall allowlist untuk akses lintas jaringan.

## GenieACS dan Telegram
- Isi URL NBI per router, misalnya `http://192.168.200.100:7557`.
- Isi Bot Token dan Chat ID di Pengaturan → Notifikasi Telegram. Token disimpan di `data/config.json`; lindungi file tersebut.
- Pemeriksaan notifikasi berjalan ketika dashboard aktif di browser. Monitoring 24/7 saat browser ditutup memerlukan cron/worker server.
- Pastikan server dapat mengakses Telegram API melalui HTTPS.

## Catatan keamanan dan operasional
- Dashboard ini menggunakan penyimpanan JSON, bukan MySQL.
- Versi ini tidak menyediakan login dashboard/CSRF protection bawaan; gunakan hanya pada LAN/VPN tepercaya dan batasi akses web.
- RX power bergantung pada parameter yang dikirim perangkat ke GenieACS. Jika username atau SN tidak tersedia, pesan akan menampilkan `Tidak terdeteksi`.
- WAN dipantau berdasarkan status interface yang dipilih di menu Load Balance, bukan tes ping ke internet.
- Uji notifikasi Telegram dan koneksi perangkat setelah instalasi.
