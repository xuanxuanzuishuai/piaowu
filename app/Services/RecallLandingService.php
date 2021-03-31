<?php
/**
 * Created by PhpStorm.
 * User: liz
 * Date: 2021/3/22
 * Time: 11:34
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\RedisDB;
use App\Libs\Util;
use App\Models\Dss\DssCategoryV1Model;
use App\Models\Dss\DssGiftCodeModel;

class RecallLandingService
{
    const LANDING_PAGE_USER_FIRST_KEY = 'USER_24_HOURS_MARK';

    /**
     * @param $packageId
     * @param $student
     * @return array
     * @throws RunTimeException
     */
    public static function getIndexData($packageId, $student)
    {
        if (empty($packageId)) {
            return [];
        }
        $data = [];
        $data['had_trial'] = false;
        $data['package'] = [];
        $data['recent_purchase'] = [];
        $data['first_flag'] = false;
        $data['pkg'] = PayServices::PACKAGE_990;
        $package = PackageService::getPackageV1Detail($packageId);
        //判断产品包是否绑定赠品组
        $giftGroup = ErpOrderV1Service::haveBoundGiftGroup($packageId);
        $package['has_gift_group'] = $giftGroup;
        if (empty($package)) {
            return [];
        }

        if ($package['sub_type'] == DssCategoryV1Model::DURATION_TYPE_TRAIL
        && !empty($student['id'])) {
            $res = DssGiftCodeModel::hadPurchasePackageByType($student['id']);
            $data['had_trial'] = !empty($res);
        } else {
            // 年卡需要判断当前在有效期内
            $deadLine = DictConstants::get(DictConstants::RECALL_CONFIG, 'event_deadline');
            if (!empty($deadLine) && time() > $deadLine) {
                throw new RunTimeException(['event_pass_deadline']);
            }
        }
        $data['package'] = $package;
        if ($package['r_price'] != 990) {
            $data['pkg'] = PayServices::PACKAGE_1;
        }
        // 24小时内是否首次进入页面
        if (!empty($student['id'])) {
            $redis = RedisDB::getConn();
            $data['first_flag'] = $redis->get(self::LANDING_PAGE_USER_FIRST_KEY . '_' . $student['id']) ? false : true;
            if ($data['first_flag']) {
                $redis->setex(self::LANDING_PAGE_USER_FIRST_KEY . '_' . $student['id'], Util::TIMESTAMP_ONEDAY, time());
            }
        }
        $recent = DictConstants::get(DictConstants::AGENT_WEB_STUDENT_CONFIG, 'broadcast_config');
        $recent = json_decode($recent, true);

        if (!empty($recent)) {
            array_walk($recent, function (&$item) {
                $item['image'] = AliOSS::replaceCdnDomainForDss($item['image']);
            });
        }
        $data['recent_purchase'] = $recent;
        return $data;
    }
}
