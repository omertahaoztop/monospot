<?php

/**
 * Monospot > Portal sayfası: portal durumu, zone şablonu kurulumu, portal bağlantıları.
 *
 * URL: /ui/monospot/portal — veri Api/ServiceController (info + installzonetemplate).
 */

namespace OPNsense\Monospot;

class PortalController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->portalStatusForm = $this->getForm('portal_status');
        $this->view->pick('OPNsense/Monospot/portal');
    }
}
