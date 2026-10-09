<?php

/**
 * Monospot > Gruplar > Grup Portal Ayarları sayfası.
 *
 * URL: /ui/monospot/group_settings[?group_id=N] — grup seçilir, o grubun
 * settings_group<id>.inc değerleri düzenlenir (global değerler varsayılan).
 */

namespace OPNsense\Monospot;

class GroupSettingsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->groupSettingsForm = $this->getForm('group_settings');
        $groupId = (string)$this->request->getQuery('group_id', null, '');
        $this->view->selectedGroupId = preg_match('/^\d+$/', $groupId) ? $groupId : '';
        $this->view->pick('OPNsense/Monospot/group_settings');
    }
}
