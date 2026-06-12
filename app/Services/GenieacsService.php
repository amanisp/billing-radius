<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GenieAcsService
{
    protected string $baseUrl;
    protected string $username;
    protected string $password;

    public function __construct()
    {
        // Default mengambil dari .env
        $this->baseUrl = config('services.genieacs.url');
        $this->username = config('services.genieacs.username');
        $this->password = config('services.genieacs.password');
    }



    /**
     * Menimpa konfigurasi default dengan konfigurasi Mandiri
     * Ditambahkan parameter $port
     */
    public function setCustomConfig(?string $url, ?string $port, ?string $username, ?string $password)
    {
        if (!empty($url)) {
            // Hapus slash di akhir URL jika user tidak sengaja mengetiknya
            $cleanUrl = rtrim($url, '/');

            // Gabungkan port jika datanya ada
            if (!empty($port)) {
                $cleanUrl .= ':' . $port;
            }

            $this->baseUrl = $cleanUrl;
        }

        if (!empty($username)) {
            $this->username = $username;
        }

        if (!empty($password)) {
            $this->password = $password;
        }

        return $this;
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
            ->withoutVerifying() // Tambahkan baris ini untuk bypass SSL
            ->withBasicAuth($this->username, $this->password)
            ->acceptJson();
    }

    public function getDeviceById(string $deviceId)
    {
        return $this->client()
            ->get("/devices", [
                'query' => json_encode(['_id' => $deviceId])
            ])
            ->json();
    }

    public function getAllDevices()
    {
        return $this->client()->get('/devices')->json();
    }

    /**
     * 1. Search device by PPPoE username
     */
    public function searchByPppoe(string $pppoe)
    {
        return $this->client()->get('/devices', [
            'query' => json_encode([
                'VirtualParameters.pppoeUsername' => $pppoe
            ])
        ])->json();
    }

    /**
     * 2. Search device by Serial Number
     */
    public function searchBySn(string $sn)
    {
        return $this->client()->get('/devices', [
            'query' => json_encode([
                '_deviceId._SerialNumber' => $sn
            ])
        ])->json();
    }

    /**
     * 3. Add tag group_id ke device
     */
    public function addGroupTag(string $deviceId, string $groupId)
    {
        $tag = $groupId;

        $safeDeviceId = rawurlencode($deviceId);

        return $this->client()
            ->post("/devices/{$safeDeviceId}/tags/{$tag}")
            ->json();
    }

    /**
     * 5. Remove tag group_id dari device
     */
    public function removeGroupTag(string $deviceId, string $groupId)
    {
        $tag = $groupId;
        $safeDeviceId = rawurlencode($deviceId);

        // API GenieACS menggunakan HTTP DELETE ke endpoint tags
        return $this->client()
            ->delete("/devices/{$safeDeviceId}/tags/{$tag}")
            ->json();
    }

    /**
     * 4. Get device by group/tag
     */
    public function getByGroup(string $groupId)
    {
        return $this->client()->get('/devices', [
            'query' => json_encode([
                '_tags' => $groupId
            ])
        ])->json();
    }
}
