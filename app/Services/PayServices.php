<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/1
 * Time: 1:55 PM
 */

namespace App\Services;


use App\Libs\Erp;

class PayServices
{
    public static function getPackages()
    {
        $freePackage = [
            'package_id' => 0,
            'package_name' => '7天无限体验卡',
            'price' => '免费领取',
            'origin_price' => null,
            'start_time' => null,
            'end_time' => null,
        ];

        $packages[] = $freePackage;

        $erp = new Erp();
        $ret = $erp->getPackages();
        $erpPackages = $ret['data'] ?? [];

        usort($erpPackages, function ($a, $b) {
            if ($a['oprice'] == $b['oprice']) {
                return $a['package_id'] < $b['package_id'];
            }
            return $a['oprice'] < $b['oprice'];
        });

        foreach ($erpPackages as $pkg) {
            $packages[] = [
                'package_id' => $pkg['package_id'],
                'package_name' => $pkg['package_name'],
                'price' => $pkg['sprice'] . '元',
                'origin_price' => $pkg['oprice'] . '元',
                'start_time' => $pkg['start_time'],
                'end_time' => $pkg['end_time'],
            ];
        }

        return $packages;
    }
}