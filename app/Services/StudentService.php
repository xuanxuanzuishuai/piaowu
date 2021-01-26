<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:35 PM
 */

namespace App\Services;



use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;

class StudentService
{
    /**
     * 学生当前状态
     * @param $studentId
     * @return array
     * @throws RunTimeException
     */
    public static function dssStudentStatusCheck($studentId)
    {
        //获取学生信息
        $studentInfo = DssStudentModel::getRecord(['id' => $studentId]);
        $data = [];
        if (empty($studentInfo)) {
            throw new RunTimeException(['student_not_exist']);
        }
        $data['student_info'] = $studentInfo;
        //查看学生是否绑定账户
        $userIsBind = DssUserWeiXinModel::getRecord([
            'user_id' => $studentId,
            'status' => DssUserWeiXinModel::STATUS_NORMAL,
            'user_type' => DssUserWeiXinModel::USER_TYPE_STUDENT,
            'busi_type' => DssUserWeiXinModel::BUSI_TYPE_STUDENT_SERVER,
        ], ['id']);
        if (empty($userIsBind)) {
            //未绑定
            $data['student_status'] = DssStudentModel::STATUS_UNBIND;
        } else {
            $data['student_status'] = $studentInfo['has_review_course'];
        }
        //返回数据
        return $data;
    }

    /**
     * 获取学生头像展示地址
     * @param $thumb
     * @return string|string[]
     */
    public static function getStudentThumb($thumb)
    {
        return !empty($thumb) ? AliOSS::replaceCdnDomainForDss($thumb) : AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb'));
    }
}
