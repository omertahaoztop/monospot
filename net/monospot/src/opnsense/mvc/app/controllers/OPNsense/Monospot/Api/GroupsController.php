<?php

/**
 * Monospot grupları (hotspot SQLite `group` tablosu) — CRUD köprüsü.
 *
 * Yazma işlemleri configd üzerinden root olarak çalışan group_admin.php'ye
 * devredilir (portal veritabanına www olarak yazar, sahiplik değiştirilmez).
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class GroupsController extends ApiControllerBase
{
    private function run($command, $argument = '')
    {
        $packed = $argument === '' ? $command : $command . ':' . $argument;
        $response = trim((string)(new Backend())->configdpRun('monospot group_admin', [$packed]));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => 'failed', 'error' => $response];
    }

    public function searchAction()
    {
        $rowCount = $this->request->get('rowCount', 'int', 50);
        $current = max(1, $this->request->get('current', 'int', 1));
        $limit = $rowCount <= 0 ? 500 : min(500, $rowCount);

        $sortField = 'name';
        $sortDir = 'asc';
        $sort = $this->request->get('sort');
        if (is_array($sort) && !empty($sort)) {
            $keys = array_keys($sort);
            $sortField = (string)$keys[0];
            $sortDir = strtolower((string)$sort[$keys[0]]) === 'desc' ? 'desc' : 'asc';
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
                'error' => $result['error'] ?? 'liste alınamadı',
            ];
        }
        return $result;
    }

    public function getAction($uuid = null)
    {
        $result = $this->run('get', (string)$uuid);
        if (($result['status'] ?? '') !== 'ok') {
            return ['group' => [], 'validations' => ['group.name' => $result['error'] ?? 'bulunamadı']];
        }
        return ['group' => $result['group']];
    }

    public function addAction()
    {
        return $this->save(null);
    }

    public function setAction($uuid = null)
    {
        return $this->save((string)$uuid);
    }

    private function save($uuid)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'validations' => ['group.name' => 'POST gerekli']];
        }
        $data = $this->request->getPost('group');
        if (!is_array($data)) {
            return ['result' => 'failed', 'validations' => ['group.name' => 'form verisi okunamadı']];
        }
        if ($uuid !== null) {
            $data['uuid'] = $uuid;
        }
        $result = $this->run('save', base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)));
        if (($result['status'] ?? '') !== 'ok') {
            return ['result' => 'failed', 'validations' => ['group.name' => $result['error'] ?? 'kaydedilemedi']];
        }
        return ['result' => 'saved', 'uuid' => $result['uuid'] ?? ''];
    }

    public function delAction($uuids = null)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'validations' => ['group.name' => 'POST gerekli']];
        }
        $failed = [];
        foreach (array_filter(explode(',', (string)$uuids)) as $uuid) {
            $result = $this->run('delete', trim($uuid));
            if (($result['status'] ?? '') !== 'ok') {
                $failed[] = trim($uuid);
            }
        }
        return empty($failed)
            ? ['result' => 'deleted']
            : ['result' => 'failed', 'validations' => ['group.name' => 'silinemeyen kayıt: ' . implode(',', $failed)]];
    }
}
