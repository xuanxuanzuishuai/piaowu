<?php
/**
 * 清晨转介绍关系表
 */

namespace App\Models;

class MorningReferralStatisticsModel extends Model
{
    public static $table = 'morning_referral_statistics';

    /**
     * 批量获取转介绍人数
     * @param $refereeUuids
     * @param array $fields
     * @return array|null
     */
    public static function getReferralCountList($refereeUuids, $fields = [])
    {
        $list = [];
        if (empty($refereeUuids)) {
            return $list;
        }
        $ids = array_chunk($refereeUuids, 200);
        foreach ($ids as $_ids) {
            $tmpData = self::getRecords([
                'referee_student_uuid' => $_ids,
                'GROUP'                => ['referee_student_uuid'],
            ], $fields);
            $list = array_merge($list,$tmpData);
        }

        return $list;
    }
}