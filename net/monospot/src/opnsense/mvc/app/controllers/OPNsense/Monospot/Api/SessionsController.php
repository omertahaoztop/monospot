<?php

/**
 * Monospot oturumları (captive portal zone) — listeleme ve oturum kesme.
 *
 * Yazma işlemi core `configctl captiveportal disconnect` çağrısına dayanır
 * (configd köprüsü: session_admin.php).
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class SessionsController extends ApiControllerBase
{
    private function run($command, $argument = '')
    {
        $packed = $argument === '' ? $command : $command . ':' . $argument;
        $response = trim((string)(new Backend())->configdpRun('monospot session_admin', [$packed]));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => 'failed', 'error' => $response];
    }

    /** Yetkili oturum listesi (UIBootgrid protokolü). */
    public function searchAction()
    {
        $rowCount = $this->request->get('rowCount', 'int', 50);
        $current = max(1, $this->request->get('current', 'int', 1));
        $limit = $rowCount <= 0 ? 500 : min(500, $rowCount);

        $sortField = 'start';
        $sortDir = 'desc';
        $sort = $this->request->get('sort');
        if (is_array($sort) && !empty($sort)) {
            $keys = array_keys($sort);
            $sortField = (string)$keys[0];
            $sortDir = strtolower((string)$sort[$keys[0]]) === 'asc' ? 'asc' : 'desc';
        }

        $result = $this->run('list', base64_encode(json_encode([
            'search' => (string)$this->request->get('searchPhrase', 'string', ''),
            'limit' => $limit,
            'offset' => ($current - 1) * $limit,
            'sort' => $sortField,
            'dir' => $sortDir,
        ], JSON_UNESCAPED_UNICODE)));

        if (($result['status'] ?? '') !== 'ok') {
            return [
                'rows' => [],
                'total' => 0,
                'rowCount' => $limit,
                'current' => $current,
                'error' => $result['error'] ?? 'oturum listesi alınamadı',
            ];
        }
        return $result;
    }

    /** Oturumu keser (core disconnect). */
    public function disconnectAction($sessionid = null)
    {
        if (!$this->request->isPost()) {
            return ['status' => 'failed', 'error' => 'POST gerekli'];
        }
        $sessionid = (string)$sessionid;
        if ($sessionid === '' || $sessionid === null) {
            return ['status' => 'failed', 'error' => 'oturum kimliği gerekli'];
        }
        return $this->run('disconnect', $sessionid);
    }
}
