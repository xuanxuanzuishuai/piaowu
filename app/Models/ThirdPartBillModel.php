<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/2/18
 * Time: 11:24 AM
 */

namespace App\Models;


use App\Libs\UserCenter;
use App\Models\Dss\DssChannelModel;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssErpPackageModel;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssStudentModel;

class ThirdPartBillModel extends Model
{
    public static $table = 'third_part_bill';
    const STATUS_SUCCESS = 1; // 请求成功
    const STATUS_FAIL = 2; // 请求失败
    const IS_NEW = 1; // 新注册用户
    const NOT_NEW = 2; // 老用户
    const IGNORE = 1; // 导入数据时忽略当前行标记
    //1 新产品包 0 旧产品包
    const PACKAGE_V1 = 1;
    const PACKAGE_V1_NOT = 0;
    //第三方在小叶子系统中的身份类型
    const THIRD_IDENTITY_TYPE_AGENT = 1;//1代理商

    /**
     * 获取订单列表
     * @param $where
     * @param $map
     * @param $page
     * @param $count
     * @param $thirdIdentityTableName
     * @return array
     */
    public static function list($where, $map, $page, $count, $thirdIdentityTableName)
    {
        //获取从库对象
        $db = self::dbRO();
        //获取数据表名
        $opThirdPartBillTable = self::getTableNameWithDb();
        $opEmployeeTable = EmployeeModel::getTableNameWithDb();
        $dssStudentTable = DssStudentModel::getTableNameWithDb();
        $dssEmployeeTable = DssEmployeeModel::getTableNameWithDb();
        $dssErpPackageTable = DssErpPackageModel::getTableNameWithDb();
        $dssErpPackageV1Table = DssErpPackageV1Model::getTableNameWithDb();
        $dssChannelTable = DssChannelModel::getTableNameWithDb();
        $opAgentTable = AgentModel::getTableNameWithDb();
        $offset = ($page - 1) * $count;
        $data = ['total_count' => 0, 'records' => []];

        //根据第三方身份的不同类型关联不同的表去查询数据
        $thirdJoinWhere = '';
        if (!empty($thirdIdentityTableName)) {
            //代理商
            $thirdJoinWhere = 'LEFT JOIN  ' . $thirdIdentityTableName .
                ' AS third ON t.third_identity_id=third.id';
        }
        //数据总量
        $countSql = "SELECT count(t.id) as total_count
                   FROM " . $opThirdPartBillTable . " t
                   LEFT JOIN " . $dssStudentTable . " s ON t.student_id = s.id 
                   WHERE " . $where;
        $countData = $db->queryAll($countSql, $map);
        if (empty($countData[0]['total_count'])) {
            return $data;
        }
        $data['total_count'] = $countData[0]['total_count'];
        //数据列表
        $listSql = "SELECT 
                        t.id,
                        t.student_id,
                        t.business_id,
                        s.name,
                        s.mobile,
                        t.trade_no,
                        t.is_new,
                        t.pay_time,
                        t.status,
                        t.reason,
                        t.create_time,
                        t.package_id,
                        t.parent_channel_id,
                        t.channel_id,
                        case 
                            when t.third_identity_type = " . self::THIRD_IDENTITY_TYPE_AGENT . " then (select name from " . $opAgentTable . " where id = t.third_identity_id)
                        else ' '
                        end third_name,
                        case 
                            when t.business_id = " . UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT . " then (select name from " . $dssEmployeeTable . " where id = t.operator_id)
                            when t.business_id = " . UserCenter::AUTH_APP_ID_OP . " then (select name from  " . $opEmployeeTable . " where id = t.operator_id)
                        end operator_name,
                        case 
                            when t.package_v1 = " . self::PACKAGE_V1_NOT . " then (select name from " . $dssErpPackageTable . " where id = t.package_id)
                            when t.package_v1 = " . self::PACKAGE_V1 . " then (select name from  " . $dssErpPackageV1Table . " where id = t.package_id)
                        end package_name,
                        c.name channel_name
                    FROM " . $opThirdPartBillTable . " t
                       LEFT JOIN " . $dssStudentTable . " s on t.student_id = s.id
                       LEFT JOIN " . $dssChannelTable . " c on c.id = t.channel_id
                       " . $thirdJoinWhere . "
                    WHERE " . $where . "
                    ORDER BY t.id DESC
                    LIMIT " . $offset . "," . $count;
        $data['records'] = $db->queryAll($listSql, $map);
        return $data;
    }
}