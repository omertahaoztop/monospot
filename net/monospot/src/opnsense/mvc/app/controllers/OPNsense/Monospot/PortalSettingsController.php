<?php

/**
 * Monospot > Portal > Uygulama Ayarları sayfası (settings.inc/hotspot.ini).
 *
 * URL: /ui/monospot/portal_settings — veri Api/PortalSettingsController.
 */

namespace OPNsense\Monospot;

class PortalSettingsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->portalSettingsForm = $this->getForm('portal_settings');
        $this->view->pick('OPNsense/Monospot/portal_settings');
    }
}
