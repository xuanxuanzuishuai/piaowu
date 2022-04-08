<?php

namespace App\Models;

class AbroadLaunchLeadsInputRecordModel extends Model
{
    public static $table = "abroad_launch_leads_input_record";

    const INPUT_STATUS_REPEAT  = 2;  // 手机号重复
    const INPUT_STATUA_SUCCESS = 1;  // 录入成功

    /**
     * 获取列表
     * @param $data
     * @param $page
     * @param $limit
     * @return array
     */
    public static function getList($data, $page, $limit)
    {
        $returnData = [
            'list' => [],
            'total_count' => 0,
        ];
        $where = [];
        if (!empty($data['app_id'])) {
            $where['app_id'] = $data['app_id'];
        }
        if (empty($where)) {
            return $returnData;
        }
        $returnData['total_count'] = self::getCount($where);
        if (empty($returnData['total_count'])) {
            return $returnData;
        }
        $where['LIMIT'] = [($page-1)*$limit, $limit];
        $where['ORDER'] = ['id' => 'DESC'];
        $returnData['list'] = self::getRecords($where);
        return $returnData;
    }
}
