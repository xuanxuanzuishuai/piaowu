<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 07/08/2018
 * Time: 3:58 PM
 */

namespace App\Libs;

use Medoo\Medoo;

/**
 * Class MysqlDB
 * @package Classroom\Libs
 *
 * __call Medoo method
 * @method array select($table, $join, $columns = null, $where = null)
 * @method \PDOStatement insert($table, $datas)
 * @method \PDOStatement update($table, $data, $where = null)
 * @method \PDOStatement delete($table, $where)
 * @method \PDOStatement replace($table, $columns, $where = null)
 * @method mixed get($table, $join = null, $columns = null, $where = null)
 * @method boolean has($table, $join, $where = null)
 * @method number count($table, $join = null, $column = null, $where = null)
 * @method number max($table, $join = null, $column = null, $where = null)
 * @method number min($table, $join = null, $column = null, $where = null)
 * @method number avg($table, $join = null, $column = null, $where = null)
 * @method number sum($table, $join = null, $column = null, $where = null)
 * @method \PDOStatement query($query, $map = [])
 * @method number id()
 */
class MysqlDB
{
    private static $instances;

    private $client;
    private $name;

    const ERROR_CODE_NO_ERROR = '00000';
    const CONFIG_SLAVE = 'dss_slave';
    const CONFIG_ERP_SLAVE = 'erp_slave';
    const CONFIG_AD = 'ad';

    /**
     * 获取数据库实例
     * @param null $configType
     * @param true $isStopEmulatePrepares     启用或禁用预处理语句的模拟。 有些驱动不支持或有限度地支持本地预处理。
     *                                          使用此设置强制PDO总是模拟预处理语句（如果为 true ），或试着使用本地预处理语句（如果为 false）。
     *                                          如果驱动不能成功预处理当前查询，它将总是回到模拟预处理语句上。 需要 bool 类型。
     * @return MysqlDB|mixed
     */
    public static function getDB($configType = null, bool $isStopEmulatePrepares = true)
    {
        if (!isset(self::$instances)) {
            self::$instances = [];
        }
        $configType = $configType ?? 'default';
        $instancesKey = $configType.$isStopEmulatePrepares;
        if (!isset(self::$instances[$instancesKey])) {
            self::$instances[$instancesKey] = new self($configType, $isStopEmulatePrepares);
        }

        return self::$instances[$instancesKey];
    }

