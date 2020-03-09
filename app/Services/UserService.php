<?php
/**
 * Created by PhpStorm.
 * User: yuxuan
 * Date: 2019/2/21
 * Time: 6:59 PM
 *
 * 用户转介绍
 */

namespace App\Services;

use App\Libs\File;
use App\Libs\QRcode;
use App\Libs\RC4;
use App\Models\UserQrTicketModel;
use Intervention\Image\ImageManagerStatic as Image;
use App\Libs\AliOSS;
use App\Models\QRCodeModel;
use App\Libs\SimpleLogger;
class UserService
{
    /**
     * @param $userId
     * @param $subDir
     * @param $newFilename
     * @param $posterFile
     * @param int $type
     * @param string $from
     * @return bool|string
     * @throws \App\Libs\KeyErrorRC4Exception
     * 生成二维码海报
     */
    public static function generateQRPoster($userId, $subDir, $newFilename, $posterFile, $type = 1, $from = 'user')
    {
        $d1 = substr($newFilename, 0, 2);
        $d2 = substr($newFilename, 2, 2);
        if (!file_exists($_ENV['STATIC_FILE_SAVE_PATH'] . "/{$subDir}/{$d1}/{$d2}/{$newFilename}")) {
            // 海报的宽、高
            $imageWidth = 750;
            $imageHeight = 1050;

            // 生成的海报图片
            $newImage = Image::canvas($imageWidth, $imageHeight);
            $userQR = self::getUserQR($userId, $type, $from);
            if (!file_exists($_ENV['STATIC_FILE_SAVE_PATH'] . "/" . $userQR['qr_url']))
                return false;

            $imgPoster = Image::make($posterFile);
            $newImage->insert($imgPoster);
            $qrImage = Image::make($_ENV['STATIC_FILE_SAVE_PATH'] . "/" . $userQR['qr_url']);
            // 插入二维码
            $newImage->insert($qrImage, 'bottom-left', 550, 30);

            // 获取存储的文件夹路径
            list($subPath, $hashDir, $fullDir) = File::createDir($subDir, $newFilename);

            // 保存文件
            $newImage->save("{$fullDir}/{$newFilename}", 100);
            chmod("{$fullDir}/{$newFilename}", 0755);
        }
        return "/{$subDir}/{$d1}/{$d2}/{$newFilename}";
    }

