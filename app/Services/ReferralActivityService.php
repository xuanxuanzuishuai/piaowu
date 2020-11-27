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
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\EmployeeActivityModel;
use App\Libs\Util;
use App\Libs\AliOSS;
use App\Libs\Exceptions\RunTimeException;
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
     * @param $activityID
     * @return int|null
     */
    public static function modifyEmployeeActivity($data, $activityID)
    {
        $data = self::formatEmployeeActivityPost($data);
        return EmployeeActivityModel::modify($data, $activityID);
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
     * @param $activityID
     * @param $employeeID
     * @return array
     * @throws RunTimeException
     */
    public static function getEmployeePoster($activityID, $employeeID)
    {
        $res = [];
        $activity = EmployeeActivityModel::getById($activityID);
        if (empty($activity)) {
            throw new RunTimeException(['record_not_found']);
        }
        $res['employee_share'] = $activity['employee_share'];
        $setting = EmployeeActivityModel::$activityPosterConfig;
        $activity['employee_poster_url'] = AliOSS::signUrls($activity['employee_poster']);
        list($imageWidth, $imageHeight) = getimagesize($activity['employee_poster_url']);
        if (empty($imageHeight) || empty($imageWidth)) {
            SimpleLogger::error('Error get image size', [$activity]);
            return $res;
        }

        $userQrPath = self::getEmployeeActivityQr($activity, $employeeID);
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
     * @param $employeeID
     * @return string
     */
    public static function getEmployeeActivityQr($activity, $employeeID)
    {
        // return 'dev/employee_poster/3cc78caecb23370c95aff7faba87b281.png';
        // 带活动id和employee id参数的二维码
        $url = DictConstants::get(DictConstants::EMPLOYEE_ACTIVITY_ENV, 'employee_activity_landing_url');
        $url = $url ?? $_ENV['AI_REFERRER_URL'];
        $qrURL = sprintf($url.'?activity_id=%s&employee_id=%s&app=%s', $activity['id'], $employeeID, $activity['app_id']);
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
     * @param $employeeID
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

        $posterData = json_decode($activity['poster'], true);
        unset($activity['poster']);
        if ($posterData) {
            foreach ($posterData as $posterURL) {
                $activity['poster_url'][] = AliOSS::signUrls($posterURL);
            }
        }

        if ($activity['start_time'] > $now) {
            $activity['act_time_status'] = 1; // 待开始
        } elseif ($activity['end_time'] < $now) {
            $activity['act_time_status'] = 3; // 已结束
        } else {
            $activity['act_time_status'] = 2; // 进行中
        }
        $activity['banner_url']           = AliOSS::signUrls($activity['banner']);
        $activity['figure_url']           = AliOSS::signUrls($activity['figure']);
        $activity['employee_poster_url']  = AliOSS::signUrls($activity['employee_poster']);
        $activity['show_start_time']      = date('Y-m-d', $activity['start_time']);
        $activity['show_end_time']        = date('Y-m-d', $activity['end_time']);
        $activity['create_time']          = date('Y-m-d H:i', $activity['create_time']);
        $activity['activity_time_status'] = DictService::getKeyValue('activity_time_status', $activity['act_time_status']);
        $activity['activity_status']      = DictService::getKeyValue('activity_status', $activity['status']);
        $activity['invite_text']          = Util::textDecode($activity['invite_text']);
        $activity['employee_share']       = Util::textDecode($activity['employee_share']);
        return $activity;
    }

    /**
     * 海报数据入库格式化
     * @param $data
     * @return mixed
     */
    private static function formatEmployeeActivityPost($data)
    {
        $data['app_id']         = $data['app_id'] ?? UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT;
        $data['poster']         = json_encode($data['poster']);
        $data['invite_text']    = Util::textEncode($data['invite_text']);
        $data['employee_share'] = Util::textEncode($data['employee_share']);
        return $data;
    }

    public static function getMiniappQrImage($appid, $params = [])
    {
        $userQrTicket      = $params['r'] ?? '';
        $channelId         = $params['c'] ?? '';
        $activityID        = $params['a'] ?? '';
        $employeeID        = $params['e'] ?? '';
        $appID             = $params['ap'] ?? '';
        // $weChatAppIdSecret = WeChatService::getWeCHatAppIdSecret($appid, UserWeixinModel::USER_TYPE_STUDENT);
        // $wx                = WeChatMiniPro::factory(['app_id' => $weChatAppIdSecret['app_id'], 'app_secret' => $weChatAppIdSecret['secret']]);
        // $data = [];
        // if (empty($wx)) {
        //     SimpleLogger::error('wx create fail', ['we_chat_type'=>UserWeixinModel::USER_TYPE_STUDENT]);
        //     return $data;
        // }
        // // 请求微信，获取小程序码图片
        // $res = $wx->getMiniappCodeImage($params);
        // if ($res === false) {
        //     return [];
        // }
        // $tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . '/' . md5($userQrTicket) . '.jpg';
        // chmod($tmpFileFullPath, 0755);

        // $bytesWrite = file_put_contents($tmpFileFullPath, $res);
        // if (empty($bytesWrite)) {
        //     SimpleLogger::error('save miniapp code image file error', [$userQrTicket]);
        //     return $data;
        // }
        $imageUrl = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_MINIAPP_CODE . '/' . md5(implode(',', $params)) . ".png";
        // AliOSS::uploadFile($imageUrl, $tmpFileFullPath);
        // unlink($tmpFileFullPath);
        return [
            'path'    => $imageUrl,
            'ticket'  => $params['r'],
            'channel' => $params['c'],
            'ext'     => json_encode([
                'activity_id' => $params['a'],
                'employee_id' => $params['e'],
            ]),
            'app_id' => $params['ap']
        ];
    }

    public static function getReferralLandingPageQrImage($params)
    {
        //生成二维码
        $content = $_ENV["AI_REFERRER_URL"] . "?" . http_build_query($params);
        list($filePath, $fileName) = QRCodeModel::genImage($content, time());
        chmod($filePath, 0755);
        //上传二维码到阿里oss
        $envName  = $_ENV['ENV_NAME'] ?? 'dev';
        $imageUrl = $envName . '/' . AliOSS::DIR_REFERRAL . '/' . $fileName;
        AliOSS::uploadFile($imageUrl, $filePath);
        //删除临时二维码文件
        unlink($filePath);
        // @TODO: parameters name
        return [
            'path'    => $imageUrl,
            'ticket'  => $params['referee_id'],
            'channel' => $params['channel_id'],
            'ext'     => json_encode([
                'activity_id' => $params['activity_id'],
                'employee_id' => $params['employee_id'],
            ]),
            'app_id' => $params['app_id']
        ];
    }
    public static function getUserQrCode(
        $ticket,
        $channel,
        $activityID,
        $employeeID,
        $appID
    ) {
        $landingType = self::getLandingType();
        if ($landingType == UserQrTicketModel::LANDING_TYPE_MINIAPP) {
            $miniappID = null;
            return self::getMiniappQrImage(
                $miniappID,
                [
                    'r'  => $ticket,
                    'c'  => $channel,
                    'a'  => $activityID,
                    'e'  => $employeeID,
                    'ap' => $appID,
                ]
            );
        }
        return self::getReferralLandingPageQrImage(
            [
                'referee_id'  => $ticket,
                'activity_id' => $activityID,
                'employee_id' => $employeeID,
                'channel_id'  => $channel,
                'app_id'      => $appID
            ]
        );
    }

    private static function getLandingType($landingType = null)
    {
        return '';
        // if (!empty($landingType)) {
        //     return $landingType;
        // }
        // $landingType      = UserQrTicketModel::LANDING_TYPE_NORMAL;
        // $posterQrcodeType = DictService::getKeyValue(Constants::DICT_TYPE_POSTER_QRCODE_TYPE, 'qr_code_type');
        // // 配置：非空时为小程序码
        // if (!empty($posterQrcodeType)) {
        //     $landingType = UserQrTicketModel::LANDING_TYPE_MINIAPP;
        // }
        // return $landingType;
    }

    public static function getPosterList(
        $ticket,
        $channel,
        $activityID,
        $employeeID,
        $appID
    ) {
        $activity = EmployeeActivityModel::getById($activityID);
        if (empty($activity)) {
            throw new RunTimeException(['record_not_found']);
        }
        $posterList = json_decode($activity['poster'], true);
        if ($posterList) {
            foreach ($posterList as $posterURL) {
                $posterList['poster_url'][] = AliOSS::signUrls($posterURL);
            }
        }
        $userQrPath = self::getUserQrCode($ticket, $channel, $activityID, $employeeID, $appID);
        return [$posterList, $userQrPath];
    }

}
