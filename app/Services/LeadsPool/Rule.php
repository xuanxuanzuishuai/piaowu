<?php


namespace App\Services\LeadsPool;


class Rule
{
    /** @var PoolManager */
    private $manager;

    private $id; // rule_id

    private $status; // 开关

    private $weight; // 权重

    private $priority; // 当前优先级

    private $targetId;
    private $targetType;

    /** @var Pool 目标池 */
    private $target;

    function __construct($config, $manager)
    {
        $this->manager = $manager;

        $this->id = $config['id'];
        $this->status = $config['status'];
        $this->weight = $config['weight'];
        $this->priority = PHP_INT_MAX;
        $this->targetId = $config['target_id'];
        $this->targetType = $config['target_type'];
        $this->target = null;
    }

    function getId()
    {
        return $this->id;
    }

    function getStatus()
    {
        return $this->status;
    }

    function getWeight()
    {
        return $this->weight;
    }

    function getPriority()
    {
        return $this->priority;
    }

    /**
     * @return Pool
     */
    function getTargetPool()
    {
        if (empty($this->target)) {
            $this->target = $this->manager->getPool($this->targetId, $this->targetType);
        }
        return $this->target;
    }

    public function updatePriority($parentTotalSubWeight, $parentDispatchedCount)
    {
        $selfCount = $this->getTargetPool()->getTotalCount();
        $selfWeight = $this->weight;
        $this->priority = $this->calcPriority($selfCount, $selfWeight, $parentTotalSubWeight, $parentDispatchedCount);
    }

    private function calcPriority($selfCount, $selfWeight, $parentTotalSubWeight, $parentDispatchedCount)
    {
        $realWeight = $selfWeight / $parentTotalSubWeight;
        $expectedCount = ($parentDispatchedCount + 1) * $realWeight;
        return ($selfCount / $expectedCount);
    }

    function select(Leads $leads)
    {
        return $this->getTargetPool()->addLeads($leads);
    }
}