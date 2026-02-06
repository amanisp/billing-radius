<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use Illuminate\Support\Facades\Log;

class RadiusDisconnectService
{
    /**
     * Kirim Disconnect-Request via SSH ke server FreeRADIUS, lalu ke NAS.
     * Semua detail SSH diambil dari environment variables (.env).
     *
     * @param string $username Username atau MAC address pengguna.
     * @param string $nasIp IP address NAS (misalnya, router hotspot).
     * @param int $nasPort Port RADIUS (default 1812 untuk Disconnect).
     * @param string $sharedSecret Shared secret NAS.
     * @return bool True jika berhasil, false jika gagal.
     */
    public static function sendDisconnectViaSsh(
        $username,
        $nasIp,
        $nasPort = 1812,
        $sharedSecret
    ) {
        // Ambil detail SSH dari env
        $freeradiusHost = env('FREERADIUS_HOST', 'localhost');  // Default localhost jika tidak ada
        $freeradiusPort = env('FREERADIUS_SSH_PORT', 22);
        $freeradiusUser = env('FREERADIUS_SSH_USER', 'root');
        $freeradiusPassword = env('FREERADIUS_SSH_PASSWORD');
        $freeradiusKeyFile = env('FREERADIUS_SSH_KEY_FILE');  // Path ke private key (certificate Anda)

        try {
            // Connect ke server FreeRADIUS via SSH
            $ssh = new SSH2($freeradiusHost, $freeradiusPort);

            // Autentikasi SSH: Prioritaskan key file (certificate), fallback ke password
            $loginSuccess = false;
            if ($freeradiusKeyFile && file_exists($freeradiusKeyFile)) {
                // Gunakan private key (certificate yang Anda siapkan)
                $key = new \phpseclib3\Crypt\RSA\PrivateKey(file_get_contents($freeradiusKeyFile));
                $loginSuccess = $ssh->login($freeradiusUser, $key);
                if (!$loginSuccess) {
                    Log::error("SSH login failed for $freeradiusUser@$freeradiusHost using key file: $freeradiusKeyFile");
                }
            } elseif ($freeradiusPassword) {
                // Fallback ke password jika key tidak ada atau gagal
                $loginSuccess = $ssh->login($freeradiusUser, $freeradiusPassword);
                if (!$loginSuccess) {
                    Log::error("SSH login failed for $freeradiusUser@$freeradiusHost using password");
                }
            } else {
                Log::error("No SSH authentication method provided (key file or password) for $freeradiusUser@$freeradiusHost");
                return false;
            }

            if (!$loginSuccess) {
                return false;
            }

            // Buat command radclient untuk Disconnect-Request
            $command = "echo 'User-Name=$username' | radclient -x $nasIp:$nasPort disconnect $sharedSecret";

            // Jalankan command via SSH
            $output = $ssh->exec($command);

            // Log output untuk debugging
            Log::info("SSH RADIUS Disconnect to NAS $nasIp for $username via $freeradiusHost: $output");

            // Disconnect SSH
            $ssh->disconnect();

            // Cek apakah output menunjukkan keberhasilan (misalnya, "Disconnect-ACK")
            return strpos($output, 'Disconnect-ACK') !== false;
        } catch (\Exception $e) {
            Log::error("Error sending Disconnect via SSH for $username: " . $e->getMessage());
            return false;
        }
    }
}
