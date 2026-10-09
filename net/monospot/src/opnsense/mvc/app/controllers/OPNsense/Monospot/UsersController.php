<?php

/**
 * Monospot > Kullanıcılar sayfası (hotspot veritabanı CRUD).
 *
 * URL: /ui/monospot/users — veri Api/UsersController (configd user_admin.php).
 */

namespace OPNsense\Monospot;

class UsersController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->formDialogEditUser = $this->getForm('dialogUser');
        $this->view->formGridUser = $this->getFormGrid('dialogUser');
        $this->view->pick('OPNsense/Monospot/users');
    }
}
