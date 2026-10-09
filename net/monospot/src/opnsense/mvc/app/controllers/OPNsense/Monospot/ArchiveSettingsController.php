<?php

/**
 * Monospot > 5651 Günlükleri > Ayarlar.
 *
 * URL: /ui/monospot/archive_settings — model ayarları (collect5651) + servis
 * yeniden yapılandırması (cron girdisi config'ten üretilir).
 */

namespace OPNsense\Monospot;

class ArchiveSettingsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->archiveForm = $this->getForm('archive');
        $this->view->pick('OPNsense/Monospot/archive_settings');
    }
}
