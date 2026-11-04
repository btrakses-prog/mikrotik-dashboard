> Paket kompatibilitas: **PHP 8.0 atau lebih baru**. Persyaratan: PHP 8.0 atau lebih baru.

# MikroTik Shortcut Dashboard

Dashboard PHP + JSON (tanpa MySQL) untuk mengelola hingga 5 MikroTik melalui RouterOS API.

## Fitur
- Ringkasan status router, identitas, board/type, serial number, RouterOS version dan uptime.
- PPPoE online dari `/ppp/active/print`.
- PPPoE offline dari `/ppp/secret/print` dikurangi username yang sedang aktif.
- Pencarian username/IP, filter router, dan tombol remote HTTP ke IP pelanggan.
- Pengaturan router: host, port API, username, password, API-SSL, tes koneksi.
- Upload logo dan banner.
- Shortcut aplikasi yang dapat ditambah, diedit, dan dihapus.
- Penyimpanan konfigurasi JSON, tanpa database.

## Instalasi
1. Ekstrak folder `mikrotik-shortcut-dashboard` ke document root Apache, misalnya `/var/www/html/`.
2. Pastikan PHP 8.0 atau lebih baru aktif dengan ekstensi `fileinfo`, `json`, dan dukungan sockets/streams.
3. Beri hak tulis kepada user Apache pada folder `data` dan `uploads`:
   ```bash
   sudo chown -R www-data:www-data /var/www/html/mikrotik-shortcut-dashboard/data /var/www/html/mikrotik-shortcut-dashboard/uploads
   sudo chmod 750 /var/www/html/mikrotik-shortcut-dashboard/data /var/www/html/mikrotik-shortcut-dashboard/uploads
   ```
4. Buka `http://IP-SERVER/mikrotik-shortcut-dashboard/`.
5. Masuk menu Pengaturan → Router → isi alamat IP, port API, username dan password.

## Konfigurasi MikroTik
Aktifkan API hanya dari IP server dashboard. Contoh RouterOS v7 (sesuaikan IP server):
```routeros
/ip service set api disabled=no port=8728 address=192.168.200.100/32
/user group add name=dashboard-api policy=read,api
/user add name=dashboard-api group=dashboard-api password=GANTI_PASSWORD_KUAT
```
Jika user/group sudah ada, jangan dibuat ulang. Jangan membuka API ke seluruh internet. Untuk koneksi lintas jaringan, gunakan VPN atau API-SSL dan firewall allowlist.

## Catatan penting
- Tombol Remote membuka `http://IP-PPPoE` di tab baru. Pastikan perangkat pelanggan memang menyediakan web management pada HTTP dan routing dari browser/server memungkinkan. Untuk Winbox, gunakan IP tersebut secara manual atau sesuaikan skema remote sesuai lingkungan.
- RouterOS API port default 8728; API-SSL umumnya 8729. Sertifikat TLS self-signed diterima oleh client ini, sehingga untuk produksi gunakan jaringan privat/VPN dan batasi akses.
- Password API disimpan di `data/config.json`; lindungi folder ini dan jangan publikasikan file konfigurasi.
- Versi awal ini belum dilengkapi login dashboard/CSRF protection. Pasang pada LAN/VPN tepercaya, jangan diekspos langsung ke internet.
- Status offline berarti PPP Secret tidak memiliki sesi aktif saat data dibaca; akun disabled juga akan tampil offline.


## Monitoring Load Balance
- Panel traffic Load Balance ditampilkan di bagian paling atas Dashboard.
- Menu **Load Balance** menampilkan panel traffic dan pengaturan interface per router.
- Pilih router, centang interface WAN yang digunakan untuk load balance, lalu klik **Simpan Interface**.
- RX adalah download yang diterima interface; TX adalah upload yang dikirim interface.
- Data diperbarui otomatis setiap 5 detik melalui RouterOS API.
- Pastikan user API memiliki policy `read,api` dan interface yang dipilih benar-benar interface WAN.
