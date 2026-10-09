<?php

/**
 * Monospot servis denetimi (status/start/stop/restart/reconfigure) ve
 * zone şablon şiminin kurulması.
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
}
