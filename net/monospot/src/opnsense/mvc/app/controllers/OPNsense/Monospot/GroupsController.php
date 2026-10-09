<?php

/**
 * Monospot > Gruplar sayfası (MAC bazlı grup tanımları).
 *
 * URL: /ui/monospot/groups — veri Api/GroupsController → configd group_admin.php.
 */

namespace OPNsense\Monospot;

class GroupsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->formDialogEditGroup = $this->getForm('dialogGroup');
        $this->view->formGridGroup = $this->getFormGrid('dialogGroup');
        $this->view->pick('OPNsense/Monospot/groups');
    }
}
