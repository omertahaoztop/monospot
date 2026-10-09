<?php

/**
 * Monospot > Oturumlar sayfası (yetkili istemciler + oturum kesme).
 *
 * URL: /ui/monospot/sessions — veri Api/SessionsController.
 */

namespace OPNsense\Monospot;

class SessionsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/Monospot/sessions');
    }
}
