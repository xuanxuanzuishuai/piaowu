<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/7/30
 * Time: 3:08 PM
 */

namespace App\Libs;


use ClickHouseDB\Client;
use ClickHouseDB\Statement;

/**
 * Class CHDB
 * @package App\Libs
 *
 * __call ClickHouseDB method
 * @method Statement select(string $sql, array $bindings = [])
 * @method insert(string $table, array $values, array $columns = []): Statement
 */
class CHDB
{
    private static $instances;

    private $client;
    private $name;

    /**
     * @param null $configType
     * @return CHDB
     */
    public static function getDB($configType = null)
    {
        if (!isset(self::$instances)) {
            self::$instances = [];
        }

        $configType = $configType ?? 'default';
        if (!isset(self::$instances[$configType])) {
            self::$instances[$configType] = new self($configType);
        }

        return self::$instances[$configType];
    }

    public function __construct($configName = 'default')
    {
        $this->name = $configName;

        $configData = self::getConfig($configName);
        $this->client = new Client($configData);
        $this->client->database($configData['database_name']);
        $this->client->setTimeout(5);       // 10 seconds
        $this->client->setConnectTimeOut(5); // 5 seconds
    }

    public static function getConfig($configType)
    {
        switch ($configType) {
            case 'default':
            default:
                return [
                    'host' => $_ENV['CHDB_HOST'],
                    'port' => $_ENV['CHDB_PORT'],
                    'username' => $_ENV['CHDB_USERNAME'],
                    'password' => $_ENV['CHDB_PASSWORD'],
                    'database_name' => $_ENV['CHDB_DATABASE'],
                    'timeout' => $_ENV['CHDB_TIMEOUT'],
                    'connect_timeout' => $_ENV['CHDB_CONNECT_TIMEOUT'],
                ];
        }

    }

    public function __call($name, $arguments)
    {
        try {
            $ret = call_user_func_array(array($this->client, $name), $arguments);
        } catch (\Exception $e) {
            SimpleLogger::error('make user qr image exception', [print_r($e->getMessage(), true)]);
            return null;
        }
        return $ret;
    }

    /**
     * @param $query
     * @param array $map
     * @return array
     */
    public function queryAll($query, $map = [])
    {
        $statement = $this->select($query, $map);
        if ($statement) {
            return $statement->rows();
        }
        return [];
    }
}