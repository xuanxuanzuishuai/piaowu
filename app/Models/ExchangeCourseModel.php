<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:37 PM
 */

namespace App\Models;


class ExchangeCourseModel extends Model
{
    public static $table = 'exchange_course';

    const IGNORE = 1; // 导入数据时忽略当前行标记

    // 导入状态 1待处理 2待兑换 3已兑换 4已删除 5兑换异常 6处理异常
    const STATUS_READY_HANDLE = 1;
    const STATUS_READY_EXCHANGE = 2;
    const STATUS_EXCHANGE_SUCCESS = 3;
    const STATUS_DELETE = 4;
    const STATUS_EXCHANGE_FAIL = 5;
    const STATUS_HANDLE_FAIL = 6;

    /**
     * 获取导入记录列表
     * @param $where
     * @param $page
     * @param $count
     * @return array
     */
    public static function list($where, $page, $count)
    {
        $data = ['total_count' => 0, 'list' => []];
        $totalNum = self::getCount($where);
        if (empty($totalNum)) {
            return $data;
        }
        $offset = ($page - 1) * $count;
        $where['ORDER'] = ['id' => 'DESC'];
        $where['LIMIT'] = [$offset, $count];
        $data['total_count'] = $totalNum;
        $data['list'] = self::getRecords($where, [
            'id',
            'import_source',
            'channel_id',
            'country_code',
            'mobile',
            'uuid',
            'status',
            'package_id',
            'create_time',
            'operator_name',
            'update_time',
        ]);
        return $data;
    }
}