<?php

/**
 * Monospot > Durum sayfası.
 *
 * URL: /ui/monospot/status — veri Api/ServiceController::infoAction
 * (configctl monospot info → status.php) üzerinden gelir.
 */

namespace OPNsense\Monospot;

class StatusController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/Monospot/status');
    }
}
