<?php

/**
 * Monospot > İzin Raporu (salt-okunur KVKK kayıtları).
 *
 * URL: /ui/monospot/permissions — veri Api/ReportsController::permission.
 */

namespace OPNsense\Monospot;

class PermissionsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->formGridPermission = $this->getFormGrid('reportPermission');
        $this->view->pick('OPNsense/Monospot/permissions');
    }
}
