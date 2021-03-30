<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\PosterModel;
use App\Models\QRCodeModel;

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
        $waterMarkStr = implode(",", $waterMark) . '/';
        $imgSize = [
            "w_" . $config['POSTER_WIDTH'],
            "h_" . $config['POSTER_HEIGHT'],
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';
        $resImageUrl = AliOSS::signUrls($posterPath, "", "", "", true, $waterMarkStr, $imgSizeStr);
        //返回数据
        return ['poster_save_full_path' => $resImageUrl, 'unique' => md5($userId . $posterPath . $userQrUrl) . ".jpg"];
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
        chmod($tmpFileFullPath, 0755);

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
}