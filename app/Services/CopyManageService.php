<?php
/**
 * Created by PhpStorm.
 * User: theone
 * Date: 2021/10/11
 * Time: 6:17 PM
 */

namespace App\Services;


use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Models\CopyManageModel;
use App\Models\OperationActivityModel;

class CopyManageService
{
    /**
     * 编辑文案
     * @param $contentId
     * @param $content
     * @param $operatorId
     * @return bool
     * @throws RunTimeException
     */
    public static function update($contentId, $content, $operatorId)
    {
        $contentData = CopyManageModel::getRecord(['id' => $contentId, 'status' => OperationActivityModel::ENABLE_STATUS_ON], ['id', 'title', 'type']);
        if (empty($contentData)) {
            return true;
        }
        $time = time();
        CopyManageModel::batchUpdateRecord(
            [
                'status' => OperationActivityModel::ENABLE_STATUS_DISABLE,
                'operator_id' => $operatorId,
                'update_time' => $time
            ],
            [
                'type' => $contentData['type'],
                'status[!]' => OperationActivityModel::ENABLE_STATUS_DISABLE
            ]);
        $addRes = CopyManageModel::batchInsert([
            'content' => trim(Util::textEncode($content)),
            'title' => $contentData['title'],
            'type' => $contentData['type'],
            'operator_id' => $operatorId,
            'create_time' => $time,
        ]);
        if (empty($addRes)) {
            throw new RunTimeException(['insert_failure']);
        }
        self::delRuleDescCache($contentData['type']);
        return $addRes;
    }

    /**
     * 获取文案列表
     * @param $params
     * @return array
     */
    public static function list($params)
    {
        $where = ['id[>]' => 0, 'status' => OperationActivityModel::ENABLE_STATUS_ON];
        if (!empty($params['content_id'])) {
            $where['id'] = $params['content_id'];
        }
        if (!empty($params['title'])) {
            $where['title[~]'] = $params['title'];
        }
        $data = CopyManageModel::list($where, $params['page'], $params['count']);
        if (!empty($data['list'])) {
            foreach ($data['list'] as &$dv) {
                $dv['content'] = Util::textDecode($dv['content']);
            }
        }
        return $data;
    }

    /**
     * 获取金叶子商城/魔法石商城规则说明文案
     * @param $type
     * @return mixed|string
     */
    public static function getRuleDesc($type)
    {
        $redisDb = RedisDB::getConn();
        $cacheData = $redisDb->get(CopyManageModel::RULE_DESC_CACHE_KEY_PRI . $type);
        if (empty($cacheData)) {
            $cacheData = self::setRuleDescCache($type);
        }
        return Util::textDecode($cacheData);
    }

    /**
     * 设置文案缓存
     * @param $type
     * @return mixed|string
     */
    private static function setRuleDescCache($type)
    {
        $redisDb = RedisDB::getConn();
        $dbData = CopyManageModel::getRecord(['type' => $type, 'status' => OperationActivityModel::ENABLE_STATUS_ON, 'ORDER' => ['id' => 'DESC']], ['id', 'content']);
        $content = '';
        $expireTime = 60;
        if (!empty($dbData['content'])) {
            $content = $dbData['content'];
            $expireTime = CopyManageModel::RULE_DESC_CACHE_KEY_EXPIRE;
        }
        $redisDb->setex(CopyManageModel::RULE_DESC_CACHE_KEY_PRI . $type, $expireTime, $content);
        return $content;
    }

    /**
     * 删除文案缓存
     * @param $type
     * @return mixed|string
     */
    private static function delRuleDescCache($type)
    {
        $redisDb = RedisDB::getConn();
        return $redisDb->del(CopyManageModel::RULE_DESC_CACHE_KEY_PRI . $type);
    }


}