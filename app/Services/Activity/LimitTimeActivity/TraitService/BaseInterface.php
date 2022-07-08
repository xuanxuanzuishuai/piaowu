<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

/**
 * 活动客户端管理基础服务接口类：定义一些方法，由子类去实现
 */
interface BaseInterface
{
    /**
     * 学生付费状态检测
     * @return mixed
     */
    public function studentStatusCheck($studentId);
}