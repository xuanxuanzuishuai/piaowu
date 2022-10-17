<?php
/**
 * 清晨学生微信表
 * author: qingfeng.lian
 * date: 2022/10/11
 */

namespace App\Models\Morning;

use App\Libs\Constants;

class MorningUserWechatModel extends MorningModel
{
    public static $table = 'user_wechat';

    /**
     * 获取清晨学生当前绑定的openid
     * @param $uuids
     * @param $fields
     * @return array
     */
    public static function getMorningStudentWechatOpenIds($uuids, $fields)
    {
        if (empty($uuids)) {
            return [];
        }
        $list = self::getRecords(
            [
                'user_uuid'   => $uuids,
                'user_type'   => 1,
                'bind_status' => 1,
                'busi_type'   => 1,
                'app_id'      => Constants::QC_APP_ID,
            ],
            $fields
        );
        return is_array($list) ? $list : [];
    }
}