<?php
/**
 * 白名单列表
 * User: yangpeng
 * Date: 2021/8/12
 * Time: 10:35 AM
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\Valid;
use App\Models\Dss\DssStudentModel;
use App\Models\WeekWhiteListModel;
use App\Models\WhiteRecordModel;

class WeekWhiteListService
{
    /**
     * 添加白名单
     * @param $uuids
     * @param $operator_id
     * @throws RunTimeException
     */
    public static function create($uuids, $operator_id){

        $errData = [];

        //检测是否重复
        $uniqueIds = array_unique($uuids);
        $repeatIds = array_values(array_diff_assoc($uuids, $uniqueIds));

        if($repeatIds){
            $errData['repeatIds'] = $repeatIds;
        }

        //检测是否存在
        $uuidList = DssStudentModel::getUuids($uniqueIds);
        $uuidList = array_column($uuidList, null, 'uuid');
        $diff = array_diff($uniqueIds, array_keys($uuidList));
        if($diff){
            $errData['not_exists'] = array_values($diff);
        }

        //检测是否添加过
        $exists = WeekWhiteListModel::getRecords(['uuid'=>$uniqueIds],'uuid');
        if($exists){
            $errData['exists'] = $exists;
        }
        if($errData){
            return ['errorList' => $errData];
        }

        $insert = [];
        $logData = [];
        $now = time();

        foreach ($uniqueIds as $uuid){

            $row = [
                'uuid'  => $uuid,
                'mobile' => $uuidList[$uuid]['mobile'],
                'operator_id'   => $operator_id,
                'create_time'   => $now,
                'status'        => WeekWhiteListModel::NORMAL_STATUS
            ];
            $insert[] = $row;

            $log = [
                'uuid' => $uuid,
                'mobile' => $row['mobile'],
                'operator_id' => $operator_id,
                'type'   => WhiteRecordModel::TYPE_ADD
            ];
            $logData[] = $log;
        }

        try{

            $db = MysqlDB::getDB();
            $db->beginTransaction();

            $insertWhite = WeekWhiteListModel::batchInsert($insert);
            if(!$insertWhite){
                throw new RunTimeException(['insert_failure'], $insert);
            }

            $insertRecord = WhiteRecordService::BatchCreate($logData);
            if(!$insertRecord){
                throw new RunTimeException(['insert_failure'], $logData);
            }

            $db->commit();
            return true;
        }catch (RunTimeException $e){
            $db->rollBack();
            return false;
        }


    }

    /**
     * 获取白名单
     * @param $params
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function getWhiteList($params, $page, $pageSize){

        $where = [
            'status' => WeekWhiteListModel::NORMAL_STATUS
        ];

        if(!empty($params['uuid'])){
            $where['uuid'] = $params['uuid'];
        }

        if(!empty($params['mobile'])){
            $where['mobile'] = $params['mobile'];
        }

        $total = WeekWhiteListModel::getCount($where);

        if ($total <= 0) {
            return [[], 0];
        }

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];

        $list = WeekWhiteListModel::getRecords($where);
        return compact('list', 'total');
    }

    /**
     * 删除白名单
     * @param $id
     * @param $operator_id
     * @return array|void
     */
    public static function del($id, $operator_id){
        $info = WeekWhiteListModel::getById($id);

        if(empty($info)){
            return false;
        }

        try {

            $db = MysqlDB::getDB();
            $db->beginTransaction();

            $edit = WeekWhiteListModel::updateRecord($id, ['status' => WeekWhiteListModel::DISABLE_STATUS, 'operator_id'=>$operator_id]);

            if(!$edit){
                throw new RunTimeException(['update_failure']);
            }

            $insert = WhiteRecordService::createOne($info['uuid'], $info['mobile'], WhiteRecordModel::TYPE_DEL, $operator_id);

            if(!$insert){
                throw new RunTimeException(['insert_failure']);
            }

            $db->commit();
            return true;
        }catch (RunTimeException $e){
            $db->rollBack();
            return false;
        }

    }


}