    public function __construct($configName, $isStopEmulatePrepares)
    {
        $this->name = $configName;

        $configData = self::getConfig($configName);
        try {
            $this->client = new Medoo($configData);
            //设置返回数据和数据表字段设置类型一致，禁止所有字段自动格式化成为字符串
            $this->client->pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, $isStopEmulatePrepares);
        } catch (\Exception $exception) {
            SentryClient::captureException($exception, ['db' => 'mysql', 'config_name' => $configName, 'server' => $configData['server']]);
        }
    }

    public function __call($name, $arguments)
    {
        $ret = call_user_func_array(array($this->client, $name), $arguments);

        if ($name == 'id') {
            return $ret;
        }

        $error = $this->client->error();
        $source = '';
        $arr = '';
        if (!empty($error) && ($error[0] != self::ERROR_CODE_NO_ERROR || $error[1] != null)) {
            $arr = debug_backtrace();
            for($i = 0; $i < min(count($arr), 4); $i++){
                if (isset($arr['i']['file'])){
                    $source .= $arr[$i]['file'] . ':' . $arr[$i]['line'] . '<--';
                }
            }
            if($name != 'beginTransaction'){
                $record = [
                    'error' => $error,
                    'last_query' => $this->client->last(),
                    'method' => $name,
                    'arguments' => $arguments,
                    'log_uid' => SimpleLogger::getWriteUid(),
                ];
                $extra = [
                    'extra' => [
                        'stack_trace' => $arr
                    ]
                ];
                $sentryClient = new \Raven_Client($_ENV['SENTRY_NOTIFY_URL']);
                $sentryClient->captureMessage('DB ERROR: '.$this->client->last(), $record, $extra);
            }
        }

        SimpleLogger::debug($source, [
            'METHOD' => $name,
            'DBClient' => $this->name,
            'SQL' => preg_replace('/\\n/',' ', $this->client->last()),
            'ERROR' => $error,
            'TRACE' => $arr
        ]);

        return $ret;
    }

    public function queryAll($query, $map = [])
    {
        $statement = $this->query($query, $map);
        if ($statement) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        }
        return null;
    }

    public function insertGetID($table, $data)
    {
        $statement = $this->insert($table, $data);
        if ($statement && $statement->errorCode() == self::ERROR_CODE_NO_ERROR) {
            return $this->client->id();
        }
        return null;
    }

    public function updateGetCount($table, $data, $where = null)
    {
        $statement = $this->update($table, $data, $where);
        if ($statement && $statement->errorCode() == self::ERROR_CODE_NO_ERROR) {
            return $statement->rowCount();
        }
        return null;
    }

    public function deleteGetCount($table, $where){
        $statement = $this->delete($table, $where);
        if ($statement && $statement->errorCode() == self::ERROR_CODE_NO_ERROR) {
            return $statement->rowCount();
        }
        return null;
    }

    public function beginTransaction()
    {
        $this->client->pdo->beginTransaction();
        SimpleLogger::debug(__FILE__ . ":" . __LINE__, [
            'DBClient' => $this->name,
            'SQL' => 'beginTransaction'
        ]);
    }

    public function commit()
    {
        $this->client->pdo->commit();
        SimpleLogger::debug(__FILE__ . ":" . __LINE__, [
            'DBClient' => $this->name,
            'SQL' => 'commit'
        ]);

    }

    public function rollBack()
    {
        $this->client->pdo->rollBack();
        SimpleLogger::debug(__FILE__ . ":" . __LINE__, [
            'DBClient' => $this->name,
            'SQL' => 'rollBack'
        ]);
    }

    public static function getConfig($configType)
    {
        switch ($configType) {
            case self::CONFIG_SLAVE:
                return [
                    'database_type' => $_ENV['DB_S_TYPE'],
                    'database_name' => $_ENV['DB_DSS_S_NAME'],
                    'server' => $_ENV['DB_S_HOST'],
                    'username' => $_ENV['DB_S_USERNAME'],
                    'password' => $_ENV['DB_S_PASSWORD'],
                    'charset' => $_ENV['DB_S_CHARSET'],
                    'prefix' => $_ENV['DB_S_PREFIX'],
                    'port' => $_ENV['DB_S_PORT'],
                    'logging' => intval($_ENV['DB_DEBUG_MODE']) ? true : false,
                    'debug_mode' => intval($_ENV['DB_DEBUG_MODE']) ? true : false,
                    'option' => [\PDO::ATTR_STRINGIFY_FETCHES => false,
                        \PDO::ATTR_EMULATE_PREPARES => true]
                ];
                break;
            case self::CONFIG_ERP_SLAVE:
                return [
                    'database_type' => $_ENV['DB_S_TYPE'],
                    'database_name' => $_ENV['DB_ERP_S_NAME'],
                    'server' => $_ENV['DB_S_HOST'],
                    'username' => $_ENV['DB_S_USERNAME'],
                    'password' => $_ENV['DB_S_PASSWORD'],
                    'charset' => $_ENV['DB_S_CHARSET'],
                    'prefix' => $_ENV['DB_S_PREFIX'],
                    'port' => $_ENV['DB_S_PORT'],
                    'logging' => intval($_ENV['DB_DEBUG_MODE']) ? true : false,
                    'debug_mode' => intval($_ENV['DB_DEBUG_MODE']) ? true : false,
                    'option' => [\PDO::ATTR_STRINGIFY_FETCHES => false,
                        \PDO::ATTR_EMULATE_PREPARES => true]
                ];
                break;
            case self::CONFIG_AD:
                return [
                    'database_type' => $_ENV['DB_AD_TYPE'],
                    'database_name' => $_ENV['DB_AD_NAME'],
                    'server' => $_ENV['DB_AD_HOST'],
                    'username' => $_ENV['DB_AD_USERNAME'],
                    'password' => $_ENV['DB_AD_PASSWORD'],
                    'charset' => $_ENV['DB_AD_CHARSET'],
                    'prefix' => $_ENV['DB_AD_PREFIX'],
                    'port' => $_ENV['DB_AD_PORT'],
                    'logging' => intval($_ENV['DB_DEBUG_MODE']) ? true : false,
                    'debug_mode' => intval($_ENV['DB_DEBUG_MODE']) ? true : false,
                    'option' => [\PDO::ATTR_STRINGIFY_FETCHES => false,
                                 \PDO::ATTR_EMULATE_PREPARES => true]
                ];
                break;
            default:
                return [
                    'database_type' => $_ENV['DB_TYPE'],
                    'database_name' => $_ENV['DB_NAME'],
                    'server' => $_ENV['DB_HOST'],
                    'username' => $_ENV['DB_USERNAME'],
                    'password' => $_ENV['DB_PASSWORD'],
                    'charset' => $_ENV['DB_CHARSET'],
                    'prefix' => $_ENV['DB_PREFIX'],
                    'port' => $_ENV['DB_PORT'],
                    'logging' => intval($_ENV['DB_DEBUG_MODE']) ? true : false,
                    'debug_mode' => intval($_ENV['DB_DEBUG_MODE']) ? true : false,
                    'option' => [\PDO::ATTR_STRINGIFY_FETCHES => false,
                        \PDO::ATTR_EMULATE_PREPARES => true]
                ];
        }
    }
}
