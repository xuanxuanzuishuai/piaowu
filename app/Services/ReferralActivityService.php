<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:35 PM
 */

namespace App\Services;

use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\SimpleLogger;
use App\Libs\UserCenter;
use App\Models\Dss\DssEmployeeModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\EmployeeActivityModel;
use App\Libs\Util;
use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
use App\Models\ParamMapModel;
use App\Models\QRCodeModel;

class ReferralActivityService
{

    /**
     * 员工专项转介绍活动列表
     * @param $params
     * @return array
     */
    public static function getEmployeeActivities($params)
    {
        list($activities, $totalCount) = EmployeeActivityModel::list($params);
        foreach ($activities as &$activity) {
            $activity = self::formatEmployeeActivity($activity);
        }
        return [$activities, $totalCount];
    }

    /**
     * 添加员工专项活动
     * @param $data
     * @return int|mixed|string|null
     */
    public static function addEmployeeActivity($data)
    {
        $data = self::formatEmployeeActivityPost($data);
        return EmployeeActivityModel::insert($data);
    }

    /**
     * 员工专项活动修改
     * @param $data
     * @param $activityId
     * @return int|null
     */
    public static function modifyEmployeeActivity($data, $activityId)
    {
        $data = self::formatEmployeeActivityPost($data);
        return EmployeeActivityModel::modify($data, $activityId);
    }

    /**
     * 获取活动详情
     * @param $id
     * @return mixed
     * @throws RunTimeException
     */
    public static function getEmployeeActivityDetail($id)
    {
        $activity = EmployeeActivityModel::getById($id);
        if (empty($activity)) {
            throw new RunTimeException(['record_not_found']);
        }
        return self::formatEmployeeActivity($activity);
    }

    /**
     * 更新活动状态
     * @param $id
     * @param $status
     * @return int|null
     * @throws RunTimeException
     */
    public static function updateEmployeeActivityStatus($id, $status)
    {
        if (empty($id) || Util::emptyExceptZero($status)) {
            throw new RunTimeException(['invalid_data']);
        }
        return EmployeeActivityModel::modify(['status' => $status], $id);
    }

    /**
     * 获取可生成海报活动列表
     * @param $params
     * @return array
     */
    public static function getActiveList($params)
    {
        $now = time();
        $params['start_time[<=]'] = $now;
        $params['end_time[>=]']   = $now;
        $params['status'] = EmployeeActivityModel::STATUS_ENABLE;
        $list = EmployeeActivityModel::getRecords($params);
        $res = [];
        foreach ($list as $key => $value) {
            $res[] = ['id' => $value['id'], 'name' => $value['name']];
        }
        return $res;
    }

    /**
     * 获取员工活动海报
     * @param $activityId
     * @param $employeeId
     * @return array
     * @throws RunTimeException
     */
    public static function getEmployeePoster($activityId, $employeeId)
    {
        $res = [];
        $activity = EmployeeActivityModel::getById($activityId);
        if (empty($activity)) {
            throw new RunTimeException(['record_not_found']);
        }
        $res['employee_share'] = Util::textDecode($activity['employee_share']);
        $setting = EmployeeActivityModel::$activityPosterConfig;
        $activity['employee_poster_url'] = AliOSS::replaceCdnDomainForDss($activity['employee_poster']);
        list($imageWidth, $imageHeight) = getimagesize($activity['employee_poster_url']);
        if (empty($imageHeight) || empty($imageWidth)) {
            SimpleLogger::error('Error get image size', [$activity]);
            return $res;
        }

        $userQrPath = self::getEmployeeActivityQr($activity, $employeeId);
        $res['poster'] = self::genEmployeePoster(
            $activity['employee_poster'],
            $imageWidth,
            $imageHeight,
            $userQrPath,
            $setting['qr_width'],
            $setting['qr_height'],
            $setting['qr_x'],
            $setting['qr_y']
        );
        return $res;
    }

