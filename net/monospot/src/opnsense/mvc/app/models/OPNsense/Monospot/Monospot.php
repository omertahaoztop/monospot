<?php

/**
 * Monospot model sınıfı.
 */

namespace OPNsense\Monospot;

use OPNsense\Base\BaseModel;

class Monospot extends BaseModel
{
    /**
     * Portal servisinin kullanacağı zone numarası (tanımsızsa 0).
     */
    public function getZoneId()
    {
        $zoneid = (string)$this->general->zoneid;
        return $zoneid === '' ? '0' : $zoneid;
    }

    /**
     * Portal portu (tanımsızsa 8100).
     */
    public function getPortalPort()
    {
        $port = (string)$this->general->portal_port;
        return $port === '' ? '8100' : $port;
    }
}
