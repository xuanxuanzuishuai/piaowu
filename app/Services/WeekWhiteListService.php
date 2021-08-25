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
use App\Libs\Util;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\EmployeeModel;
use App\Models\WeekWhiteListModel;
use App\Models\WhiteRecordModel;

class WeekWhiteListService
{
    /**
     * 添加白名单
     * @param $uuids
     * @param $operator_id
     * @return array[]|bool
     */
    public static function create($uuids, $operator_id){
        //检测是否重复
        $uniqueIds = array_unique($uuids);
        $repeatIds = array_values(array_diff_assoc($uuids, $uniqueIds));
        $errData['repeatIds'] = $repeatIds;


        //检测是否存在
        $uuidList = DssStudentModel::getUuids($uniqueIds);
        $uuidList = array_column($uuidList, null, 'uuid');
        $diff = array_diff($uniqueIds, array_keys($uuidList));
        $errData['not_exists'] = array_values($diff);


        //检测是否添加过
        $exists = WeekWhiteListModel::getRecords(['uuid'=>$uniqueIds, 'status'=>WeekWhiteListModel::NORMAL_STATUS],'uuid');

        $errData['exists'] = $exists;

        if($errData['repeatIds'] || $errData['not_exists'] || $errData['exists']){
            return ['errorList' => $errData];
        }

        $insert = [];
        $logData = [];
        $now = time();

        foreach ($uniqueIds as $uuid){

            $row = [
                'student_id' => $uuidList[$uuid]['id'],
                'uuid'  => $uuid,
                'operator_id'   => $operator_id,
                'create_time'   => $now,
                'status'        => WeekWhiteListModel::NORMAL_STATUS
            ];
            $insert[] = $row;

            $log = [
                'uuid' => $uuid,
                'operator_id' => $operator_id,
                'type'   => WhiteRecordModel::TYPE_ADD,
                'create_time'   => $now,
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
            $studentInfo = DssStudentModel::getRecord(['mobile'=>$params['mobile']],['id','uuid','mobile']);
            $where['student_id'] = $studentInfo['id'] ?? 0;
        }

        $total = WeekWhiteListModel::getCount($where);

        if ($total <= 0) {
            return ['list'=>[], 'total'=>0];
        }

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
        $where['ORDER'] = ['id'=>'DESC'];
        $list = WeekWhiteListModel::getRecords($where);

        $list = self::initList($list);

        return compact('list', 'total');
    }

    public static function initList($list){

        $uuids = array_column($list, 'uuid');
        $students = DssStudentModel::getUuids($uuids);

        $students = array_column($students, null, 'uuid');
        $course_manage_ids = array_column($students, 'course_manage_id');
        $operator_ids = array_column($list, 'operator_id');
        $operator_ids = array_unique(array_merge($course_manage_ids, $operator_ids));
        $employees = DssEmployeeModel::getRecords(['id'=>$operator_ids],['id','name']);
        $employees = array_column($employees, null, 'id');


        foreach ($list as &$one){
            $one['mobile']          = Util::hideUserMobile($students[$one['uuid']]['mobile'] ?? '');
            $one['operator_name']   = $employees[$one['operator_id']]['name'] ?? '系统';
            $one['student_id']      = $students[$one['uuid']]['id'];
            $one['course_manage_name'] = $employees[$one['course_manage_id']]['name'] ?? '';

            if(isset($one['type'])){
                $one['type_text'] = WhiteRecordModel::$types[$one['type']];
            }

            if(isset($one['grant_money'])){
                $one['grant_money'] = $one['grant_money'] / 100;
            }
        }

        return $list;
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

            $insert = WhiteRecordService::createOne($info['uuid'], WhiteRecordModel::TYPE_DEL, $operator_id);

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
