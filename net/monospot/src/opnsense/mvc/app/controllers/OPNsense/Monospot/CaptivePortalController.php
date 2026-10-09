<?php

/**
 * Monospot > Captive Portal sayfası (core zone ayarlarının yüzü).
 *
 * URL: /ui/monospot/captive_portal (çok kelimeli controller adı alt çizgiyle).
 * Veri core modelde tutulur; okuma/yazma Api/CaptivePortalController üzerinden.
 */

namespace OPNsense\Monospot;

class CaptivePortalController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->captivePortalForm = $this->getForm('captiveportal');
        $this->view->pick('OPNsense/Monospot/captiveportal');
    }
}
