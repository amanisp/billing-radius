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
        $this->baseUrl = config('services.genieacs.url');
        $this->username = config('services.genieacs.username');
        $this->password = config('services.genieacs.password');
    }

    protected function client()
    {
        return Http::baseUrl($this->baseUrl)
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
