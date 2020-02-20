<?php
/**
 * Created by PhpStorm.
 * User: lianglipeng
 * Date: 2020/2/11
 * Time: 2:32 PM
 */

namespace App\Services;

use App\Libs\MysqlDB;
use App\Models\WeChatConfigModel;
use App\Libs\Util;

class WeChatConfigService
{
    /**
     * 添加配置公众号事件推送内容
     * @param $insertData
     * @return int|mixed|null|string
     */
    public static function addWechatConfig($insertData)
    {
        //写入数据
        $id = WeChatConfigModel::insertRecord($insertData);
        //返回结果
        return $id;
    }

    /**
     * @param $id
     * @param $updateData
     * @return int|mixed|null|string
     */
    public static function updateWechatConfig($id, $updateData)
    {
        $affectRows = WeChatConfigModel::updateRecord($id, $updateData);
        //返回结果
        return $affectRows;
    }

    /**
     * @param $where
     * @param $fields
     * @return int|mixed|null|string
     */
    public static function getWechatConfigDetail($where, $fields = [])
    {
        //写入数据
        $data = WeChatConfigModel::getRecord($where, $fields, false);
        if($data){
            $data['content'] = Util::textDecode($data['content']);
        }
        //返回结果
        return $data;
    }

    /**
     * 获取列表
     * @param $params
     * @param $page
     * @param $count
     * @return int|mixed|null|string
     */
    public static function getWechatConfigList($params, $page = 1, $count = 20)
    {
        //搜索条件
        $where = $data = [];
        if ($params['type']) {
            $where['type'] = $params['type'];
        }
        //获取数量
        $db = MysqlDB::getDB();
        $dataCount = $db->count(WeChatConfigModel::$table, $where);
        if($dataCount>0){
            //获取数据
            $where['LIMIT'] = [($page - 1) * $count, $count];
            $data = WeChatConfigModel::getRecords($where, ['id', 'type','content'], false);
            if($data){
                foreach ($data as &$val){
                    $val['content'] = Util::textDecode($val['content']);
                }
            }
        }
        //返回结果
        return [$dataCount, $data];
    }
}
