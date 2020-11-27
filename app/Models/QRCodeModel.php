<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/4/29
 * Time: 5:48 PM
 */

namespace App\Models;

use App\Libs\QRcode;

class QRCodeModel extends Model
{
    public static function genImage($content, $time)
    {
        $outputPath = self::getOutputPath();
        if (empty($outputPath)) {
            return [null];
        }

        $fileName = md5($content . $time) . ".png";
        $outfile = $outputPath . $fileName;
        QRcode::png($content, $outfile, 0, 4, 2);

        return [file_exists($outfile) ? $outfile : null, $fileName];
    }

    public static function genText($content)
    {
        return QRcode::text($content, false, 0, 4, 2);
    }

    public static function getOutputPath()
    {
        $default = '/tmp/qr/';
        if (!file_exists($default)) {
            if (!mkdir($default)) {
                return null;
            }
        }
        return $default;
    }
}