<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/28
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Models\WeekWhiteListModel;
use App\Models\WhiteRecordModel;

class WhiteRecordService
{

    public static function BatchCreate($data){
        return WhiteRecordModel::batchInsert($data);
    }

    public static function createOne($uuid, $mobile, $type, $operator_id){
        $data = [
            'uuid'  => $uuid,
            'mobile'=> $mobile,
            'type'  => $type,
            'operator_id' => $operator_id,
            'create_time' => time(),
        ];

        return WhiteRecordModel::insertRecord($data);
    }

    public static function list($params, $page, $pageSize){

        $where = [];
        if(!empty($params['uuid'])){
            $where['uuid'] = $params['uuid'];
        }

        if(!empty($params['mobile'])){
            $where['mobile'] = $params['mobile'];
        }

        $total = WhiteRecordModel::getCount($where);

        if ($total <= 0) {
            return [[], 0];
        }

        $where['LIMIT'] = [($page - 1) * $pageSize, $pageSize];
        $list = WhiteRecordModel::getRecords($where);

        foreach ($list as &$one){
            $one['type_text'] = WhiteRecordModel::$types[$one['type']];
        }
        return compact('list', 'total');


    }
}
