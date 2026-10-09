#!/usr/local/bin/php
<?php

/**
 * Monospot'tan core captive portal zone ayarlarını yazar.
 *
 * configd tarafından root olarak çalıştırılır. Alanlar core modelde
 * (OPNsense/CaptivePortal) tutulur; bu betik yalnız köprüdür, veriyi
 * çoğaltmaz. Yazma sonrası core CP şablonu yenilenir ve servis yeniden başlar.
 *
 *   set_captiveportal.php --interfaces=lan --idletimeout=0 --hardtimeout=1440 \
 *       --concurrentlogins=1 --roaming=1 --enabled=1 --description="..."
 */

require_once('config.inc');
require_once('util.inc');

function fail($message)
{
    printf("%s\n", json_encode(['status' => 'failed', 'error' => $message], JSON_UNESCAPED_UNICODE));
    exit(0);
}

$options = getopt('', [
    'interfaces:', 'idletimeout:', 'hardtimeout:', 'concurrentlogins:',
    'roaming:', 'enabled:', 'description:',
]);

$mdl = new \OPNsense\Monospot\Monospot();
$zoneid = $mdl->getZoneId();

$cp = new \OPNsense\CaptivePortal\CaptivePortal();
$zone = $cp->getByZoneID($zoneid);
if ($zone === null) {
    fail('captive portal zone bulunamadı: ' . $zoneid);
}

$zone->interfaces = implode(',', array_filter(array_map('trim', explode(',', $options['interfaces'] ?? ''))));
$zone->idletimeout = (string)(int)($options['idletimeout'] ?? 0);
$zone->hardtimeout = (string)(int)($options['hardtimeout'] ?? 0);
$zone->concurrentlogins = (string)(int)($options['concurrentlogins'] ?? 0) === '1' ? '1' : '0';
$zone->roaming = (string)(int)($options['roaming'] ?? 0) === '1' ? '1' : '0';
$zone->enabled = (string)(int)($options['enabled'] ?? 0) === '1' ? '1' : '0';
$zone->description = (string)($options['description'] ?? '');

$errors = [];
foreach ($cp->performValidation() as $field => $validation) {
    $errors[] = $field . ': ' . (string)$validation;
}
if (!empty($errors)) {
    fail(implode('; ', $errors));
}

$cp->serializeToConfig();
\OPNsense\Core\Config::getInstance()->save();

shell_exec('/usr/local/sbin/configctl template reload OPNsense/Captiveportal > /dev/null 2>&1');
shell_exec('/usr/local/sbin/configctl captiveportal restart > /dev/null 2>&1');

printf("%s\n", json_encode([
    'status' => 'ok',
    'zoneid' => $zoneid,
    'interfaces' => (string)$zone->interfaces,
    'idletimeout' => (string)$zone->idletimeout,
    'hardtimeout' => (string)$zone->hardtimeout,
    'concurrentlogins' => (string)$zone->concurrentlogins,
    'roaming' => (string)$zone->roaming,
    'enabled' => (string)$zone->enabled,
    'description' => (string)$zone->description,
], JSON_UNESCAPED_UNICODE));
