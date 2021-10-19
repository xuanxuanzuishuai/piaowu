<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Referral;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\ActivityPosterModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\OperationActivityModel;
use App\Models\PosterModel;
use App\Models\QRCodeModel;
use App\Models\RealWeekActivityModel;
use App\Models\TemplatePosterModel;

class PosterService
{
    /**
     * 生成带用户QR码海报
     * @param $posterPath
     * @param $config
     * @param $userId
     * @param $type
     * @param $channelId
     * @param array $extParams
     * @return array|string[]
     * @throws \App\Libs\Exceptions\RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function generateQRPosterAliOss(
        $posterPath,
        $config,
        $userId,
        $type,
        $channelId,
        $extParams = []
    ) {
        //通过oss合成海报并保存
        //海报资源
        $emptyRes = ['poster_save_full_path' => '', 'unique' => ''];
        $exists = AliOSS::doesObjectExist($posterPath);
        if (empty($exists)) {
            SimpleLogger::info('poster oss file is not exits', [$posterPath]);
            return $emptyRes;
        }
        //用户二维码
        $userQrUrl = DssUserQrTicketModel::getUserQrURL($userId, $type, $channelId, DssUserQrTicketModel::LANDING_TYPE_MINIAPP, $extParams);
        if (empty($userQrUrl)) {
            SimpleLogger::info('user qr make fail', [$userId, $type, $channelId]);
            return $emptyRes;
        }
        $exists = AliOSS::doesObjectExist($userQrUrl);
        if (empty($exists)) {
            SimpleLogger::info('user qr oss file not exits', [$userQrUrl]);
            return $emptyRes;
        }
        //海报添加水印
        //先将内容编码成Base64结果
        //将结果中的加号（+）替换成连接号（-）
        //将结果中的正斜线（/）替换成下划线（_）
        //将结果中尾部的等号（=）全部保留
        $waterImgEncode = str_replace(
            ["+", "/"],
            ["-", "_"],
            base64_encode($userQrUrl . "?x-oss-process=image/resize,limit_0,w_" . $config['QR_WIDTH'] . ",h_" . $config['QR_HEIGHT'])
        );
        $waterMark = [
            "image_" . $waterImgEncode,
            "x_" . $config['QR_X'],
            "y_" . $config['QR_Y'],
            "g_sw",//插入的基准位置以左下角作为原点
        ];
        $waterMarkStr[] = implode(",", $waterMark);
        $imgSize = [
            "w_" . $config['POSTER_WIDTH'],
            "h_" . $config['POSTER_HEIGHT'],
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';

        if (!empty($extParams['text'])) {

            if (count($extParams['text']) == count($extParams['text'], COUNT_RECURSIVE)) {
                $textParam[] = $extParams['text'];
            } else {
                $textParam = $extParams['text'];
            }

            foreach ($textParam as $text) {
                $wordMark = [
                    "text_" . str_replace(
                        ["+", "/"],
                        ["-", "_"],
                        base64_encode($text['word'])
                    ),
                    "x_" . $text['x'],
                    "y_" . $text['y'],
                    "size_" . $text['size'],
                    "color_" . $text['color'],
                    "g_nw",
                ];
                $waterMarkStr[] = implode(",", $wordMark);
            }
        }
        $resImageUrl = AliOSS::signUrls($posterPath, "", "", "", true, $waterMarkStr, $imgSizeStr);
        $user_current_status = $extParams['user_current_status'] ?? DssStudentModel::STATUS_REGISTER;

        //返回数据
        return [
            'poster_save_full_path' => $resImageUrl,
            'unique' => md5($userId . $posterPath . $userQrUrl) . ".jpg",
            'poster_id' => $extParams['p'] ?? 0,
            'user_current_status' => $user_current_status,
            'user_current_status_zh' => DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$user_current_status] ?? '',
        ];
    }

    /**
     * 获取当前QR码类型配置
     * @param $landingType
     * @return int
     */
    private static function getLandingType($landingType)
    {
        if (!empty($landingType)) {
            return $landingType;
        }
        $landingType      = DssUserQrTicketModel::LANDING_TYPE_NORMAL;
        $posterQrcodeType = DictService::getKeyValue(Constants::DICT_TYPE_POSTER_QRCODE_TYPE, 'qr_code_type');
        // 配置：非空时为小程序码
        if (!empty($posterQrcodeType)) {
            $landingType = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
        }
        return $landingType;
    }

