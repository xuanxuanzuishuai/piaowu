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
use ClickHouseDB\Transport\Http;

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

    const OP = 'op';    // op服务库
    
    // build oneself clickhouse
    const CONFIG_BO = 'build_oneself';
    const CONFIG_ERP = 'erp';
    
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
    
    /**
     * 自建clickhouse
     * @return CHDB
     */
    public static function getBODB()
    {
        return self::getDB(self::CONFIG_BO);
    }

    /**
     * erp clickhouse
     * @return CHDB
     */
    public static function getErpDB()
    {
        return self::getDB(self::CONFIG_ERP);
    }
    
    public function __construct($configName = 'default')
    {
        $this->name = $configName;

        $configData = self::getConfig($configName);
        try {
            $this->client = new Client($configData);
            $this->client->database($configData['database_name']);
            $this->client->setTimeout(5);       // 10 seconds
            $this->client->setConnectTimeOut(5); // 5 seconds
        } catch (\Exception $exception) {
            SentryClient::captureException($exception, ['db' => 'click_house', 'config_name' => $configName, 'server' => $configData['server']]);
        }
    }

    public static function getConfig($configType)
    {
        switch ($configType) {
            case self::OP:
                return [
                    'host' => $_ENV['CHDB_OP_HOST'],
                    'port' => $_ENV['CHDB_OP_PORT'],
                    'username' => $_ENV['CHDB_OP_USERNAME'],
                    'password' => $_ENV['CHDB_OP_PASSWORD'],
                    'database_name' => $_ENV['CHDB_OP_DATABASE'],
                    'timeout' => $_ENV['CHDB_OP_TIMEOUT'],
                    'connect_timeout' => $_ENV['CHDB_OP_CONNECT_TIMEOUT'],
                    'auth_method'     => Http::AUTH_METHOD_QUERY_STRING,
                ];
            case self::CONFIG_BO:
                return [
                    'host'            => $_ENV['CHDB_BO_HOST'],
                    'port'            => $_ENV['CHDB_BO_PORT'],
                    'username'        => $_ENV['CHDB_BO_USERNAME'],
                    'password'        => $_ENV['CHDB_BO_PASSWORD'],
                    'database_name'   => $_ENV['CHDB_BO_DATABASE'],
                    'timeout'         => $_ENV['CHDB_BO_TIMEOUT'],
                    'connect_timeout' => $_ENV['CHDB_BO_CONNECT_TIMEOUT'],
                    'auth_method'     => Http::AUTH_METHOD_QUERY_STRING,
                ];
            case self::CONFIG_ERP:
                return [
                    'host'            => $_ENV['CHDB_ERP_HOST'],
                    'port'            => $_ENV['CHDB_ERP_PORT'],
                    'username'        => $_ENV['CHDB_ERP_USERNAME'],
                    'password'        => $_ENV['CHDB_ERP_PASSWORD'],
                    'database_name'   => $_ENV['CHDB_ERP_DATABASE'],
                    'timeout'         => $_ENV['CHDB_ERP_TIMEOUT'],
                    'connect_timeout' => $_ENV['CHDB_ERP_CONNECT_TIMEOUT'],
                    'auth_method'     => Http::AUTH_METHOD_QUERY_STRING,
                ];
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
            SimpleLogger::error('call user func exception', [print_r($e->getMessage(), true)]);
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
        SimpleLogger::info("query sql",[$statement->sql()]);
        if ($statement) {
            return $statement->rows();
        }
        return [];
    }
}