<?php


namespace App\Libs;


use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class ElasticsearchDB
{
    private static $instances;

    /**
     * @param string $configType
     * @return Client
     */
    public static function getDB($configType = null)
    {
        if (!isset(self::$instances)) {
            self::$instances = [];
        }

        $configType = $configType ?? 'default';
        if (!isset(self::$instances[$configType])) {
            $hosts = self::getConfig($configType);
            self::$instances[$configType] = ClientBuilder::create()->setHosts($hosts)->build();
        }

        return self::$instances[$configType];
    }

    public static function getConfig($configType)
    {
        switch ($configType) {
            default:
                return [
                    [
                        'host' => $_ENV['ES_HOST'],
                        'port' => $_ENV['ES_PORT'],
                        'user' => $_ENV['ES_USER'],
                        'pass' => $_ENV['ES_PASS']
                    ]
                ];
        }
    }

    public static function getIndex($name)
    {
        $env = $_ENV['ENV_NAME'] ?? 'dev';
        return $env . '_' . $name;
    }

    public static function update() {

    }
}