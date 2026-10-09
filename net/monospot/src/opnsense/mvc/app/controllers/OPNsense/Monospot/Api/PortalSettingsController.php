<?php

/**
 * Monospot portal uygulama ayarları (settings.inc / hotspot.ini) — okuma/yazma.
 *
 * Yazma configd köprüsüne (portal_settings.php) devredilir; dosyalar portal
 * uygulamasının dizininde olduğu için sahiplik korunur ve yedek alınır.
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class PortalSettingsController extends ApiControllerBase
{
    private function run($command, $argument = '')
    {
        $packed = $argument === '' ? $command : $command . ':' . $argument;
        $response = trim((string)(new Backend())->configdpRun('monospot portal_settings', [$packed]));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => 'failed', 'error' => $response];
    }

    /** Portal ayarlarını form alanlarına döker. */
    public function getAction()
    {
        $result = $this->run('get');
        if (($result['status'] ?? '') !== 'ok') {
            return ['portal' => [], 'error' => $result['error'] ?? 'ayarlar okunamadı'];
        }
        return ['portal' => $result['portal']];
    }

    /** Form değerlerini settings.inc/hotspot.ini dosyalarına yazar. */
    public function setAction()
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'error' => 'POST gerekli'];
        }
        $portal = $this->request->getPost('portal');
        if (!is_array($portal)) {
            return ['status' => 'failed', 'error' => 'form verisi okunamadı'];
        }
        $result = $this->run('set', base64_encode(json_encode($portal, JSON_UNESCAPED_UNICODE)));
        if (($result['status'] ?? '') !== 'ok') {
            return ['validations' => ['portal.session_timeout' => $result['error'] ?? 'kaydedilemedi']];
        }
        return ['result' => 'saved', 'changed' => $result['changed'] ?? []];
    }
}
