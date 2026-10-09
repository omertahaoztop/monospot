<?php

/**
 * Monospot servis ayarları sayfası.
 */

namespace OPNsense\Monospot;

class IndexController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->generalForm = $this->getForm('settings');
        $this->view->pick('OPNsense/Monospot/index');
    }

    /**
     * Captive Portal zone ayarları (core modelin Monospot yüzü).
     */
    public function captiveportalAction()
    {
        $this->view->captivePortalForm = $this->getForm('captiveportal');
        $this->view->pick('OPNsense/Monospot/captiveportal');
    }
}
