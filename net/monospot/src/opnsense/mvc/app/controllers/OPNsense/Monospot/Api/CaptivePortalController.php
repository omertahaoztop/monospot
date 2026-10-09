<?php

/**
 * Monospot > Captive Portal: core captive portal zone ayarlarının köprüsü.
 *
 * Veri core modelde (OPNsense/CaptivePortal) tutulur; burada yalnız okuma ve
 * doğrulanmış yazma yapılır. Yazma configd üzerinden root olarak çalışan
 * set_captiveportal.php ile core modele işlenir.
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class CaptivePortalController extends ApiControllerBase
{
    /**
     * Core zone ayarlarını form alanlarına döker.
     */
    public function getAction()
    {
        $zoneid = (new \OPNsense\Monospot\Monospot())->getZoneId();
        $zone = (new \OPNsense\CaptivePortal\CaptivePortal())->getByZoneID($zoneid);
        if ($zone === null) {
            return ['captiveportal' => [], 'error' => sprintf('captive portal zone bulunamadı: %s', $zoneid)];
        }
        return ['captiveportal' => [
            'enabled' => (string)$zone->enabled,
            'interfaces' => $zone->interfaces->getValues(),
            'idletimeout' => (string)$zone->idletimeout,
            'hardtimeout' => (string)$zone->hardtimeout,
            'concurrentlogins' => (string)$zone->concurrentlogins,
            'roaming' => (string)$zone->roaming,
            'description' => (string)$zone->description,
        ]];
    }

    /**
     * Form değerlerini core zone'a yazar.
     */
    public function setAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'error' => 'POST gerekli'];
        }
        $zone = $this->request->getPost('captiveportal');
        if (!is_array($zone)) {
            return ['status' => 'failed', 'error' => 'form verisi okunamadı'];
        }
        $interfaces = $zone['interfaces'] ?? [];
        $response = trim((string)(new Backend())->configdpRun('monospot set_captiveportal', [
            implode(',', is_array($interfaces) ? $interfaces : [$interfaces]),
            (string)($zone['idletimeout'] ?? '0'),
            (string)($zone['hardtimeout'] ?? '0'),
            (string)($zone['concurrentlogins'] ?? '0'),
            (string)($zone['roaming'] ?? '0'),
            (string)($zone['enabled'] ?? '0'),
            (string)($zone['description'] ?? ''),
        ]));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => 'failed', 'error' => $response];
    }
}
