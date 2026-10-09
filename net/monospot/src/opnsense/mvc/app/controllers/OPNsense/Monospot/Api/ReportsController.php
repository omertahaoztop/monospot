<?php

/**
 * Monospot raporları (salt-okunur): SMS gönderim kayıtları ve KVKK izin kayıtları.
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class ReportsController extends ApiControllerBase
{
    private function search($command)
    {
        $rowCount = $this->request->get('rowCount', 'int', 50);
        $current = max(1, $this->request->get('current', 'int', 1));
        $limit = $rowCount <= 0 ? 500 : min(500, $rowCount);

        $packed = $command . ':' . base64_encode(json_encode([
            'search' => (string)$this->request->get('searchPhrase', 'string', ''),
            'limit' => $limit,
            'offset' => ($current - 1) * $limit,
        ], JSON_UNESCAPED_UNICODE));

        $response = trim((string)(new Backend())->configdpRun('monospot report_admin', [$packed]));
        $decoded = json_decode($response, true);
        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'ok') {
            return [
                'rows' => [],
                'total' => 0,
                'rowCount' => $limit,
                'current' => $current,
                'error' => $decoded['error'] ?? $response,
            ];
        }
        return $decoded;
    }

    /** SMS gönderim kayıtları. */
    public function smsAction()
    {
        return $this->search('sms');
    }

    /** KVKK izin kayıtları. */
    public function permissionAction()
    {
        return $this->search('permission');
    }
}
