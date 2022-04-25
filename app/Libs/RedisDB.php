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
     * 单例模式
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
                'scheme'   => 'tcp',
                'host'     => $_ENV['REDIS_HOST'],
                'port'     => intval($_ENV['REDIS_PORT']),
                'database' => $db,
            ];

            if (!empty($_ENV['REDIS_PASS'])) {
                $config['password'] = $_ENV['REDIS_PASS'];
            }

            self::$clients[$db] = new Client($config, []);
        }

        return self::$clients[$db];
    }

    /**
     * 每次获取一个新的实例:使用场景是脚本执行过长，避免实例失效，否则应使用上方单例模式获取！！！
     * @param null $db
     * @return Client
     */
    public static function getNewConn($db = null): client
    {
        $db = $db ?? intval($_ENV['REDIS_DB']);
        $config = [
            'scheme'   => 'tcp',
            'host'     => $_ENV['REDIS_HOST'],
            'port'     => intval($_ENV['REDIS_PORT']),
            'database' => $db,
        ];

        if (!empty($_ENV['REDIS_PASS'])) {
            $config['password'] = $_ENV['REDIS_PASS'];
        }
        return new Client($config, []);
    }
}