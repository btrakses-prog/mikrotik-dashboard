<?php
declare(strict_types=1);

// Compatibility helper for PHP 7.4 (str_starts_with is native from PHP 8.0).
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool {
        if ($needle === '') return true;
        return strpos($haystack, $needle) === 0;
    }
}

class RouterOSClient {
    private $socket = null;
    private int $timeout;

    public function __construct(int $timeout=5) { $this->timeout = $timeout; }

    private function encodeLength(int $length): string {
        if ($length < 0x80) return chr($length);
        if ($length < 0x4000) return chr(($length >> 8) | 0x80) . chr($length & 0xFF);
        if ($length < 0x200000) return chr(($length >> 16) | 0xC0) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        if ($length < 0x10000000) return chr(($length >> 24) | 0xE0) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        return "\xF0" . pack('N', $length);
    }
    private function readLength(): int {
        $c = ord($this->readExact(1));
        if (($c & 0x80) === 0) return $c;
        if (($c & 0xC0) === 0x80) return (($c & ~0xC0) << 8) + ord($this->readExact(1));
        if (($c & 0xE0) === 0xC0) return (($c & ~0xE0) << 16) + (ord($this->readExact(1)) << 8) + ord($this->readExact(1));
        if (($c & 0xF0) === 0xE0) return (($c & ~0xF0) << 24) + (ord($this->readExact(1)) << 16) + (ord($this->readExact(1)) << 8) + ord($this->readExact(1));
        if ($c === 0xF0) return unpack('N', $this->readExact(4))[1];
        throw new RuntimeException('Invalid API response length');
    }
    private function readExact(int $length): string {
        $data = '';
        while (strlen($data) < $length) {
            $part = fread($this->socket, $length - strlen($data));
            if ($part === false || $part === '') throw new RuntimeException('Koneksi API terputus');
            $data .= $part;
        }
        return $data;
    }
    private function writeWord(string $word): void {
        fwrite($this->socket, $this->encodeLength(strlen($word)) . $word);
    }
    private function writeSentence(array $words): void {
        foreach ($words as $word) $this->writeWord((string)$word);
        $this->writeWord('');
    }
    private function readSentence(): array {
        $words = [];
        while (true) {
            $length = $this->readLength();
            if ($length === 0) break;
            $words[] = $this->readExact($length);
        }
        return $words;
    }
    public function connect(array $router): void {
        $host = trim((string)($router['host'] ?? ''));
        $port = (int)($router['port'] ?? 8728);
        $tls = !empty($router['tls']);
        if ($host === '' || $port < 1 || $port > 65535) throw new RuntimeException('Host atau port API tidak valid');
        $scheme = $tls ? 'ssl://' : 'tcp://';
        $context = stream_context_create(['ssl'=>['verify_peer'=>false,'verify_peer_name'=>false,'allow_self_signed'=>true]]);
        $this->socket = @stream_socket_client($scheme.$host.':'.$port, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if (!$this->socket) throw new RuntimeException("Gagal konek ke {$host}:{$port} ({$errstr})");
        stream_set_timeout($this->socket, $this->timeout);
        $this->writeSentence(['/login', '=name='.(string)$router['username'], '=password='.(string)$router['password']]);
        $reply = $this->readSentence();
        if (($reply[0] ?? '') === '!trap' || ($reply[0] ?? '') === '!fatal') {
            $message = 'Login API gagal';
            foreach ($reply as $w) if (str_starts_with($w, '=message=')) $message = substr($w, 9);
            throw new RuntimeException($message);
        }
    }
    public function command(string $command, array $params=[]): array {
        if (!$this->socket) throw new RuntimeException('Belum terhubung');
        $words = [$command];
        foreach ($params as $key=>$value) $words[] = '='.$key.'='.$value;
        $this->writeSentence($words);
        $rows = [];
        while (true) {
            $sentence = $this->readSentence();
            $type = $sentence[0] ?? '';
            if ($type === '!re') {
                $row = [];
                foreach (array_slice($sentence,1) as $word) {
                    if (str_starts_with($word, '=')) {
                        $pair = explode('=', substr($word,1), 2);
                        if (count($pair) === 2) $row[$pair[0]] = $pair[1];
                    }
                }
                $rows[] = $row;
            } elseif ($type === '!trap' || $type === '!fatal') {
                $message = 'Perintah API gagal';
                foreach ($sentence as $word) if (str_starts_with($word, '=message=')) $message = substr($word,9);
                throw new RuntimeException($message);
            } elseif ($type === '!done') break;
        }
        return $rows;
    }
    public function close(): void { if (is_resource($this->socket)) fclose($this->socket); $this->socket = null; }
    public function __destruct() { $this->close(); }
}
