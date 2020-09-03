<?php


namespace App\Services\LeadsPool;


use App\Models\LeadsPoolLogModel;

class PoolManager
{
    const MAIN_POOL_ID = 1;

    private static $instance;

    private $date;

    private $pid;

    private $mainPool;
    private $pools;
    private $rules;

    public function __construct()
    {
        $this->date = date('Ymd');
        $this->mainPool = null;
        $this->pools = [];
    }

    /**
     * @return PoolManager
     */
    public static function getInstance()
    {
        if (empty(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function newProcess($studentId)
    {
        $time = date('Ymd H:i:s');
        $pid = md5($studentId . '_'.  $time);

//        $log = LeadsPoolLogModel::getRecord(['pid' => $pid]);
//        if (!empty($log)) {
//            // TODO: throw ERROR
//        }

        $this->pid = $pid;

        $this->log('pid = ' . $this->pid);
    }

    public function addLeads(Leads $leads, int $poolId = self::MAIN_POOL_ID, int $type = Pool::TYPE_POOL)
    {
        $this->newProcess($leads->getStudentId());
        $leads->setPid($this->pid);
        $leads->setDate($this->date);

        $pool = $this->getPool($poolId, $type);
        return $pool->addLeads($leads);
    }

    /**
     * @return Pool
     */
    public function getMainPool()
    {
        if (empty($this->mainPool)) {
            $this->mainPool = $this->getPool(self::MAIN_POOL_ID, Pool::TYPE_POOL);
        }
        return $this->mainPool;
    }

    /**
     * @param $id
     * @param $type
     * @return Pool
     */
    public function getPool($id, $type)
    {
        if (empty($this->pools[$id])) {
            $this->initPoolByType($id, $type);
        }
        return $this->pools[$id];
    }

    public function initPoolByType($poolId, $type)
    {
        if ($type == Pool::TYPE_POOL) {
            return $this->initPool($poolId);
        } elseif ($type == Pool::TYPE_EMPLOYEE) {
            return $this->initEmployeePool($poolId);
        }

        return null;
    }

    /**
     * @param $poolId
     * @return Pool
     */
    public function initPool($poolId)
    {
        $poolConfig = CacheManager::getPoolConfig($poolId, $this->date);
        $poolConfig['pool_type'] = Pool::TYPE_POOL;
        $poolConfig['date'] = $this->date;
        $poolConfig['capacity'] = PHP_INT_MAX;
        $poolConfig['is_dispatch'] = 1;

        $this->log("initPool");
        $this->log(print_r($poolConfig));

        $pool = new Pool($poolConfig, $this);
        $this->pools[$poolId] = $pool;

        $this->log("initRules");
        $ruleConfigs = CacheManager::getPoolRulesConfigs($poolId, $this->date);
        foreach ($ruleConfigs as $cfg) {
            $rule = $this->initRule($cfg['id'], $cfg);
            $pool->addRule($rule);
        }

        return $pool;
    }

    public function initEmployeePool($poolId)
    {
        $poolConfig = CacheManager::getEmployeePoolConfig($poolId, $this->date);
        $poolConfig['pool_type'] = Pool::TYPE_EMPLOYEE;
        $poolConfig['date'] = $this->date;
        $poolConfig['capacity'] = PHP_INT_MAX;
        $poolConfig['is_dispatch'] = 0;

        $this->log("initEmployeePool");
        $this->log(print_r($poolConfig, true));

        $pool = new Pool($poolConfig, $this);
        $this->pools[$poolId] = $pool;

        return $pool;
    }

    public function initRule($ruleId, $config)
    {
        $this->log("initRule");
        $this->log(print_r($config, true));
        $rule = new Rule($config, $this);
        $this->rules[$ruleId] = $rule;

        return $rule;
    }

    public function poolLog($type, $poolId, $poolType, $time = null, $studentId = null, $data = null)
    {
        if (is_array($data)) {
            $data = json_encode($data);
        }
        LeadsPoolLogModel::insertRecord([
            'pid' => $this->pid,
            'type' => $type,
            'pool_id' => $poolId,
            'pool_type' => $poolType,
            'create_time' => $time ?? time(),
            'date' => $this->date,
            'leads_student_id' => $studentId,
            'detail' => $data,
        ]);
    }

    public function logAdded($poolId, $poolType, $studentId, $value)
    {
        $this->poolLog(LeadsPoolLogModel::TYPE_ADDED,
            $poolId,
            $poolType,
            time(),
            $studentId
        );
        CacheManager::updatePoolDailyAdded($poolId, $this->date, $value);
    }

    public function logStashed($poolId, $poolType, $studentId, $value)
    {
        $this->poolLog(LeadsPoolLogModel::TYPE_STASHED,
            $poolId,
            $poolType,
            time(),
            $studentId
        );
        CacheManager::updatePoolDailyStashed($poolId, $this->date, $value);
    }

    public function logDispatched($poolId, $poolType, $studentId, $value)
    {
        $this->poolLog(LeadsPoolLogModel::TYPE_DISPATCHED,
            $poolId,
            $poolType,
            time(),
            $studentId
        );
        CacheManager::updatePoolDailyDispatched($poolId, $this->date, $value);
    }

    public function logPrepare($poolId, $poolType, $studentId, $data)
    {
        $this->poolLog(LeadsPoolLogModel::TYPE_PREPARE,
            $poolId,
            $poolType,
            time(),
            $studentId,
            $data
        );
    }

    public function errorNoRule($poolId, $poolType)
    {
        $this->poolLog(LeadsPoolLogModel::TYPE_ERROR_NO_RULES,
            $poolId,
            $poolType,
            time()
        );
    }

    public function log($message)
    {
        printf("[%s] " . $message . "\n", 'PM');
    }
}