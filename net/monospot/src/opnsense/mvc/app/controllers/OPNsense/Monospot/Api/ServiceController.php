<?php

/**
 * Monospot servis denetimi (status/start/stop/restart/reconfigure), zone şablon
 * şiminin kurulması ve 5651 günlük arşivinin elle çalıştırılması.
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiMutableServiceControllerBase;
use OPNsense\Core\Backend;

class ServiceController extends ApiMutableServiceControllerBase
{
    protected static $internalServiceClass = '\OPNsense\Monospot\Monospot';
    protected static $internalServiceTemplate = 'OPNsense/Monospot';
    protected static $internalServiceEnabled = 'general.enabled';
    protected static $internalServiceName = 'monospot';

    /**
     * Zone şablon şimini kurar: zone'un portal sayfası istemciyi Monospot
     * portalına yönlendirir. Kurulum sonrası core captive portal yeniden başlar.
     */
    public function installzonetemplateAction()
    {
        $result = ['status' => 'failed'];
        if (!$this->request->isPost()) {
            return $result;
        }
        $mdl = new \OPNsense\Monospot\Monospot();
        $response = (new Backend())->configdpRun('monospot install_zonetemplate', [
            $mdl->getZoneId(),
            $mdl->getPortalPort(),
            'Monospot Portal',
        ]);
        $decoded = json_decode(trim((string)$response), true);
        if (is_array($decoded) && isset($decoded['status']) && $decoded['status'] === 'ok') {
            $result = ['status' => 'ok'];
        } elseif (is_array($decoded)) {
            $result = $decoded;
        } else {
            $result['error'] = trim((string)$response);
        }
        return $result;
    }

    /**
     * Durum özeti (Services > Monospot sayfasındaki Durum paneli).
     */
    public function infoAction()
    {
        $response = trim((string)(new Backend())->configdpRun('monospot info'));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['error' => $response];
    }

    /**
     * 5651 arşivini şimdi toplar (ayarlar sayfasındaki düğme).
     */
    public function collect5651Action()
    {
        $result = ['status' => 'failed'];
        if (!$this->request->isPost()) {
            return $result;
        }
        $response = trim((string)(new Backend())->configdpRun('monospot collect5651'));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => 'failed', 'error' => $response];
    }

    /**
     * Ayarlar kaydedilirken 5651 cron işi config'ten üretilir
     * (plugins_cron → root crontab); crontab'ı yeniden kurmak için yeniden başlatılır.
     */
    public function reconfigureAction()
    {
        $result = parent::reconfigureAction();
        if ($this->request->isPost()) {
            (new Backend())->configdRun('cron restart');
        }
        return $result;
    }
}

