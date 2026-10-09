<?php

/**
 * Monospot dil dosyaları (app/lang/<kod>.inc) — okuma/yazma.
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class LanguageController extends ApiControllerBase
{
    private function run($command, $code = '', $argument = '')
    {
        $packed = $command;
        if ($code !== '') {
            $packed .= ':' . $code;
            if ($argument !== '') {
                $packed .= ':' . $argument;
            }
        }
        $response = trim((string)(new Backend())->configdpRun('monospot lang_admin', [$packed]));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => 'failed', 'error' => $response];
    }

    /** Kullanılabilir dil kodları. */
    public function codesAction()
    {
        $result = $this->run('codes');
        return ($result['status'] ?? '') === 'ok'
            ? ['codes' => $result['codes']]
            : ['codes' => [], 'error' => $result['error'] ?? 'dil listesi okunamadı'];
    }

    /** Bir dilin metinleri. */
    public function getAction($code = null)
    {
        $result = $this->run('get', (string)$code);
        if (($result['status'] ?? '') !== 'ok') {
            return ['keys' => [], 'error' => $result['error'] ?? 'metinler okunamadı'];
        }
        return ['code' => $result['code'], 'written' => $result['written'], 'keys' => $result['keys']];
    }

    /** Metinleri yazar (gövde: {"lang": {anahtar: metin}}). */
    public function setAction($code = null)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'error' => 'POST gerekli'];
        }
        $texts = $this->request->getPost('lang');
        if (!is_array($texts)) {
            return ['status' => 'failed', 'error' => 'form verisi okunamadı'];
        }
        $result = $this->run('set', (string)$code, base64_encode(json_encode($texts, JSON_UNESCAPED_UNICODE)));
        if (($result['status'] ?? '') !== 'ok') {
            return ['validations' => ['lang.text' => $result['error'] ?? 'kaydedilemedi']];
        }
        return ['result' => 'saved', 'code' => $result['code'], 'changed' => $result['changed']];
    }
}
