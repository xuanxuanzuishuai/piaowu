<?php

namespace App\Services\Activity\LimitTimeActivity\TraitService;

/**
 * 活动客户端管理基础服务接口类：定义一些方法，由子类去实现
 */
interface ClientBaseInterface
{
    public function run();
}