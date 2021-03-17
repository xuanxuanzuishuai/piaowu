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
use App\Libs\PhpMail;
use App\Libs\SimpleLogger;
use App\Libs\Spreadsheet;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssErpPackageV1Model;
use App\Models\Dss\DssGiftCodeModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\Erp\ErpStudentModel;
use App\Models\StudentAccountAwardPointsFileModel;
use App\Services\Queue\StudentAccountAwardPointsTopic;
use I18N\Lang;

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

    /**
     * 搜索学生数据
     * @param $params
     * @return array|mixed
     */
    public static function searchStudentList($params)
    {
        if (!empty($params['id'])) {
            $where['id'] = $params['id'];
        }
        if (!empty($params['mobile'])) {
            $where['mobile'] = $params['mobile'];
        }
        if (!empty($params['uuid'])) {
            $where['uuid'] = $params['uuid'];
        }
        if (!empty($params['name'])) {
            $where['name[~]'] = $params['name'];
        }
        if (empty($where)) {
            return [];
        }
        return DssStudentModel::getRecords($where, ['name', 'uuid', 'mobile', 'id']);
    }


    /**
     * 获取学生购买课包列表: 体验课/正式课
     * @param $studentId
     * @return mixed
     */
    public static function getStudentGiftCodeList($studentId)
    {
        //旧产品包列表
        $oldPackageIdArr = DssPackageExtModel::getPackages(['package_type' => [DssPackageExtModel::PACKAGE_TYPE_NORMAL, DssPackageExtModel::PACKAGE_TYPE_TRIAL], 'app_id' => DssPackageExtModel::APP_AI]);
        //新产品包列表
        $v1PackageIdArr = DssErpPackageV1Model::getPackageIds([DssCategoryV1Model::DURATION_TYPE_TRAIL, DssCategoryV1Model::DURATION_TYPE_NORMAL]);
        $packageIdArr = array_merge($oldPackageIdArr, $v1PackageIdArr);
        $giftCodeList = DssGiftCodeModel::getRecords(['buyer' => $studentId, 'bill_package_id' => array_column($packageIdArr, 'package_id')], ['id', 'bill_package_id']);
        if (empty($giftCodeList)){
            return [];
        }
        $packageType = array_column($packageIdArr, null, 'package_id');
        foreach ($giftCodeList as &$v)
        {
            $v['package_type'] = $packageType[$v['bill_package_id']]['sub_type'];
        }
        return $giftCodeList;
    }

    /**
     * 保存上传的批量发放积分奖励的excel
     * @param $localFilePath
     * @param $params
     * @return false
     * @throws RunTimeException
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     */
    public static function saveUploadExcel($localFilePath, $params)
    {
        $time = time();
        //获取xml数据

        $sheetData = Spreadsheet::getActiveSheetData($localFilePath);
        $excelTitle = array_shift($sheetData);
        // 超过1w条发送邮件
        if (count($excelTitle) > 10000) {
            list($toMail, $title) = DictConstants::get(DictConstants::AWARD_POINTS_SEND_MAIL_CONFIG, ['to_mail', 'err_title']);
            PhpMail::sendEmail($toMail, $title, 'excel内容超过1万条，请分批导入');
            throw new RunTimeException(['excel_max_line']);
        }
        // 校验否有不符合规则的手机号 - 11位纯数字
        $errData = [];
        foreach ($sheetData as $_time) {
            $_mobile = $_time['B'];
            if (is_null($_mobile) || empty($_mobile)) {
                continue;
            }
            if (strlen((string)$_mobile) != 11) {
                $errData[] = self::formatErrData($_time['A'], $_mobile, 'mobile_len_err');
            }
            $num = intval($_time['C']);
            // 不是数字字符串， 并且intval之后与原数不符， 小于0 都表示不是正整数
            if (!is_numeric($_time['C']) || $num != $_time['C'] || $_time['C'] < 0) {
                $errData[] = self::formatErrData($_time['A'], $_mobile, 'account_award_points_num_is_int');
            }
        }
        if (!empty($errData)) {
            $content = self::createRepeatDataMailContent($errData);
            list($toMail, $title) = DictConstants::get(DictConstants::AWARD_POINTS_SEND_MAIL_CONFIG, ['to_mail', 'err_title']);
            PhpMail::sendEmail($toMail, $title, $content);
            throw new RunTimeException(['excel_data_exist_err_data'],['err_data' => $errData]);
        }
        // 校验数据是否有重复
        $uuidArr = array_diff(array_column($sheetData,'A'),[null]);
        $mobileArr = array_diff(array_column($sheetData,'B'),[null]);
        $repeatArr = self::checkRepeatUuidMobile(array_values($uuidArr), $mobileArr);
        if (!empty($repeatArr)) {
            // 发送邮件
            $content = self::createRepeatDataMailContent($repeatArr);
            list($toMail, $title) = DictConstants::get(DictConstants::AWARD_POINTS_SEND_MAIL_CONFIG, ['to_mail', 'err_title']);
            PhpMail::sendEmail($toMail, $title, $content);
            throw new RunTimeException(['excel_data_exist_err_data'],['err_data' => $repeatArr]);
        }
        $dataList = array_chunk($sheetData, 5000);
        $fileInfo = pathinfo($localFilePath);
        //上传原文件到oss
        $orgOssFile = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_REFERRAL . '/import_student_account_award_points_org_file/' . pathinfo($localFilePath)['basename'];
        AliOSS::uploadFile($orgOssFile, $localFilePath);

        //oss对应的文件资源地址
        $insertData = [];
        foreach ($dataList as $key => $info) {
            //生成xml文件
            $_fileName = $fileInfo['filename'] . '_' . $key . '.' . $fileInfo['extension'];
            $_filePath = $_fileName;
            if (!empty($fileInfo['dirname'])) {
                $_filePath = $fileInfo['dirname'] . '/' . $_fileName;
            }
            Spreadsheet::createXml($_filePath, $excelTitle, $info, 0, 'aaaaaaaa');
            //上传到Oss
            $ossFile = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_REFERRAL . '/import_student_account_award_points/' . $_fileName;
            AliOSS::uploadFile($ossFile, $_filePath);
            // 删除临时文件
            unlink($_filePath);
            //上传oss成功，保存到数据表中
            $insertData[] = [
                'operator_id' => $params['employee_id'],
                'app_id' => $params['app_id'],
                'sub_type' => $params['sub_type'],
                'org_file' => $orgOssFile,
                'chunk_file' => $ossFile,
                'remark' => $params['remark'],
                'status' => StudentAccountAwardPointsFileModel::STATUS_CREATE,
                'create_time' => $time,
            ];
        }
        // 保存到数据表
        $res = StudentAccountAwardPointsFileModel::batchInsert($insertData);

        if (!$res) {
            SimpleLogger::error("StudentService::saveUploadXlsx batchInsert error",['data' => $insertData]);
            return false;
        }

        // dev 没有nsq需要到pre上测试 所有逻辑成功后放入到，推送到消息队列
        $queue = new StudentAccountAwardPointsTopic();
        foreach ($insertData as $_data) {
            $queue->import($_data)->publish();
        }
        return true;
    }

    /**
     * 检查uuid 和 mobile 是否重复
     * @param $uuidArr
     * @param $mobileArr
     * @return array
     */
    public static function checkRepeatUuidMobile($uuidArr, $mobileArr)
    {
        $repeatArr = [];    //重复的数组
        $uuidKeyArr = [];   // uuid=>mobile
        $mobileKeyArr = []; // mobile=>uuid
        if (empty($uuidArr) && empty($mobileArr)) {
            return $repeatArr;
        }
        // 查询uuid
        if (!empty($uuidArr)) {
            //找出重复的uuid
            $rUuid = array_diff_assoc($uuidArr,array_unique($uuidArr));
            $uuidStudentList = ErpStudentModel::getRecords(['uuid' => $uuidArr],['uuid','mobile']);
            $uuidKeyArr = array_column($uuidStudentList,'mobile','uuid');
            foreach ($rUuid as $_v) {
                $repeatArr[] = self::formatErrData($_v, $uuidArr[$_v], 'repeat_uuid');
            }

            // 不存在的uuid
            $noExistUuid = array_diff($uuidArr,array_keys($uuidKeyArr));
            foreach ($noExistUuid as $_v) {
                $repeatArr[] = self::formatErrData($_v, '', 'uuid_not_exist');
            }
        }
        // 查询mobile
        if (!empty($mobileArr) ){
            //找出重复mobile
            $rMobile = array_diff_assoc($mobileArr,array_unique($mobileArr));
            $mobileStudentList = ErpStudentModel::getRecords(['mobile' => $mobileArr],['uuid','mobile']);
            $mobileKeyArr = array_column($mobileStudentList,'uuid','mobile');
            foreach ($rMobile as $_v) {
                $repeatArr[] = self::formatErrData($mobileKeyArr[$_v], $_v, 'repeat_mobile');
            }

            // 不存在的mobile
            $noExistMobile = array_diff($mobileArr,array_keys($mobileKeyArr));
            foreach ($noExistMobile as $_v) {
                $repeatArr[] = self::formatErrData('', $_v, 'mobile_not_exist');
            }
        }

        //找出 mobile 和 uuid 对应的id相同的
        if (!empty($mobileKeyArr)) {
            foreach ($uuidKeyArr as $_uuid => $_mobile) {
                // 存在相同
                if (isset($mobileKeyArr[$_mobile])) {
                    $repeatArr[] = self::formatErrData($_uuid, $_mobile, 'uuid_mobile_is_eq');
                    continue;
                }
            }
        }

        return $repeatArr;
    }

    /**
     * 批量发放积分，错误数据格式， 统一格式方便后期调整
     * @param $uuid
     * @param $mobile
     * @param $num
     * @param $errCode
     * @return array
     */
    public static function formatErrData($uuid, $mobile, $errCode, $num = 0)
    {
        $errMsg = Lang::getWord($errCode);
        $errInfo =  [
            'uuid' => $uuid,
            'mobile' => $mobile,
            'num' => $num,
            'err_msg' => !empty($errMsg) ? $errMsg : $errCode,
        ];
        return $errInfo;
    }

    /**
     * 批量发放积分 有重复数据的邮件内容
     * @param $repeatArr
     * @return string
     */
    public static function createRepeatDataMailContent($repeatArr)
    {
        //检测积分发放表+文件名称，
        // 错误数据表内容包括：用户UUID、用户手机号、失败原因
        $content = '检测积分发放上传表，以下内容为错误数据，请更新完后重新上传表。<br><table border="1px solid #ccc" cellspacing="0" cellpadding="0">'.
            '<tr><td width="30%">用户UUID</td><td width="30%">用户手机号</td><td>失败原因</td></tr>';
        foreach ($repeatArr as $_r) {
            $content .= '<tr><td>'.$_r['uuid'].'</td><td>'.$_r['mobile'].'</td><td>'.$_r['err_msg'].'</td></tr>';
        }
        $content .='</table>';
        return $content;
    }
}
