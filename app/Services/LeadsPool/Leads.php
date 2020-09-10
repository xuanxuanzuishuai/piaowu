<?php


namespace App\Services\LeadsPool;



class Leads
{
    private $pid;
    private $id;
    private $student;
    private $studentId;
    private $package;

    private $date; // 分配日期，由进入PoolManager时分配配置的日期决定

    function __construct($config)
    {
        $this->id = $config['id'];
        $this->student = $config['student'];
        $this->studentId = $config['student']['id'];
        $this->package = $config['package'];
    }

    /**
     * @param int $date 20200901
     */
    function setDate($date)
    {
        $this->date = $date;
    }

    /**
     * 单次分配过程的id
     * @param string $pid
     */
    function setPid($pid)
    {
        $this->pid = $pid;
    }

    function getId()
    {
        return $this->id;
    }

    function getStudentId()
    {
        return $this->studentId;
    }

//    function addTo(Pool $pool)
//    {
//        if ($pool->getPoolType() == Pool::TYPE_EMPLOYEE) {
//            return $this->assignTo($pool);
//        } else {
//            return $this->moveTo($pool);
//        }
//    }

    function moveTo(Pool $pool)
    {
        return LeadsService::move($this->pid,
            $this->id,
            $pool->getId(),
            $this->date);
    }

    function assignTo(Pool $pool)
    {
        return LeadsService::assign($this->pid,
            $this->studentId,
            $pool->getId(),
            $this->package,
            $this->date);
    }
}