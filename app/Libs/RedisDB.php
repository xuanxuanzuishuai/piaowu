<?php
/**
 * Redis DB connection
 * @author tianye@xiaoyezi.com
 * @since 2016-07-13 16:04:02
 */

namespace App\Libs;

use Predis\Client;

class RedisDB
{
    private static $clients;

    /**
     * @param null $db
     * @return Client
     */
    public static function getConn($db = null)
    {
        $db = $db ?? intval($_ENV['REDIS_DB']);

        if (empty(self::$clients)) {
            self::$clients = [];
        }

        if (empty(self::$clients[$db])) {
            $config = [
                'scheme' => 'tcp',
                'host' => $_ENV['REDIS_HOST'],
                'port' => intval($_ENV['REDIS_PORT']),
                'database' => $db,
            ];

            if (!empty($_ENV['REDIS_PASS'])) {
                $config['password'] = $_ENV['REDIS_PASS'];
            }

            self::$clients[$db] = new Client($config, []);
        }

        return self::$clients[$db];
    }
}