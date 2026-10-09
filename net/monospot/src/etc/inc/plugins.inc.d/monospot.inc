<?php

/*
 * Copyright (C) 2026 Monobilisim
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 *    this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
 * INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
 * AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
 * OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * Monospot servis ve güvenlik duvarı kayıtları.
 * Core captive portal ile paralel çalışır; core dosyalarına dokunulmaz.
 */

function monospot_general()
{
    global $config;

    return isset($config['OPNsense']['Monospot']['general']) ?
        $config['OPNsense']['Monospot']['general'] : [];
}

function monospot_zone_interfaces($zoneid)
{
    // zone çözümlenemezse boş döner: çağıran taraf fail-closed davranmalı
    $result = [];
    if (!class_exists('\OPNsense\CaptivePortal\CaptivePortal')) {
        return $result;
    }
    $cp = new \OPNsense\CaptivePortal\CaptivePortal();
    foreach ($cp->zones->zone->iterateItems() as $zone) {
        if ((string)$zone->zoneid === (string)$zoneid) {
            $result = $zone->interfaces->getValues();
            break;
        }
    }
    return $result;
}

function monospot_services()
{
    $services = [];
    $general = monospot_general();
    if (!empty($general['enabled']) && (string)$general['enabled'] === '1') {
        $services[] = [
            'description' => gettext('Monospot Hotspot'),
            'configd' => [
                'restart' => ['monospot restart'],
                'start' => ['monospot start'],
                'stop' => ['monospot stop'],
            ],
            'name' => 'monospot',
        ];
    }
    return $services;
}

/**
 * Portal portuna, yalnızca hizmet verdiğimiz zone arayüzlerinden geçiş izni.
 * Admin yüzeyi core WebGUI'de yaşar; buraya kural yazılmaz.
 */
function monospot_firewall($fw)
{
    $general = monospot_general();
    if (empty($general['enabled']) || (string)$general['enabled'] !== '1') {
        return;
    }
    $zoneid = isset($general['zoneid']) ? $general['zoneid'] : '0';
    $portal_port = !empty($general['portal_port']) ? $general['portal_port'] : '8100';

    $interfaces = monospot_zone_interfaces($zoneid);
    if (empty($interfaces)) {
        return;
    }
    foreach ($interfaces as $interface) {
        /*
         * Yetkili istemci portala giremez: zone tablosundakiler (açık oturumu
         * olanlar) portal portuna erişemez. Kural, aşağıdaki pass kuralından ve
         * core'un "Default Captive Portal block" kuralından önce (quick) gelir.
         */
        $fw->registerFilterRule(1, [
            'type' => 'block',
            'interface' => $interface,
            'protocol' => 'tcp',
            'direction' => 'in',
            'from' => "<__captiveportal_zone_{$zoneid}>",
            'to' => '(self)',
            'to_port' => $portal_port,
            'descr' => sprintf('Monospot portal: yetkili istemciye kapalı (zone %s)', $zoneid),
            'log' => false,
            '#ref' => 'ui/monospot/index',
        ]);
        $fw->registerFilterRule(2, [
            'type' => 'pass',
            'interface' => $interface,
            'protocol' => 'tcp',
            'direction' => 'in',
            'from' => "<__captiveportal_zone_{$zoneid}>",
            'from_not' => true,
            'to' => '(self)',
            'to_port' => $portal_port,
            'descr' => sprintf('Monospot portal (zone %s)', $zoneid),
            'log' => !isset($GLOBALS['config']['syslog']['nologdefaultpass']),
            '#ref' => 'ui/monospot/index',
        ]);
    }
}
