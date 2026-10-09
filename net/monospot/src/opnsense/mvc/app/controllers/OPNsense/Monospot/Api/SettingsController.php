<?php

/**
 * Monospot model ayarları (get/set).
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiMutableModelControllerBase;

class SettingsController extends ApiMutableModelControllerBase
{
    protected static $internalModelName = 'monospot';
    protected static $internalModelClass = '\OPNsense\Monospot\Monospot';
}
