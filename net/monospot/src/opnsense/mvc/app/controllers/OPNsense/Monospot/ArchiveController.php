<?php

/**
 * Monospot > 5651 Günlükleri sayfası: arşiv özeti, elle toplama, tarayıcı bağlantısı.
 *
 * URL: /ui/monospot/archive — veri Api/ServiceController (info + collect5651).
 */

namespace OPNsense\Monospot;

class ArchiveController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/Monospot/archive');
    }
}
