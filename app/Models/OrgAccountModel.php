<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/24
 * Time: 2:58 PM
 */

namespace App\Models;


use App\Libs\MysqlDB;

/**
 * 机构账号
 * 密码保存账号和明文密码拼接后的md5
 *
 * Class OrgAccountModel
 * @package App\Models
 */
class OrgAccountModel extends Model
{
    static $table = 'org_account';

    const STATUS_STOP = 0; //停用
    const STATUS_NORMAL = 1; //正常

    public static function getByAccount($account)
    {
        $db = MysqlDB::getDB();
        return $db->get(self::$table, '*', ['account' => $account]);
    }

    public static function getMaxAccount()
    {
        $db = MysqlDB::getDB();
        return $db->max(self::$table, 'account');
    }

    public static function selectByPage($page, $count, $params)
    {
        $where = [
            'LIMIT' => [($page-1) * $count, $count],
            'ORDER' => ['oa.create_time' => 'DESC'],
        ];

        if(!empty($params['account'])) {
            $where['oa.account'] = $params['account'];
        }
        //license_num=0也是一种状态，所以用isset
        if(isset($params['license_num'])) {
            $where['oa.license_num'] = $params['license_num'];
        }
        if(!empty($params['org_id'])) {
            $where['o.id'] = $params['org_id'];
        }

        $db = MysqlDB::getDB();

        $records = $db->select(self::$table . '(oa)',[
            '[><]' . OrganizationModel::$table . '(o)' => ['oa.org_id' => 'id']
        ],[
            'oa.org_id',
            'oa.id',
            'o.name(org_name)',
            'oa.account',
            'oa.status',
            'oa.license_num',
            'oa.last_login_time',
        ], $where);

        $total = $db->count(self::$table . '(oa)', [
            '[><]' . OrganizationModel::$table . '(o)' => ['oa.org_id' => 'id']
        ], ['oa.id'] ,$where);

        return [$records, $total];
    }
}