    /**
     * 获取小程序码图片路径
     * @param $appId
     * @param array $params
     * @param int $busiType
     * @return array|string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getMiniappQrImage($appId, $params = [], $busiType = DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP)
    {
        $params['app_id'] = $params['app_id'] ?? $appId;
        $ticket    = $params['r'] ?? '';
        $wechat    = WeChatMiniPro::factory($appId, $busiType);
        $imagePath = [];
        if (empty($wechat)) {
            SimpleLogger::error('wechat create fail', ['getMiniappQrImage', $appId, $params]);
            return $imagePath;
        }
        // 请求微信，获取小程序码图片
        $paramId = ReferralActivityService::getParamsId($params);
        $res = $wechat->getMiniappCodeImage($paramId);
        if ($res === false) {
            return $imagePath;
        }
        $tmpFileFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . '/' . md5($ticket . $paramId) . '.jpg';
        if (file_exists($tmpFileFullPath)) {
            chmod($tmpFileFullPath, 0755);
        }

        $bytesWrite = file_put_contents($tmpFileFullPath, $res);
        if (empty($bytesWrite)) {
            SimpleLogger::error('save miniapp code image file error', [$ticket]);
            return $imagePath;
        }
        $imagePath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_MINIAPP_CODE . '/' . md5($ticket.$paramId) . ".png";
        AliOSS::uploadFile($imagePath, $tmpFileFullPath);
        unlink($tmpFileFullPath);
        return [$imagePath, $paramId];
    }

    /**
     * 获取H5页面地址二维码
     * @param $userQrTicket
     * @param $channelId
     * @return string
     */
    public static function getReferralLandingPageQrImage($userQrTicket, $channelId)
    {
        //生成二维码
        $content = $_ENV["AI_REFERRER_URL"] . "?referee_id=" . $userQrTicket . "&channel_id=" . $channelId . "&ad=0";
        list($filePath, $fileName) = QRCodeModel::genImage($content, time());
        chmod($filePath, 0755);
        //上传二维码到阿里oss
        $envName  = $_ENV['ENV_NAME'] ?? 'dev';
        $imagePath = $envName . '/' . AliOSS::DIR_REFERRAL . '/' . $fileName;
        AliOSS::uploadFile($imagePath, $filePath);
        //删除临时二维码文件
        unlink($filePath);
        return $imagePath;
    }

    /**
     * 代理推广页面地址
     * @param array $params
     * @return string
     */
    public static function getAgentLandingPageUrl($params = [])
    {
        $link = DictConstants::get(DictConstants::AGENT_CONFIG, 'package_buy_page_url');
        return $link . "?". http_build_query($params);
    }

    /**
     * 代理二期
     * 新web端销售页面二维码
     * @param $appId
     * @param array $params
     * @param int $packageId
     * @return array
     */
    public static function getAgentLandingPageQrImage($appId, $params = [], $packageId = 0)
    {
        $params['app_id'] = $params['app_id'] ?? $appId;
        $paramId = ReferralActivityService::getParamsId($params);
        $params = [
            'package_id' => $packageId,
            'param_id' => $paramId
        ];
        $content = self::getAgentLandingPageUrl($params);
        list($filePath, $fileName) = QRCodeModel::genImage($content, time());
        chmod($filePath, 0755);
        //上传二维码到阿里oss
        $envName  = $_ENV['ENV_NAME'] ?? 'dev';
        $imagePath = $envName . '/' . AliOSS::DIR_REFERRAL . '/' . $fileName;
        AliOSS::uploadFile($imagePath, $filePath);
        //删除临时二维码文件
        unlink($filePath);
        return [$imagePath, $paramId];
    }

    /**
     * 获取海报配置
     * @param array $key
     * @return array
     */
    public static function getPosterConfig($key = DictConstants::TEMPLATE_POSTER_CONFIG)
    {
        return DictConstants::getSet($key);
    }

    /**
     * 获取海报路径对应的id ,如果不存在新增一条记录并返回对应的id
     * @param $path
     * @param $params
     * @return int|mixed|string|null
     */
    public static function getIdByPath($path, $params)
    {
        return PosterModel::getIdByPath($path, $params);
    }

