<?php
/**
 * author: qingfeng.lian
 * date: 2022/9/1
 */

namespace App\Libs;

use Elasticsearch\ClientBuilder;
use Exception;

class Elasticsearch
{
    private static $instance = null;
    private        $client;
    private        $index    = null;

    private function __construct()
    {
        if ($_ENV['ES_SCHEME'] == 'https') {
            $esHost[] = $_ENV['ES_SCHEME'] . '://' . $_ENV['ES_HOST'] . ":" . $_ENV['ES_PORT'];
        } else {
            $esHost[] = $_ENV['ES_HOST'] . ":" . $_ENV['ES_PORT'];
        }
        $this->client = ClientBuilder::create()->setHosts($esHost)->setBasicAuthentication($_ENV['ES_USER'], $_ENV['ES_PASS'])->build();
    }

    private function __clone()
    {
    }

    public static function getInstance($index): Elasticsearch
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        self::$instance->index = $index;
        return self::$instance;
    }

    public function search($body): array
    {
        try {
            if (empty($body)) {
                return [];
            }
            $data = $this->client->search([
                'index' => $this->index,
                'body'  => [
                    'query' => $body
                ]
            ]);
        } catch (Exception $e) {
            SimpleLogger::info("elasticsearch::search::fail", [$body, $e->getMessage()]);
            return [];
        }
        SimpleLogger::info("elasticsearch::search::success", [$data]);
        return [
            'total' => $data['hits']['total']['value'],
            'hits'  => is_array($data['hits']['hits']) ? $data['hits']['hits'] : [],
        ];
    }

    public function index($body): bool
    {
        try {
            $res = $this->client->index([
                'index' => $this->index,
                'body'  => $body
            ]);
        } catch (Exception $e) {
            SimpleLogger::info("elasticsearch::index::fail", [$body, $e->getMessage()]);
            return false;
        }
        SimpleLogger::info("elasticsearch::index::success", [$body, $res]);
        return true;
    }

    public function update($id, $body): bool
    {
        try {
            $res = $this->client->update([
                'index' => $this->index,
                'id'    => $id,
                'body'  => [
                    'doc' => $body,
                ]
            ]);
        } catch (Exception $e) {
            SimpleLogger::info("elasticsearch::update::fail", [$id, $body, $e->getMessage()]);
            return false;
        }
        SimpleLogger::info("elasticsearch::update::success", [$id, $body, $res]);
        return true;
    }

    public function delete($id): bool
    {
        try {
            if ($this->exists($id)) {
                $res = $this->client->delete([
                    'index' => $this->index,
                    'id'    => $id,
                ]);
            }
        } catch (Exception $e) {
            SimpleLogger::info("elasticsearch::delete::fail", [$id, $e->getMessage()]);
            return false;
        }
        SimpleLogger::info("elasticsearch::delete::success", [$id, $res ?? null]);
        return true;
    }

    public function exists($id): bool
    {
        try {
            $exists = $this->client->exists([
                'index' => $this->index,
                'id'    => $id,
            ]);
        } catch (Exception $e) {
            SimpleLogger::info("elasticsearch::exists::fail", [$id, $e->getMessage()]);
            return false;
        }
        SimpleLogger::info("elasticsearch::exists::success", [$id, $exists]);
        return $exists;
    }

    public function getOneDoc($body): array
    {
        $data = $this->search([
            'match' => $body,
        ]);
        $source = $data['hits'][0]['_source'] ?? [];
        return [$source, $data['hits'][0]['_id']];
    }
}