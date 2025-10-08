<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use phpseclib3\Crypt\PublicKeyLoader;

class SshClient
{
    protected $ssh;
    protected $connected = false;

    public function __construct()
    {
        // Config SSH (bisa kamu buat dari .env juga)
        $host = '157.15.63.97';
        $port = 2185;
        $user = 'amanisp-root';

        // Cara 1: Gunakan Password
        $password = '!Tahun2025';

        $this->ssh = new SSH2($host, $port);
        $this->connected = $this->ssh->login($user, $password);

        // Cara 2: Gunakan SSH Key
        // $key = PublicKeyLoader::load(file_get_contents('/path/to/id_rsa'));
        // $this->connected = $this->ssh->login($user, $key);
    }

    public function isConnected()
    {
        return $this->connected;
    }

    public function exec($command)
    {
        if (!$this->connected) {
            throw new \Exception('SSH not connected');
        }

        return $this->ssh->exec($command);
    }
}
