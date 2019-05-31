<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/11/4
 * Time: 19:58
 *
 * 客户相关数据service
 */

namespace App\Services;


use App\Libs\Util;
use App\Models\FollowRemarkModel;

class StudentFollowUpService
{
    //默认分页条数
    const DEFAULT_COUNT = 20;

    /**
     * 添加学生的跟进记录
     * @param $params
     * @return array
     */
    public static function addStudentFollowRemark($params)
    {
        $data['user_id'] = $params['user_id'];
        $data['operator_id'] = $params['operator_id'];
        $data['remark'] = Util::filterEmoji($params['remark']);
        $data['create_time'] = time();
        return FollowRemarkModel::insertRecord($data);
    }

    /**
     * @param $userId
     * @param $page
     * @param $count
     * @return array
     * 查看学生的跟进记录
     */
    public static function lookStudentFollowRemark($userId, $page, $count)
    {
        $log = FollowRemarkModel::getRemarkLog([
            'user_id' => $userId,
            "LIMIT" => [($page - 1) * $count, $count],
        ]);
        $data = [];
        if (!empty($log)) {
            foreach ($log as $value) {
                $value['create_time'] = date('Y-m-d H:i:s', $value['create_time']);
                $data[] = $value;
            }
        }
        return [
            $data,
            FollowRemarkModel::getCountNum(['user_id' => $userId])
            ];
    }
}