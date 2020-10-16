<?php


namespace App\Services\LeadsPool;



use App\Libs\SimpleLogger;
use App\Models\PackageExtModel;

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

    /**
     * 分配例子到助教或课管
     * @param Pool $pool
     * @return bool
     */
    function assignTo(Pool $pool)
    {
        //年卡可包分配课管  体验卡分配助教
        if ($this->package['package_type'] == PackageExtModel::PACKAGE_TYPE_NORMAL) {
            return LeadsService::assignToCourseManage(
                $this->pid,
                $this->studentId,
                $pool->getId(),
                $this->package,
                $this->date);
        } elseif ($this->package['package_type'] == PackageExtModel::PACKAGE_TYPE_TRIAL) {
            return LeadsService::assign($this->pid,
                $this->studentId,
                $pool->getId(),
                $this->package,
                $this->date);
        } else {
            SimpleLogger::error("package type stop allot leads", ['package' => $this->package]);
            return true;
        }

    }
}