    /**
     * 学生端微信分享二维码
     * @param $userId 学生id 或 老师id
     * @param int $type 推荐人类型 1 学生 2 老师
     * @param string $from 'user'代表要推荐学生，'teacher'代表要推荐教师
     * @param source 渠道
     * @param $posterInfo
     * @return mixed
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getUserQR($userId, $type = 1, $from = "user", $source = null, $posterInfo = null)
    {
        $res = UserQrTicketModel::getRecord(['AND' => ['user_id' => $userId, 'type' => $type]], [], false);
        if (!empty($res['qr_url']))
            return $res;
        $userQrStr = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $type . "_" . $userId);
        if (!is_dir('/tmp/qr')) {
            mkdir('/tmp/qr', 0777, true);
        }
        QRcode::png($_ENV["WECHAT_FRONT_DOMAIN"] . "/bind/org/add" . "?referee_id=" . $userQrStr, '/tmp/qr/' . $userQrStr . ".png");
        $path = '/tmp/qr/' . $userQrStr . ".png";
        $qrImage = Image::make($path);
        $qrImage->resize(170, 170);

        $subDir = $from . "_" . $type . 'QR';
        $newFilename = substr(md5($type . $userId), 8, 16) . '.png';

        // 获取存储的文件夹路径
        list($subPath, $hashDir, $fullDir) = File::createDir($subDir, $newFilename);

        // 保存文件
        $qrImage->save("{$fullDir}/{$newFilename}", 100);
        chmod("{$fullDir}/{$newFilename}", 0755);
        $data = [
            'create_time' => time(),
            'user_id' => $userId,
            'qr_ticket' => $userQrStr,
            'qr_url' => $subPath . "/" . $newFilename,
            'type' => $type,
        ];
        unlink($path);
        UserQrTicketModel::insertRecord($data, false);
        return $data;
    }
    /**
     * 生成二维码海报
     * @param $userId
     * @param $posterFile
     * @param int $type
     * @param int $imageWidth   海报图片宽度
     * @param int $imageHeight  海报图片高度
     * @param int $qrWidth      二维码图片宽度
     * @param int $qrHeight     二维码图片高度
     * @param int $qrX          二维码图片在海报中的X轴位置
     * @param int $qrY          二维码图片在海报中的Y轴位置
     * @return array|string
     */
    public static function generateQRPosterAliOss($userId, $posterFile, $type = 1, $imageWidth, $imageHeight, $qrWidth, $qrHeight, $qrX, $qrY)
    {
        //合成海报保存目录
        $posterFirstDir = UserQrTicketModel::$posterDir[$type];
        //海报保存二级目录
        $posterSecondDir = md5($posterFile);
        //海报文件名称
        $posterFileName = $userId.".png";
        //获取宣传海报文件：不存在重新生成
        $posterSaveFullPath = $_ENV['STATIC_FILE_SAVE_PATH'] . "/".$posterFirstDir."/".$posterSecondDir."/".$posterFileName;
        if (!file_exists($posterSaveFullPath)) {
            //通过oss合成海报并保存
            //海报资源
            $posterAliOssFileExits = AliOSS::doesObjectExist($posterFile);
            if (empty($posterAliOssFileExits)) {
                SimpleLogger::info('poster oss file is not exits', [$posterFile]);
                return;
            }
            //用户二维码
            $userQrUrl = self::getUserQRAliOss($userId, $type);
            if (empty($userQrUrl)) {
                SimpleLogger::info('user qr make fail', [$userId,$type]);
                return;
            }
            $userQrAliOssFileExits = AliOSS::doesObjectExist($userQrUrl['qr_url']);
            if (empty($userQrAliOssFileExits)) {
                SimpleLogger::info('user qr oss file is not exits', [$userQrUrl['qr_url']]);
                return;
            }
            //海报添加水印
            //先将内容编码成Base64结果
            //将结果中的加号（+）替换成连接号（-）
            //将结果中的正斜线（/）替换成下划线（_）
            //将结果中尾部的等号（=）全部保留
            $waterImgEncode = str_replace(["+", "/"], ["-", "_"], base64_encode($userQrUrl['qr_url'] . "?x-oss-process=image/resize,limit_0,w_" . $qrWidth . ",h_" . $qrHeight));
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
            $resImgFile = AliOSS::signUrls($posterFile, "", "", "", false, $waterMarkStr, $imgSizeStr);
            //生成宣传海报图
            list($subPath, $hashDir, $fullDir) = File::createDir($posterFirstDir."/".$posterSecondDir);
            $posterQrFile=file_get_contents($resImgFile);
            $posterQrFileTmpPath = $fullDir.$posterFileName;
            file_put_contents($posterQrFileTmpPath,$posterQrFile);
            chmod($posterQrFileTmpPath, 0755);
        }
        //返回数据
        return $posterSaveFullPath;
    }

    /**
     * 学生端微信分享二维码
     * @param $userId       学生 id或老师id
     * @param int $type 推荐人类型 1 学生 2 老师
     * @return array|mixed
     */
    public static function getUserQRAliOss($userId, $type = 1)
    {
        //获取学生转介绍学生二维码资源数据
        $res = UserQrTicketModel::getRecord(['AND' => ['user_id' => $userId, 'type' => $type]], [], false);
        if (!empty($res['qr_url'])) {
            return $res;
        }
        $data = [];
        try {
            //生成二维码
            $userQrTicket = RC4::encrypt($_ENV['COOKIE_SECURITY_KEY'], $type . "_" . $userId);
            $content = $_ENV["AI_REFERRER_URL"] . "?referee_id=" . $userQrTicket;
            $time = time();
            list($filePath, $fileName) = QRCodeModel::genImage($content, $time);
            chmod($filePath, 0755);
            //上传二维码到阿里oss
            $envName = $_ENV['ENV_NAME'] ?? 'dev';
            $imageUrl = $envName . '/' . AliOSS::DIR_REFERRAL . '/' . $fileName;
            AliOSS::uploadFile($imageUrl, $filePath);
            //记录数据
            $data = [
                'create_time' => $time,
                'user_id' => $userId,
                'qr_ticket' => $userQrTicket,
                'qr_url' => $imageUrl,
                'type' => $type,
            ];
            //删除临时二维码文件
            unlink($filePath);
            UserQrTicketModel::insertRecord($data, false);
        } catch (\Exception $e) {
            echo $e->getMessage();
            SimpleLogger::error('make user qr image exception', [print_r($e->getMessage(), true)]);
            return $data;
        }
        return $data;
    }
}