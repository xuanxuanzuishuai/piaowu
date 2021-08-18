<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/8/13
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Models\Dss\DssStudentModel;
use App\Models\WeekWhiteListModel;
use App\Models\WhiteRecordModel;

class WhiteRecordService
{

    public static function BatchCreate($data){
        return WhiteRecordModel::batchInsert($data);
    }

    public static function createOne($uuid, $type, $operator_id){
        $data = [
            'uuid'  => $uuid,
            'type'  => $type,
            'operator_id' => $operator_id,
            'create_time' => time(),
        ];

        return WhiteRecordModel::insertRecord($data);
    }

    /**
     * 获取操作记录列表
     * @param $params
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function list($params, $page, $pageSize){

        $where = [];
        if(!empty($params['uuid'])){
            $where['uuid'] = $params['uuid'];
        }

        if(!empty($params['mobile'])){
            $studentInfo = DssStudentModel::getRecord(['mobile'=>$params['mobile']],['id','uuid','mobile']);
            $where['uuid'] = $studentInfo['uuid'] ?? 0;
        }

        $total = WhiteRecordModel::getCount($where);

        if ($total <= 0) {
            return ['list'=>[], 'total'=>0];
        }

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
        $list = WhiteRecordModel::getRecords($where);
        $list = WeekWhiteListService::initList($list);

        return compact('list', 'total');
    }
}
