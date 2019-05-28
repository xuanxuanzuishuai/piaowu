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
}