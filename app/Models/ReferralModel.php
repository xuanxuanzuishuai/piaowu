<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/21
 * Time: 2:43 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

class ReferralModel extends Model
{
    protected static $table = 'referral';

    /** 分享类型 */
    const REFERRAL_TYPE_WX_SHARE = 1; // 微信分享页面

    const REFERRER_DEFAULT_LIMIT = 100; // 推荐列表默认最大返回数据

    /**
     * 获取被推荐人的推荐关系
     * 被推荐人同类型只有一条推荐关系
     * @param int $refereeId 被推荐人id
     * @param int $type 分享类型
     * @return mixed
     */
    public static function getByRefereeId($refereeId, $type)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['referee_id' => $refereeId, 'type' => $type]);
    }

    /**
     * 获取推荐人的推荐列表
     * @param int $referrerId 推荐人id
     * @param int $type 分享类型
     * @return array
     */
    public static function getListByReferrerId($referrerId, $type)
    {
        $db = MysqlDB::getDB();

        $stu = StudentModel::$table;

        return $db->select(self::$table . '(ref)',
            ["[>]{$stu}(referee)" => ['ref.referee_id' => 'id']],
            [
                'ref.id',
                'ref.referrer_id',
                'ref.referee_id',
                'ref.type',
                'ref.create_time',
                'ref.given_rewards',
                'ref.given_rewards_time',
                'referee.name(referee_name)',
                'referee.mobile(referee_mobile)'
            ], [
                'ref.referrer_id' => $referrerId,
                'ref.type' => $type,
                'ORDER' => ['create_time' => 'DESC'],
                'LIMIT' => self::REFERRER_DEFAULT_LIMIT
            ]
        );
    }
}