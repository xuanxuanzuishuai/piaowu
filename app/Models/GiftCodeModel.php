<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2018/10/26
 * Time: 3:56 PM
 */

namespace App\Models;

use App\Libs\MysqlDB;
use App\Libs\Util;

class GiftCodeModel extends Model
{
    public static $table = "gift_code";

    /**
     * 生成方式
     * 1系统生成，2手工生成
     */
    const SYSTEM_CREATE = 1;
    const MANUAL_CREATE = 2;

    /**
     * 生成渠道
     * 1机构，2个人，3其他
     */
    const AGENT_CHANNEL = 1;
    const PANDA_CHANNEL = 2;
    const OTHER_CHANNEL = 3;

    /**
     * 兑换码状态
     * 0 未兑换
     * 1 已兑换
     * 2 已作废
     */
    const CODE_STATUS_NOT_REDEEMED = 0;
    const CODE_STATUS_HAS_REDEEMED = 1;
    const CODE_STATUS_INVALID = 2;

    /**
     * 激活码时间单位
     * 1 天
     * 2 月
     * 3 年
     */
    const CODE_TIME_DAY = 1;
    const CODE_TIME_MONTH = 2;
    const CODE_TIME_YEAR = 3;

    const DEFAULT_DEADLINE_UNIT = 2; //默认激活码有效期单位 月
    const DEFAULT_DEADLINE_NUMBER = 12; //默认激活时间 12
    const DEFAULT_NUM = 1;//默认激活数量


    /**
     * @param $params
     * @return array
     * 获取是否已存在激活码
     */
    public static function getCodeInfo($params)
    {
        $where = [];
        if (!empty($params['code'])) {
            $where = [
                'code' => $params['code']
            ];
        }
        $db = MysqlDB::getDB();
        $result = $db->select(self::$table, [
            "id",
            "code",
        ], $where);
        return $result;
    }

    /**
     * 插入激活码
     * @param $params
     * @return int|mixed|null|string
     */
    public static function InsertCodeInfo($params)
    {
        $data = [];
        $data['code'] = $params['code'];
        $data['generate_channel'] = $params['generate_channel'];
        $data['buyer'] = $params['buyer'];
        $data['buy_time'] = $params['buy_time'];
        $data['valid_num'] = $params['valid_num'];
        $data['valid_units'] = $params['valid_units'];
        $data['generate_way'] = $params['generate_way'];
        $data['operate_user'] = $params['operate_user'];
        $data['create_time'] = $params['create_time'];
        $data['operate_time'] = $params['operate_time'];
        $data['remarks'] = $params['remarks'];
        return MysqlDB::getDB()->insertGetID(self::$table, $data);
    }

    /**
     * @param $params
     * @return array
     * 模糊获取激活码
     */
    public static function getLikeCodeInfo($params)
    {
        $where = ' where 1 = 1 ';
        if (!empty($params['code'])) {
            $where .= " and erp_code.code like '%{$params['code']}%'";
        }

        if (!empty($params['generate_channel'])) {
            $where .= " and erp_code.generate_channel = " . $params['generate_channel'];
        }

        if (!empty($params['generate_way'])) {
            $where .= " and erp_code.generate_way = " . $params['generate_way'];
        }

        if (!empty($params['code_status'])) {
            $where .= " and erp_code.code_status = " . $params['code_status'];
        }

        if (!empty($params['agent_name'])) {
            $where .= " and erp_agent.agent_name like '%{$params['agent_name']}%'";
        }

        $db = MysqlDB::getDB();

        $join = "left join erp_agent on erp_code.buyer = erp_agent.id and erp_code.generate_channel = " . self::AGENT_CHANNEL . " 
      left join erp_student on erp_code.buyer = erp_student.id and erp_code.generate_channel = " . self::PANDA_CHANNEL . "
      left join erp_student apply_user on erp_code.apply_user = apply_user.id";
        $totalCount = self::getCodeCount($join, $where);
        //格式化分页参数
        list($page, $count) = Util::formatPageCount($params);
        $offset = ($page - 1) * $count;

        $sql = "select erp_code.id, erp_code.code, erp_code.generate_channel, erp_code.generate_way, erp_code.code_status,
                  erp_code.buyer, erp_code.buy_time, erp_code.apply_user, erp_code.valid_num, erp_code.valid_units, erp_code.be_active_time,
                  erp_code.operate_user, erp_code.operate_time, erp_student.name, erp_student.mobile, erp_agent.agent_name, 
                  apply_user.name apply_name, apply_user.mobile apply_mobile
      from erp_code {$join} {$where} order by erp_code.id DESC";
        $sql .= " limit $offset, $count";
        return [$totalCount, $db->queryAll($sql)];
    }


    /**
     * @param $join
     * @param $where
     * @return mixed
     * 求所需总数
     */
    public static function getCodeCount($join, $where)
    {
        $code = self::$table;
        $db = MysqlDB::getDB();

        $query = "SELECT count(*) AS count FROM {$code}";
        if (!empty($where)) {
            $query = "SELECT count(*) AS count FROM {$code} {$join} {$where}";
        }

        $count = $db->queryAll($query);
        return $count[0]['count'];
    }

    /**
     * 获取激活码信息
     * @param $code
     * @return mixed
     */
    public static function getByCode($code)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['code' => $code]);
    }
}