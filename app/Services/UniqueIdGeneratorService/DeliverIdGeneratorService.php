<?php

namespace App\Services\UniqueIdGeneratorService;

use App\Libs\Constants;
use App\Libs\RedisDB;
use App\Libs\SimpleLogger;
use App\Models\UniqueIdGeneratorModel;

class DeliverIdGeneratorService
{
    private $redisDb;
    private $redisKey = 'unique_deliver_ids';
    private $lockKey = 'unique_deliver_lock';

    public function __construct()
    {
        $this->redisDb = RedisDB::getConn();

    }

    /**
     * 获取发货单ID
     * @return string
     */
    public function getDeliverId(): string
    {
        //获取当前列表最右侧元素:空进行列表生成
        $deliverId = $this->redisDb->rpop($this->redisKey);
        if (empty($deliverId)) {
            $generateRes = self::generateDeliverId();
            if ($generateRes === false) {
                return '';
            }
            $deliverId = $this->redisDb->rpop($this->redisKey);
        }
        return Constants::UNIQUE_ID_PREFIX . sprintf("%010d", $deliverId);
    }

    /**
     * 生成发货单ID
     * @return bool
     */
    private function generateDeliverId(): bool
    {
        //加锁更新
        $lockStatus = $this->redisDb->setnx($this->lockKey, 1);
        if (empty($lockStatus)) {
            SimpleLogger::error("set generate lock fail", []);
            return false;
        }
        //获取配置数据
        try {
            $configData = UniqueIdGeneratorModel::getGeneratorConfig(UniqueIdGeneratorModel::BUSINESS_TYPE_DELIVER);
            if (empty($configData)) {
                SimpleLogger::error("generate config data empty",
                    ["business_type" => UniqueIdGeneratorModel::BUSINESS_TYPE_DELIVER]);
                return false;
            }
            $updateRes = UniqueIdGeneratorModel::updateGeneratorConfig($configData['id'],
                $configData['max_id'] + $configData['step'], $configData['version'] + 1);
            //更新数据
            if (empty($updateRes)) {
                SimpleLogger::error("update generate data error", [$configData]);
                return false;
            }
            //生成缓存
            $uniqueIdArr = [];
            for ($i = 1; $i <= $configData['step']; $i++) {
                $uniqueIdArr[] = $configData['max_id'] + $i;
            }
            $this->redisDb->lpush($this->redisKey, $uniqueIdArr);
            return true;
        } finally {
            $this->redisDb->del([$this->lockKey]);
        }
    }
}