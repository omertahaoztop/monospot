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
}
