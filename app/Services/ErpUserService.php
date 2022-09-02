<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/03/09
 * Time: 上午10:47
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\MysqlDB;
use App\Libs\Util;
use App\Models\Erp\ErpCourseModel;
use App\Models\Erp\ErpGenericWhitelistModel;
use App\Models\Erp\ErpStudentAppModel;
use App\Models\Erp\ErpStudentCourseExtModel;
use App\Models\Erp\ErpStudentCourseModel;
use App\Models\Erp\ErpStudentCourseTmpModel;
use App\Models\Erp\ErpStudentModel;

class ErpUserService
{
    //学生账户子类型
    const ACCOUNT_SUB_TYPE_STUDENT_POINTS = 3001;//学生积分余额
    const ACCOUNT_SUB_TYPE_CASH           = 1001; // 学生现金账户

    const ACCOUNT_SUB_TYPE_GOLD_LEAF = 3002; // 学生金叶子账户

    /**
     * 获取学生账户信息
     * @param string $uuid
     * @param int|array $subType
     * @return float|int|array|mixed
     */
    public static function getStudentAccountInfo(string $uuid, $subType = self::ACCOUNT_SUB_TYPE_CASH)
    {
        $subTypes = [];
        if (is_array($subType)) {
            $ret = [];
            foreach ($subType as $value) {
                $ret[$value] = 0;
            }
            $subTypes = $subType;
        } else {
            $ret = 0;
            $subTypes[] = $subType;
        }

        $erp = new Erp();
        $accounts = $erp->studentAccount($uuid);
        if (!isset($accounts['code']) || empty($accounts['data'])) {
            return $ret;
        }
        $accounts = array_column($accounts['data'], null, 'sub_type');

        $data = [];

        foreach ($subTypes as $item) {
            if (!isset($accounts[$item])) {
                continue;
            }
            $account = $accounts[$item];

            if ($item == self::ACCOUNT_SUB_TYPE_CASH) {
                $data[$item] = ($account['total_num'] * 100 - $account['out_time_num'] * 100) ?? 0;
            } else {
                $data[$item] = $account['total_num'] ?? 0;
            }
        }

        if (is_array($subType)) return $data;

        return $data[$subType] ?? 0;
    }

    /**
     * 获取学生头像展示地址:支持批量获取
     * @param $thumbs
     * @return string|string[]
     */
    public static function getStudentThumbUrl($thumbs)
    {
        $dictConfig = array_column(DictConstants::getErpDictArr(DictConstants::ERP_SYSTEM_ENV['type'], ['QINIU_DOMAIN_1', 'QINIU_FOLDER_1', 'student_default_thumb'])[DictConstants::ERP_SYSTEM_ENV['type']], 'value', 'code');
        foreach ($thumbs as $tk => $tv) {
            if (empty($tv)) {
                $thumbUrl = Util::getQiNiuFullImgUrl($dictConfig['student_default_thumb'], $dictConfig['QINIU_DOMAIN_1'], $dictConfig['QINIU_FOLDER_1']);
            } else {
                $thumbUrl = Util::getQiNiuFullImgUrl($tv, $dictConfig['QINIU_DOMAIN_1'], $dictConfig['QINIU_FOLDER_1']);

            }
            $thumbs[$tk] = $thumbUrl;
        }
        return $thumbs;
    }

    /**
     * 获取学生默认名称
     * @param $mobile
     * @return string
     */
    public static function getStudentDefaultName($mobile)
    {
        return '宝贝' . substr($mobile, 7, 4);
    }

    /**
     * 获取真人学生的付费状态
     * @param $studentId
     * @return array
     */
    public static function getStudentStatus($studentId)
    {
        //检测学生付费状态
        $studentAppData = ErpStudentAppModel::getRecord(['student_id' => $studentId, 'app_id' => Constants::REAL_APP_ID], ['status']);
        $payStatusData['pay_status'] = $studentAppData['status'];
        //付费用户剩余正式课程数量
        if ($studentAppData['status'] == ErpStudentAppModel::STATUS_PAID) {
            $norCourseRemainNum = ErpCourseService::getUserRemainCourseNum($studentId, ErpCourseModel::TYPE_NORMAL);
            if ($norCourseRemainNum <= 0) {
                $payStatusData['pay_status'] = ErpStudentAppModel::STATUS_PAID_NO_REMAINING_COURSES;
            }
        }
        //白名单认为付费
        $res = UserService::checkRealStudentIdentityIsNormal($studentId);
        if ($res) {
            $payStatusData['pay_status'] = $studentAppData['status'] = strval(ErpStudentAppModel::STATUS_PAID);
        }
        $payStatusData['status_zh'] = ErpStudentAppModel::$statusMap[$studentAppData['status']];
        return $payStatusData;
    }

    /**
     * 获取当前所有有付费正式课剩余课程数的学生列表
     * @param $studentId
     * @return array
     */
    public static function getIsPayAndCourseRemaining($studentId = 0): array
    {
        $stuTable = ErpStudentModel::getTableNameWithDb();
        $stuAppTable = ErpStudentAppModel::getTableNameWithDb();
        $courseTable = ErpStudentCourseModel::getTableNameWithDb();
        $courseExtTable = ErpStudentCourseExtModel::getTableNameWithDb();
        // $cleanTable = ErpStudentCourseTmpModel::getTableNameWithDb();
        // $sql = "select s.id,s.uuid,s.country_code,a.first_pay_time,cl.create_time as clean_time" .
        $sql = "select s.id,s.uuid,s.country_code,a.first_pay_time" .
            " from $stuTable s " .
            " inner join $stuAppTable a on a.student_id = s.id and a.app_id = 1" .
            " inner join $courseTable c on s.id = c.student_id and c.`status` = 1" .
            " left JOIN $courseExtTable e on c.id = e.student_course_id" .
            // " left join $cleanTable cl on cl.student_id=s.id" .
            " where" .
            // 付费用户
            " a.first_pay_time > 0 " .
            // 付费正式课
            " and c.business_type=1 and json_extract(c.business_tag,'$.price') >0 and json_search(c.business_tag,'one', 'free_type') IS NULL" .
            // 有剩余付费正式课程数量
            " and (c.lesson_count > 0 or e.lesson_decimal > 0)";
        if (!empty($studentId)) {
            $sql .= ' and s.id='. $studentId . ' limit 1';
        }
        $list = MysqlDB::getDB()->queryAll($sql);
        return is_array($list) ? $list : [];
    }
}
