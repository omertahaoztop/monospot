<?php

/**
 * Monospot grup bazlı portal ayarları (settings_group<id>.inc).
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class GroupSettingsController extends ApiControllerBase
{
    private function run($command, $groupid = '', $argument = '')
    {
        $packed = $command;
        if ($groupid !== '') {
            $packed .= ':' . $groupid;
            if ($argument !== '') {
                $packed .= ':' . $argument;
            }
        }
        $response = trim((string)(new Backend())->configdpRun('monospot group_settings_admin', [$packed]));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => 'failed', 'error' => $response];
    }

    /** Grup listesi + grup bazlı düzenlenebilen bölümler. */
    public function groupsAction()
    {
        $result = $this->run('groups');
        if (($result['status'] ?? '') !== 'ok') {
            return ['groups' => [], 'sections' => [], 'error' => $result['error'] ?? 'gruplar okunamadı'];
        }
        return ['groups' => $result['groups'], 'sections' => $result['sections']];
    }

    /** Seçili grubun ayarları (global değerlerle birleşik). */
    public function getAction($groupid = null)
    {
        if ((string)$groupid === '') {
            return ['settings' => [], 'error' => 'grup seçilmedi'];
        }
        $result = $this->run('get', (string)$groupid);
        if (($result['status'] ?? '') !== 'ok') {
            return ['settings' => [], 'error' => $result['error'] ?? 'ayarlar okunamadı'];
        }
        return ['group' => $result['group'], 'settings' => $result['settings']];
    }

    /** Grubun ayarlarını yazar. */
    public function setAction($groupid = null)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'error' => 'POST gerekli'];
        }
        $settings = $this->request->getPost('settings');
        if (!is_array($settings)) {
            return ['status' => 'failed', 'error' => 'form verisi okunamadı'];
        }
        $result = $this->run('set', (string)$groupid, base64_encode(json_encode($settings, JSON_UNESCAPED_UNICODE)));
        if (($result['status'] ?? '') !== 'ok') {
            return ['validations' => ['settings.session_timeout' => $result['error'] ?? 'kaydedilemedi']];
        }
        return ['result' => 'saved', 'changed' => $result['changed'] ?? []];
    }
}