    /**
     * 查询活动对应海报
     * @param $activityInfo
     * @return array|mixed
     */
    public static function getActivityPosterList($activityInfo)
    {
        if (empty($activityInfo['activity_id'])) {
            return [];
        }
        $allPosterIds = ActivityPosterModel::getListByActivityId($activityInfo['activity_id']);
        if (empty($allPosterIds)) {
            return [];
        }
        $field = ['id', 'name', 'poster_id', 'poster_path', 'example_id', 'example_path', 'order_num', 'practise','type'];
        $where = [
            'id' => array_column($allPosterIds, 'poster_id'),
        ];
        $posterList = TemplatePosterModel::getRecords($where, $field);
        $posterList = array_column($posterList, null, 'id');
        $res = [];
        foreach ($allPosterIds as $p) {
            if (isset($posterList[$p['poster_id']])) {
                $tmpPoster = $posterList[$p['poster_id']];
                $tmpPoster['practise_zh'] = TemplatePosterModel::$practiseArray[$tmpPoster['practise']] ?? '否';
                $tmpPoster['poster_ascription'] = $p['poster_ascription'];
                $tmpPoster['activity_poster_id'] = $p['id'];
                $res[] = $tmpPoster;
            }
        }
        return $res;
    }

    /**
     * 生成带用户二维码的海报
     * @param $posterPath
     * @param $config
     * @param $userId
     * @param $type
     * @param $channelId
     * @param array $extParams
     * @param array $userQrInfo
     * @return array
     */
    public static function generateQrPoster($posterPath, $config, $userId, $type, $channelId, array $extParams = [], $userQrInfo= [])
    {
        SimpleLogger::info('generateQrPoster start', []);
        //通过oss合成海报并保存
        //海报资源
        $emptyRes = ['poster_save_full_path' => '', 'unique' => ''];
        $exists = AliOSS::doesObjectExist($posterPath);
        if (empty($exists)) {
            SimpleLogger::info('poster oss file is not exits', [$posterPath]);
            return $emptyRes;
        }
        //用户二维码
        if (empty($userQrInfo)) {
            $userQrInfo = MiniAppQrService::getUserMiniAppQr(Constants::SMART_APP_ID, Constants::SMART_MINI_BUSI_TYPE, $userId, $type, $channelId, DssUserQrTicketModel::LANDING_TYPE_MINIAPP, $extParams);
        }
        if (empty($userQrInfo['qr_path'])) {
            SimpleLogger::info('user qr make fail', [$userId, $type, $channelId, $userQrInfo]);
            return $emptyRes;
        }
        $exists = AliOSS::doesObjectExist($userQrInfo['qr_path']);
        if (empty($exists)) {
            SimpleLogger::info('user qr oss file not exits', [$userQrInfo['qr_path']]);
            return $emptyRes;
        }
        //海报添加水印
        //先将内容编码成Base64结果
        //将结果中的加号（+）替换成连接号（-）
        //将结果中的正斜线（/）替换成下划线（_）
        //将结果中尾部的等号（=）全部保留
        $waterImgEncode = str_replace(
            ["+", "/"],
            ["-", "_"],
            base64_encode($userQrInfo['qr_path'] . "?x-oss-process=image/resize,limit_0,w_" . $config['QR_WIDTH'] . ",h_" . $config['QR_HEIGHT'])
        );
        $waterMark = [
            "image_" . $waterImgEncode,
            "x_" . $config['QR_X'],
            "y_" . $config['QR_Y'],
            "g_sw",//插入的基准位置以左下角作为原点
        ];
        $waterMarkStr[] = implode(",", $waterMark);
        $imgSize = [
            "w_" . $config['POSTER_WIDTH'],
            "h_" . $config['POSTER_HEIGHT'],
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';

        //添加文字水印【qr_id】
        $extParams['text'] = [
            [
                'word'  => date('m.d', time()),
                'x'     => $config['DATE_X'],
                'y'     => $config['DATE_Y'],
                'size'  => $config['DATE_SIZE'],
                'color' => $config['DATE_COLOR'],
            ],
            [
                'word'  => $userQrInfo['qr_id'],
                'x'     => $config['QR_ID_X'],
                'y'     => $config['QR_ID_Y'],
                'size'  => $config['QR_ID_SIZE'],
                'color' => $config['QR_ID_COLOR'],
            ]
        ];

        if (!empty($extParams['text'])) {
            if (count($extParams['text']) == count($extParams['text'], COUNT_RECURSIVE)) {
                $textParam[] = $extParams['text'];
            } else {
                $textParam = $extParams['text'];
            }

            foreach ($textParam as $text) {
                $wordMark = [
                    "text_" . str_replace(
                        ["+", "/"],
                        ["-", "_"],
                        base64_encode($text['word'])
                    ),
                    "x_" . $text['x'],
                    "y_" . $text['y'],
                    "size_" . $text['size'],
                    "color_" . $text['color'],
                    "g_sw",
                ];
                $waterMarkStr[] = implode(",", $wordMark);
            }
        }
        $resImageUrl = AliOSS::signUrls($posterPath, "", "", "", true, $waterMarkStr, $imgSizeStr);
        $user_current_status = $extParams['user_current_status'] ?? DssStudentModel::STATUS_REGISTER;

        SimpleLogger::info('generateQrPoster end', []);
        //返回数据
        return [
            'poster_save_full_path' => $resImageUrl,
            'unique' => $userQrInfo['qr_id'] . ".jpg",
            'poster_id' => $extParams['poster_id'] ?? ($extParams['p'] ?? 0),
            'user_current_status' => $user_current_status,
            'user_current_status_zh' => DssStudentModel::STUDENT_IDENTITY_ZH_MAP[$user_current_status] ?? '',
        ];
    }

    /**
     * 获取小程序码
     * @param $request
     * @return mixed
     * @throws RunTimeException
     */
    public static function getQrPath($request)
    {
        $studentId  = $request['student_id'];
        $userDetail = StudentService::dssStudentStatusCheck($studentId, false, null);
        $qrData = [
            'poster_id'           => $request['poster_id'],
            'user_current_status' => $userDetail['student_status'] ?? 0,
            'activity_id'         => $request['activity_id'] ?? 0,
            'app_id'              => Constants::SMART_APP_ID,
        ];
        $userType = DssUserQrTicketModel::STUDENT_TYPE;
        $landingType = DssUserQrTicketModel::LANDING_TYPE_MINIAPP;
        $userQrArr = MiniAppQrService::getUserMiniAppQr(Constants::SMART_APP_ID, Constants::SMART_MINI_BUSI_TYPE, $studentId, $userType, $request['channel_id'], $landingType, $qrData);
        if (empty($userQrArr['qr_path'])) {
            throw new RunTimeException(['invalid_data']);
        }
        $qrPath = AliOSS::replaceCdnDomainForDss($userQrArr['qr_path']);
        return ['qr_path' => $qrPath];
    }

    /**
     * 真人生成带用户QR码海报
     * @param $posterPath
     * @param $config
     * @param $userId
     * @param array $extParams
     * @return array|string[]
     */
    public static function generateLifeQRPosterAliOss(
        $item,
        $config,
        $userId,
        $extParams = []
    ) {
        $posterPath = $item['path'];
        $posterId   = $item['poster_id'];
        //通过oss合成海报并保存
        //海报资源
        $emptyRes = ['poster_save_full_path' => '', 'unique' => ''];
        $exists = AliOSS::doesObjectExist($posterPath);
        if (empty($exists)) {
            SimpleLogger::info('poster oss file is not exits', [$posterPath]);
            return $emptyRes;
        }

        //获取真人小程序码
        $params = [
            'student_id' => $userId,
            'poster_id'  => $posterId,
            'channel_id' => $_ENV['USER_RECOMMEND_STANDARD_POSTER']
        ];
        $userQrData = RealSharePosterService::getQrPath($params);
        if (empty($userQrData)) {
            SimpleLogger::info('user qr make fail', [$userId]);
            return $emptyRes;
        }
        $userQrUrl = $userQrData['origin_qr_path'];


        //海报添加水印
        //先将内容编码成Base64结果
        //将结果中的加号（+）替换成连接号（-）
        //将结果中的正斜线（/）替换成下划线（_）
        //将结果中尾部的等号（=）全部保留
        $waterImgEncode = str_replace(
            ["+", "/"],
            ["-", "_"],
            base64_encode($userQrUrl . "?x-oss-process=image/resize,limit_0,w_" . $config['QR_WIDTH'] . ",h_" . $config['QR_HEIGHT'])
        );
        $waterMark = [
            "image_" . $waterImgEncode,
            "x_" . $config['QR_X'],
            "y_" . $config['QR_Y'],
            "g_sw",//插入的基准位置以左下角作为原点
        ];
        $waterMarkStr[] = implode(",", $waterMark);
        $imgSize = [
            "w_" . $config['POSTER_WIDTH'],
            "h_" . $config['POSTER_HEIGHT'],
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';

        if (!empty($extParams['text'])) {

            if (count($extParams['text']) == count($extParams['text'], COUNT_RECURSIVE)) {
                $textParam[] = $extParams['text'];
            } else {
                $textParam = $extParams['text'];
            }

            foreach ($textParam as $text) {
                $wordMark = [
                    "text_" . str_replace(
                        ["+", "/"],
                        ["-", "_"],
                        base64_encode($text['word'])
                    ),
                    "x_" . $text['x'],
                    "y_" . $text['y'],
                    "size_" . $text['size'],
                    "color_" . $text['color'],
                    "g_nw",
                ];
                $waterMarkStr[] = implode(",", $wordMark);
            }
        }
        $resImageUrl = AliOSS::signUrls($posterPath, "", "", "", true, $waterMarkStr, $imgSizeStr);

        //返回数据
        return [
            'poster_save_full_path' => $resImageUrl,
            'unique' => md5($userId . $posterPath . $userQrUrl) . ".jpg",
            'poster_id' => $extParams['p'] ?? 0,
        ];
    }

    //获取当前真人或智能周周活动的ID
    public static function getCheckActivityId($appId)
    {
        if ($appId == Constants::REAL_APP_ID) {
            $activityData = RealWeekActivityModel::getStudentCanSignWeekActivity(1, time());
        } elseif ($appId == Constants::SMART_APP_ID) {
            $activityData = OperationActivityModel::getActiveActivity(TemplatePosterModel::STANDARD_POSTER);
        }

        return $activityData['activity_id'] ?? 0;
    }


    /**
     * 阿里oss图片添加文字水印
     * @param $imagePath
     * @param $word
     * @param $posterConfig
     * @return array|string
     */
    public static function addAliOssWordWaterMark($imagePath, $word, $posterConfig)
    {
        if (empty($imagePath) || empty($word) || empty($posterConfig)) {
            return '';
        }
        //文字水印
        $wordMark = [
            "text_" . str_replace(
                ["+", "/"],
                ["-", "_"],
                base64_encode($word['qr_id'])
            ),
            "x_" . $posterConfig['QR_ID_X'],
            "y_" . $posterConfig['QR_ID_Y'],
            "size_" . $posterConfig['QR_ID_SIZE'],
            "color_" . $posterConfig['QR_ID_COLOR'],
            "g_sw",
        ];
        $waterMarkStr[] = implode(",", $wordMark);
        //日期水印
        $wordMarkDate = [
            "text_" . str_replace(
                ["+", "/"],
                ["-", "_"],
                base64_encode($word['date'])
            ),
            'x_' . $posterConfig['DATE_X'],
            'y_' . $posterConfig['DATE_Y'],
            'size_' . $posterConfig['DATE_SIZE'],
            'color_' . $posterConfig['DATE_COLOR'],
            "g_sw",
        ];
        $waterMarkStr[] = implode(",", $wordMarkDate);
        //底图大小数据
        $imgSize = [
            "w_" . $posterConfig['POSTER_WIDTH'],
            "h_" . $posterConfig['POSTER_HEIGHT'],
            "limit_0",//强制图片缩放
        ];
        //拼接oss接口参数
        $imageOptions = "resize," . implode(",", $imgSize) . '/';
        $waterMarkOssStr = [];
        array_map(function ($val) use (&$waterMarkOssStr) {
            $waterMarkOssStr[] = "watermark," . $val;
        }, $waterMarkStr);
        $imageOptions .= implode("/", $waterMarkOssStr);
        $options = 'x-oss-process=image/' . $imageOptions;
        //生成cdn访问地址
        $cdnDomain = DictConstants::get(DictConstants::ALI_OSS_CONFIG, 'dss_cdn_domain');
        return $cdnDomain . '/' . ($imagePath) . '?' . $options;
    }
}
