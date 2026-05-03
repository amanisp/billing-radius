<?php

namespace App\Services;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Log;

class WireguardService
{
    protected string $interface;
    protected string $serverPublicKey;
    protected string $serverEndpoint;

    public function __construct()
    {
        // Mengambil data dari config/services.php. 
        // Parameter kedua adalah fallback value jika env/config kosong.
        $this->interface = config('services.wg.interface', 'wg0');
        $this->serverPublicKey = config('services.wg.public_key', 'pqyu56PihnG4Jy4M/2dUocrEJyQc+8gZ2mSZ8q4a+kk=');
        $this->serverEndpoint = config('services.wg.endpoint', '103.203.233.235:51820');
    }

    /**
     * Membuat peer WireGuard baru (Add WG).
     * 
     * @param string $ipAddress IP yang dialokasikan (contoh: '172.31.18.2')
     * @return array
     */
    /**
     * Membuat peer WireGuard baru menggunakan Public Key dari Client.
     */
    public function createPeer($ipAddress, $clientPublicKey)
    {
        try {
            // 1. Daftarkan Peer ke Interface Server secara live
            // Menggunakan tanda kutip ('') pada public key untuk menghindari error karakter khusus (seperti +, /, atau =) di Shell Ubuntu
            $addPeerCmd = "sudo wg set {$this->interface} peer '{$clientPublicKey}' allowed-ips {$ipAddress}/32";
            $addProcess = Process::fromShellCommandline($addPeerCmd);
            $addProcess->mustRun();

            // 2. Simpan state saat ini ke file wg0.conf agar permanen saat server reboot
            $saveProcess = Process::fromShellCommandline("sudo wg-quick save {$this->interface}");
            $saveProcess->mustRun();

            // 3. Kembalikan response sukses
            return [
                'status'      => 'success',
                'public_key'  => $clientPublicKey,
                'ip_address'  => $ipAddress,
                'config'      => 'Konfigurasi dilakukan secara manual di MikroTik.',
            ];
        } catch (ProcessFailedException $e) {
            Log::error("Gagal membuat WireGuard Peer: " . $e->getMessage());
            return [
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengeksekusi perintah WireGuard.',
                'error'   => $e->getMessage()
            ];
        }
    }

    /**
     * Menghapus peer WireGuard dari server (Remove WG).
     * 
     * @param string $clientPublicKey Public Key milik client yang ingin dihapus
     * @return bool
     */
    public function removePeer($clientPublicKey)
    {
        try {
            // 1. Hapus peer dari interface live
            $removeCmd = "sudo wg set {$this->interface} peer {$clientPublicKey} remove";
            $removeProcess = Process::fromShellCommandline($removeCmd);
            $removeProcess->mustRun();

            // 2. Simpan perubahan ke config file agar tidak muncul lagi saat reboot
            $saveProcess = Process::fromShellCommandline("sudo wg-quick save {$this->interface}");
            $saveProcess->mustRun();

            return true;
        } catch (ProcessFailedException $e) {
            Log::error("Gagal menghapus WireGuard Peer: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Template konfigurasi untuk sisi Client.
     */
    protected function generateClientConfig($clientPrivateKey, $ipAddress)
    {
        // Menggunakan subnet /23 pada Address client sesuai dengan range pool server
        return <<<EOT
[Interface]
PrivateKey = {$clientPrivateKey}
Address = {$ipAddress}/23
DNS = 8.8.8.8, 1.1.1.1

[Peer]
PublicKey = {$this->serverPublicKey}
Endpoint = {$this->serverEndpoint}
AllowedIPs = 0.0.0.0/0
PersistentKeepalive = 25
EOT;
    }
}
