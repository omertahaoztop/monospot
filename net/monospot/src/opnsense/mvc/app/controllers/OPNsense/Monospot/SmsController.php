<?php

/**
 * Monospot > SMS Raporu (salt-okunur liste).
 *
 * URL: /ui/monospot/sms — veri Api/ReportsController::sms.
 */

namespace OPNsense\Monospot;

class SmsController extends \OPNsense\Base\IndexController
{
    public function indexAction()
    {
        $this->view->formGridSms = $this->getFormGrid('reportSms');
        $this->view->pick('OPNsense/Monospot/sms');
    }
}
