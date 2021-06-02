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
    /**
     * @param null $configType
     * @return MysqlDB
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

    public function __construct($configName)
    {
        $this->name = $configName;

        $configData = self::getConfig($configName);
        try {
            $this->client = new Medoo($configData);
        } catch (\Exception $exception) {
            SentryClient::captureException($exception, ['config_name' => $configName, 'server' => $configData['server']]);
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
                    'write_uid' => SimpleLogger::getWriteUid(),
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

    /**
     * @param $where
     * @param string $column
     * @return mixed
     */
    public static function addOrgId($where,$column = '') {
        if (!empty($where['org_id'])) {
            return $where;
        }
        global $orgId ;
        $column = empty($column)?"org_id":$column;
        $where[$column] = $orgId;
        return $where;
    }
}