    /**
     * 获取员工带参二维码
     * @param $activity
     * @param $employeeId
     * @return string
     */
    public static function getEmployeeActivityQr($activity, $employeeId)
    {
        // return 'dev/employee_poster/3cc78caecb23370c95aff7faba87b281.png';
        // 带活动id和employee id参数的二维码
        $url = DictConstants::get(DictConstants::EMPLOYEE_ACTIVITY_ENV, 'employee_activity_landing_url');
        if (empty($url)) {
            throw new RunTimeException(['employee_activity_landing_url not set']);
        }
        $qrURL = $url . '?' . http_build_query(['activity_id' => $activity['id'], 'employee_id' => $employeeId, 'app_id' => $activity['app_id']]);
        list($filePath, $fileName) = QRCodeModel::genImage($qrURL, time());
        chmod($filePath, 0755);
        //上传二维码到阿里oss
        $envName  = $_ENV['ENV_NAME'] ?? 'dev';
        $userQrPath = $envName . '/' . AliOSS::DIR_EMPLOYEE_POSTER . '/' . $fileName;
        AliOSS::uploadFile($userQrPath, $filePath);
        //删除临时二维码文件
        unlink($filePath);
        return $userQrPath;
    }

    /**
     * 生成员工海报
     * @param $activity
     * @param $employeeId
     * @param $imageWidth
     * @param $imageHeight
     * @param $qrWidth
     * @param $qrHeight
     * @param $qrX
     * @param $qrY
     * @return array
     */
    public static function genEmployeePoster(
        $imagePath,
        $imageWidth,
        $imageHeight,
        $qrPath,
        $qrWidth,
        $qrHeight,
        $qrX,
        $qrY
    ) {
        // 海报资源：
        $posterAliOssFileExits = AliOSS::doesObjectExist($imagePath);
        if (empty($posterAliOssFileExits)) {
            SimpleLogger::info('employee poster oss file is not exits', [$imagePath]);
            return [];
        }
        $qrAliOssFileExits = AliOSS::doesObjectExist($qrPath);
        if (empty($qrAliOssFileExits)) {
            SimpleLogger::info('employee qr code oss file is not exits', [$qrPath]);
            return [];
        }

        $waterImgEncode = str_replace(["+", "/"], ["-", "_"], base64_encode($qrPath . "?x-oss-process=image/resize,limit_0,w_" . $qrWidth . ",h_" . $qrHeight));
        $waterMark = [
            "image_" . $waterImgEncode,
            "x_" . $qrX,
            "y_" . $qrY,
            "g_sw",//插入的基准位置以左下角作为原点
        ];
        $waterMarkStr = implode(",", $waterMark) . '/';
        $imgSize = [
            "w_" . $imageWidth,
            "h_" . $imageHeight,
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';
        $resImgFile = AliOSS::signUrls($imagePath, "", "", "", false, $waterMarkStr, $imgSizeStr);
        //返回数据
        return ['poster_save_full_path' => $resImgFile, 'qr_url' => $qrPath];
    }

    /**
     * 海报展示数据格式化
     * @param $activity
     * @return mixed
     */
    private static function formatEmployeeActivity($activity)
    {
        $now = time();

        $activity['poster'] = json_decode($activity['poster'], true);
        if ($activity['poster']) {
            foreach ($activity['poster'] as $posterURL) {
                $activity['poster_url'][] = [
                    'url' => AliOSS::signUrls($posterURL)
                ];
            }
        }
        if ($activity['end_time'] < $now) {
            $activity['act_time_status'] = EmployeeActivityModel::ACT_TIME_STATUS_OVER; // 已结束
        } elseif ($activity['start_time'] > $now) {
            $activity['act_time_status'] = EmployeeActivityModel::ACT_TIME_STATUS_PENDING; // 待开始
        } else {
            $activity['act_time_status'] = EmployeeActivityModel::ACT_TIME_STATUS_IN_PROGRESS; // 进行中
        }
        $activity['banner_url']           = AliOSS::signUrls($activity['banner']);
        $activity['figure_url']           = AliOSS::signUrls($activity['figure']);
        $activity['employee_poster_url']  = AliOSS::signUrls($activity['employee_poster']);
        $activity['show_start_time']      = date('Y-m-d H:i:s', $activity['start_time']);
        $activity['show_end_time']        = date('Y-m-d H:i:s', $activity['end_time']);
        $activity['create_time']          = date('Y-m-d H:i', $activity['create_time']);
        $activity['activity_time_status'] = DictService::getKeyValue('activity_time_status', $activity['act_time_status']);
        $activity['activity_status']      = DictService::getKeyValue('activity_status', $activity['status']);
        $activity['invite_text']          = Util::textDecode($activity['invite_text']);
        $activity['employee_share']       = Util::textDecode($activity['employee_share']);
        $activity['rules']                = Util::textDecode($activity['rules']);
        return $activity;
    }

    /**
     * 海报数据入库格式化
     * @param $data
     * @return mixed
     */
    private static function formatEmployeeActivityPost($data)
    {
        self::validateEmployeeActivityPost($data);
        $data['app_id']         = $data['app_id'] ?? UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $data['poster']         = json_encode($data['poster']);
        $data['start_time']     = strtotime($data['start_time']);
        $data['end_time']       = strtotime($data['end_time']);
        $data['rules']          = Util::textEncode($data['rules']) ?: $data['rules'];
        $data['invite_text']    = Util::textEncode($data['invite_text']) ?: $data['invite_text'];
        $data['employee_share'] = Util::textEncode($data['employee_share']) ?: $data['employee_share'];
        return $data;
    }

    public static function validateEmployeeActivityPost($data)
    {
        $startTime = strtotime($data['start_time']);
        $endTime = strtotime($data['end_time']);
        if ($endTime <= $startTime || $endTime <= time()) {
            throw new RuntimeException(['end_time_error']);
        }
        if (mb_strlen($data['remark']) > 500) {
            throw new RuntimeException(['remark_length_invalid']);
        }
    }

    /**
     * 获取员工活动海报
     * @param $userId
     * @param $channel
     * @param $activityId
     * @param $employeeId
     * @param $appId
     * @return array
     * @throws RunTimeException
     */
    public static function getPosterList(
        $userId,
        $channel,
        $activityId,
        $employeeId,
        $appId
    ) {
        if (empty($channel)) {
            $channel = DictConstants::get(DictConstants::EMPLOYEE_ACTIVITY_ENV, 'invite_channel');
        }
        $activity = EmployeeActivityModel::getById($activityId);
        if (empty($activity)) {
            throw new RunTimeException(['record_not_found']);
        }
        $activity    = self::formatEmployeeActivity($activity);
        $landingType = self::getLandingType();
        $userQrPath  = DssUserQrTicketModel::getUserQrURL($userId, $channel, $activityId, $employeeId, $appId, $landingType);
        if (empty($userQrPath)) {
            SimpleLogger::error('empty user qr code path', [$userId, $channel, $activityId, $employeeId, $appId, $landingType]);
        }
        $userQrUrl = AliOSS::signUrls($userQrPath);

        return [
            'staff'         => DssEmployeeModel::getRecord(['id' => $employeeId], ['uuid']),
            'referral_info' => DssStudentModel::getRecord(['id' => $userId], ['uuid']),
            'activity'      => $activity,
            'qr_url'        => $userQrUrl
        ];
    }

    public static function getLandingType($landingType = null)
    {
        if (!empty($landingType)) {
            return $landingType;
        }
        $qrCodeType = DictService::getKeyValue(Constants::DICT_TYPE_POSTER_QRCODE_TYPE, 'qr_code_type');
        if (!empty($qrCodeType)) {
            return DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
        }
        return DssUserQrTicketModel::LANDING_TYPE_NORMAL;
    }

    /**
     * @param $params
     * @return int|mixed|string|null
     * 返回参数ID
     */
    public static function getParamsId($params)
    {
        $appId = $params['app_id'];
        $type = $params['type'];
        $userId = $params['user_id'];
        unset($params['app_id'], $params['type'], $params['user_id']);
        $paramInfo = json_encode($params);

        $where = [
            'app_id'     => $appId,
            'type'       => $type,
            'user_id'    => $userId,
            'param_info' => $paramInfo,
        ];
        $result = ParamMapModel::getRecord($where, ['id']);
        if (!empty($result)) {
            return $result['id'];
        }

        $insertData = [
            'app_id'      => $appId,
            'type'        => $type,
            'user_id'     => $userId,
            'param_info'  => $paramInfo,
            'create_time' => time(),
        ];

        return ParamMapModel::insertRecord($insertData);
    }

    /**
     * @param $paramId
     * @return mixed|string
     * 根据ID返回信息
     */
    public static function getParamsInfo($paramId)
    {
        $result = ParamMapModel::getById($paramId);
        return $result['param_info'] ?? '';
    }
}
