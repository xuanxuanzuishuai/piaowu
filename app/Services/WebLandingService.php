<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/3/22
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\DictConstants;

class WebLandingService
{

    /**
     * @param int $channel
     * @return bool
     */
    public static function checkChannel($channel = 0)
    {
        if (empty($channel)) {
            return false;
        }
        $allowedChannel = DictConstants::get(DictConstants::WEB_PROMOTION_CONFIG, 'allowed_channel');
        $allowedChannel = json_decode($allowedChannel, true);
        if (!in_array($channel, $allowedChannel)) {
            return false;
        }
        return true;
    }

}
