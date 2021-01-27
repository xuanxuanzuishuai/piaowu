<?php
namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\Dss;
use App\Libs\RC4;
use App\Libs\SimpleLogger;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\Dss\DssUserWeiXinModel;
use App\Models\QRCodeModel;

class PosterService
{
    /**
     * 生成带用户QR码海报
     * @param $posterPath
     * @param $config
     * @param $userId
     * @param $type
     * @param $appId
     * @param $channelId
     * @param null $landingType
     * @param array $extParams
     * @return array|string[]
     */
    public static function generateQRPosterAliOss(
        $posterPath,
        $config,
        $userId,
        $type,
        $appId,
        $channelId,
        $landingType = null,
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
            base64_encode($userQrUrl . "?x-oss-process=image/resize,limit_0,w_" . $config['qr_width'] . ",h_" . $config['qr_height'])
        );
        $waterMark = [
            "image_" . $waterImgEncode,
            "x_" . $config['qr_x'],
            "y_" . $config['qr_y'],
            "g_sw",//插入的基准位置以左下角作为原点
        ];
        $waterMarkStr = implode(",", $waterMark) . '/';
        $imgSize = [
            "w_" . $config['poster_width'],
            "h_" . $config['poster_height'],
            "limit_0",//强制图片缩放
        ];
        $imgSizeStr = implode(",", $imgSize) . '/';
        $resImageUrl = AliOSS::signUrls($posterPath, "", "", "", false, $waterMarkStr, $imgSizeStr);
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
     * @return string
     * @throws \App\Libs\Exceptions\RunTimeException
     */
    public static function getMiniappQrImage($appId, $params = [])
    {
        $params['app_id'] = $params['app_id'] ?? $appId;
        $ticket    = $params['r'] ?? '';
        $wechat    = WeChatMiniPro::factory($appId, DssUserWeiXinModel::BUSI_TYPE_REFERRAL_MINAPP);
        $imagePath = '';
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
        return $imagePath;
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
}