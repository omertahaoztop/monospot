<?php

/**
 * Monospot > Dil sayfası (portal metinleri: app/lang/<kod>.inc).
 *
 * URL: /ui/monospot/language
 */

namespace OPNsense\Monospot;

class LanguageController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->pick('OPNsense/Monospot/language');
    }
}
