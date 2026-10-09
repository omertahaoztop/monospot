<?php

/**
 * Zone şablon şimini core captive portal modeline yazar ve hedef zone'a bağlar.
 * configd tarafından root olarak çalıştırılır (kullanıcı girişi yoktur).
 *
 * Kullanım: save_zone_template.php --name=<ad> --zip=<dosya> [--zoneid=<id>]
 */

require_once('config.inc');
require_once('util.inc');

$options = getopt('', ['name:', 'zip:', 'zoneid:']);
if (empty($options['name']) || empty($options['zip']) || !is_readable($options['zip'])) {
    fwrite(STDERR, "kullanım: save_zone_template.php --name=<ad> --zip=<dosya> [--zoneid=<id>]\n");
    exit(1);
}

$model = new \OPNsense\CaptivePortal\CaptivePortal();
$template = $model->getTemplateByName($options['name']);
$template->content = base64_encode(file_get_contents($options['zip']));

if (isset($options['zoneid'])) {
    $zone = $model->getByZoneID((string)$options['zoneid']);
    if ($zone === null) {
        fwrite(STDERR, "zone bulunamadı: {$options['zoneid']}\n");
        exit(1);
    }
    $zone->template = $template->getAttributes()['uuid'];
}

$errorMessages = [];
foreach ($model->performValidation() as $field => $validation) {
    $errorMessages[] = (string)$validation;
}
if (!empty($errorMessages)) {
    fwrite(STDERR, "doğrulama hatası: " . implode('; ', $errorMessages) . "\n");
    exit(1);
}

$model->serializeToConfig();
\OPNsense\Core\Config::getInstance()->save();

echo json_encode(['status' => 'ok', 'name' => (string)$template->name, 'zoneid' => $options['zoneid'] ?? null]) . "\n";
