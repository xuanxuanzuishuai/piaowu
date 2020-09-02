<?php


namespace App\Services\LeadsPool;


class Pool
{
    const TYPE_POOL = 1;
    const TYPE_EMPLOYEE = 2;

    const TARGET_POOL = 1;
    const TARGET_DEPT = 2;

    /** @var PoolManager */
    private $manager;

    private $id;
    private $poolType;

    private $date;

    private $status;
    private $isDispatch;
    private $capacity;

    private $added; // 进入leads数
    private $stashed; // 堆积leads数
    private $dispatched; // 分配leads数

    private $rules;
    private $preparedRules;

    /** @var Leads 正在分配的leads */
    private $prepareFor;

    function __construct($config, $manager)
    {
        $this->manager = $manager;

        $this->id = $config['id'];
        $this->poolType = $config['pool_type'];
        $this->isDispatch = $config['is_dispatch'];

        $this->status = $config['status'];
        $this->capacity = $config['capacity'];

        $this->date = $config['date'];

        $this->added = $config['added'] ?? 0;
        $this->stashed = $config['stashed'] ?? 0;
        $this->dispatched = $config['dispatched'] ?? 0;

        $this->rules = [];
        $this->preparedRules = [];
        $this->isDispatch = true;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getPoolType()
    {
        return $this->poolType;
    }

    public function getCapacity()
    {
        return $this->capacity;
    }

    public function getAdded()
    {
        return $this->added;
    }

    public function getTotalCount()
    {
        return $this->dispatched + $this->stashed;
    }

    public function addRule(Rule $rule)
    {
        if (empty($rule)) {
            $this->log("invalid empty rule added!");
        }
        $this->rules[] = $rule;
    }

    public function addLeads(Leads $leads)
    {
        $this->added++;
        $this->manager->logAdded($this->id, $this->poolType, $leads->getStudentId(), $this->added);

        if ($this->poolType == self::TYPE_EMPLOYEE) {
            $success = $leads->assignTo($this);
            if ($success) {
                $this->stashed++;
                $this->manager->logStashed($this->id, $this->poolType, $leads->getStudentId(), $this->stashed);
            }
            return $success;
        }

        // $this->poolType == self::TYPE_POOL

        if (!$this->isDispatch) {
            $success = $leads->moveTo($this);
            if ($success) {
                $this->stashed++;
                $this->manager->logStashed($this->id, $this->poolType, $leads->getStudentId(), $this->stashed);
            }
            return $success;
        }

        if (empty($this->rules)) {
            $this->manager->errorNoRule($this->id, $this->poolType);
            return false;
        }

        $success = $this->dispatch($leads);
        if ($success) {
            $this->dispatched++;
            $this->manager->logDispatched($this->id, $this->poolType, $leads->getStudentId(), $this->dispatched);
        }
        return $success;
    }

    public function dispatch(Leads $leads)
    {
        $this->prepareFor = $leads;
        $this->prepareRules();

        $success = false;
        while ($rule = $this->selectRule()) {
            $this->log("select " . $rule->getId());
            $success = $rule->select($leads);
            if ($success) {
                $this->log("success push " . $rule->getId());
                break;
            }
        }

        return $success;
    }

    private function prepareRules()
    {
        $this->log("prepareRules");

        $logData = [
            'invalid' => [],
            'valid' => [],
            'total_weight' => 0,
            'dispatched' => $this->dispatched
        ];

        $validRules = [];
        $totalWeight = 0;
        foreach ($this->rules as $rule) {
            /** @var Rule $rule */
            if (!$rule->getStatus()) {
                $logData['invalid'][] = ['id' => $rule->getId()];
                $this->log("skip disable rule: " . $rule->getId());
                continue;
            }

            $validRules[] = $rule;
            $totalWeight += $rule->getWeight();
        }
        $logData['total_weight'] = $totalWeight;

        foreach ($validRules as $rule) {
            /** @var Rule $rule */
            $rule->updatePriority($totalWeight, $this->dispatched);

            $logData['valid'][] = ['id' => $rule->getId(), 'weight' => $rule->getWeight(), 'priority' => $rule->getPriority()];

            $this->log("sub " . $rule->getId() . ", update priority: " . $rule->getPriority());
        }

        // 子节点按优先级升序排列, 优先级相同按ID升序
        usort($validRules, function (Rule $a, Rule $b) {
            $pa = $a->getPriority();
            $pb = $b->getPriority();
            if ($pa == $pb) {
                return $a->getId() < $b->getId() ? -1 : 1;
            }
            return $pa < $pb ? -1 : 1;
        });

        $this->preparedRules = $validRules;

        $sid = $this->prepareFor->getStudentId();
        $this->manager->logPrepare($this->id, $this->poolType, $sid, $logData);
        $this->log("prepareRules end");
    }

    /**
     * @return Rule
     */
    public function selectRule()
    {
        return array_shift($this->preparedRules);
    }

    public function log($message)
    {
        $message = sprintf("[%s] " . $message . "\n", $this->getId());
        $this->manager->log($message);
    }

    public function dump()
    {
        $this->log("dump:================== " . $this->getId());
        $this->log("status:" . $this->status);
        $this->log("capacity:" . $this->capacity);
        $this->log("received:" . $this->added);
        $this->log("stashed:" . $this->stashed);
        $this->log("dispatched:" . $this->dispatched);
        foreach ($this->rules as $sub) {
            $sub->dump();
        }
    }
}