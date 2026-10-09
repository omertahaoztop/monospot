<?php

/**
 * Monospot kullanıcıları (hotspot SQLite veritabanı) — CRUD köprüsü.
 *
 * Veri core config'te değil uygulama veritabanında olduğu için yazma işlemleri
 * configd üzerinden root olarak çalışan user_admin.php'ye devredilir
 * (portal www olarak aynı veritabanına yazar; sahiplik değiştirilmez).
 */

namespace OPNsense\Monospot\Api;

use OPNsense\Base\ApiControllerBase;
use OPNsense\Core\Backend;

class UsersController extends ApiControllerBase
{
    /**
     * configd action çağırır (argümanlar sade değer olarak verilir).
     */
    private function run($command, $argument = '')
    {
        $packed = $argument === '' ? $command : $command . ':' . $argument;
        $response = trim((string)(new Backend())->configdpRun('monospot user_admin', [$packed]));
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : ['status' => 'failed', 'error' => $response];
    }

    /**
     * Kullanıcı listesi (UIBootgrid protokolü: rowCount, current, sort, searchPhrase).
     */
    public function searchAction()
    {
        $rowCount = $this->request->get('rowCount', 'int', 50);
        $current = max(1, $this->request->get('current', 'int', 1));
        $limit = $rowCount <= 0 ? 500 : min(500, $rowCount);

        $sortField = 'id';
        $sortDir = 'desc';
        $sort = $this->request->get('sort');
        if (is_array($sort) && !empty($sort)) {
            $keys = array_keys($sort);
            $sortField = (string)$keys[0];
            $sortDir = strtolower((string)$sort[$keys[0]]) === 'asc' ? 'asc' : 'desc';
        }

        $payload = base64_encode(json_encode([
            'search' => (string)$this->request->get('searchPhrase', 'string', ''),
            'limit' => $limit,
            'offset' => ($current - 1) * $limit,
            'sort' => $sortField,
            'dir' => $sortDir,
        ], JSON_UNESCAPED_UNICODE));

        $result = $this->run('list', $payload);
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

    /**
     * Tek kullanıcı (dialog formunu doldurur).
     */
    public function getAction($uuid = null)
    {
        $result = $this->run('get', (string)$uuid);
        if (($result['status'] ?? '') !== 'ok') {
            return ['user' => [], 'validations' => ['user.username' => $result['error'] ?? 'bulunamadı']];
        }
        return ['user' => $result['user']];
    }

    /**
     * Yeni kullanıcı (dialog formu POST: {"user": {...}}).
     */
    public function addAction()
    {
        return $this->save(null);
    }

    /**
     * Var olan kullanıcıyı günceller.
     */
    public function setAction($uuid = null)
    {
        return $this->save((string)$uuid);
    }

    private function save($uuid)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'validations' => ['user.username' => 'POST gerekli']];
        }
        $data = $this->request->getPost('user');
        if (!is_array($data)) {
            return ['result' => 'failed', 'validations' => ['user.username' => 'form verisi okunamadı']];
        }
        if ($uuid !== null) {
            $data['uuid'] = $uuid;
        }
        $result = $this->run('save', base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE)));
        if (($result['status'] ?? '') !== 'ok') {
            return ['result' => 'failed', 'validations' => ['user.username' => $result['error'] ?? 'kaydedilemedi']];
        }
        return ['result' => 'saved', 'uuid' => $result['uuid'] ?? ''];
    }

    /**
     * Kullanıcı(ları) siler (virgülle ayrılmış uuid listesi).
     */
    public function delAction($uuids = null)
    {
        if (!$this->request->isPost()) {
            return ['result' => 'failed', 'validations' => ['user.username' => 'POST gerekli']];
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
            : ['result' => 'failed', 'validations' => ['user.username' => 'silinemeyen kayıt: ' . implode(',', $failed)]];
    }
}
