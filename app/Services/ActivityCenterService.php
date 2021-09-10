<?php
/**
 * Created by PhpStorm.
 * User: xingkuiYu
 * Date: 2021/9/7
 * Time: 14:55
 */

namespace App\Services;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Models\ActivityCenterModel;
use App\Models\Dss\DssErpPackageModel;
use App\Models\Dss\DssGiftCodeDetailedModel;
use App\Models\Dss\DssPackageExtModel;
use App\Models\Dss\DssStudentModel;

class ActivityCenterService
{
    /**
     * 添加
     * @param array $params
     * @param int $opId
     * @return bool
     * @throws RunTimeException
     */
    public static function createActivity(array $params, int $opId):bool
    {
        $time       = time();
        $createData = [
            'name'        => $params['name'],
            'url'         => $params['url'],
            'banner'      => $params['banner'],
            'show_rule'   => $params['show_rule'],
            'button'      => $params['button'],
            'label'       => $params['label'],
            'channel'     => $params['channel'],
            'weight'      => 0,
            'status'      => ActivityCenterModel::STATUS_OK,
            'create_time' => $time,
            'update_time' => $time,
            'create_by'   => $opId,
        ];

        if (!ActivityCenterModel::add($createData)){
            throw new RunTimeException(['operator_failure']);
        }
        return true;
    }


    /**
     * 获取列表
     * @param $params
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function getList($params, $page, $pageSize)
    {
        $where = '1 = 1';

        if (!empty($params['name'])) {
            $where .= " and a.name like '%{$params['name']}%'";
        }
        if (!empty($params['url'])) {
            $where .= " and url = '{$params['url']}'";
        }
        if (!empty($params['status'])) {
            $where .= " and a.status = {$params['status']}";
        }
        if (!empty($params['channel'])){
            $where .= " and find_in_set({$params['channel']}, channel)";
        }

        if (!empty($params['show_rule'][0])){
            $where .= " and find_in_set({$params['show_rule'][0]}, show_rule)";
        }

        if (!empty($params['show_rule'][1])){
            $where .= " or find_in_set({$params['show_rule'][1]}, show_rule)";
        }

        $data = ActivityCenterModel::list($where, $page, $pageSize);

        $dict = DictConstants::getTypesMap([DictConstants::ACTIVITY_CENTER_SHOW_RULE['type']]);

        foreach ($data['list'] as &$list) {
            $list['status_zh'] = ActivityCenterModel::STATUS_DICT[$list['status']];

            $channel   = explode(',', $list['channel']);
            $channelZh = [];
            foreach ($channel as $c) {
                $channelZh[] = ActivityCenterModel::CHANNEL_DICT[$c];
            }

            $showRule   = explode(',', $list['show_rule']);
            $showRoleZh = [];
            foreach ($showRule as $s) {
                $showRoleZh[] = $dict[DictConstants::ACTIVITY_CENTER_SHOW_RULE['type']][$s]['value'];
            }

            $list['show_rule_zh'] = implode(',', $showRoleZh);
            $list['channel_zh'] = implode(',', $channelZh);
            $list['banner_url'] = AliOSS::replaceCdnDomainForDss($list['banner']);

        }

        return $data;
    }


    /**
     * 更新活动状态
     * @param int $activity_id
     * @param int $status
     * @return bool
     * @throws RunTimeException
     */
    public static function editStatus(int $activity_id, int $status): bool
    {
        $data['status']      = $status;
        $data['update_time'] = time();

        $res = ActivityCenterModel::updateRecord($activity_id, $data);
        if (!$res) {
            throw new RuntimeException(['update_disabled_fail']);
        }
        return true;
    }

    /**
     * 更新活动权重
     * @param int $activityId
     * @param int $weight
     * @return bool
     * @throws RunTimeException
     */
    public static function editWeight(int $activityId, int $weight): bool
    {
        $data['weight']      = $weight;
        $data['update_time'] = time();

        $res = ActivityCenterModel::updateRecord($activityId, $data);
        if (!$res) {
            throw new RuntimeException(['update_disabled_fail']);
        }
        return true;
    }


    /**
     * 更新活动
     * @param array $params
     * @return bool
     * @throws RunTimeException
     */
    public static function editActivity(array $params): bool
    {
        if (empty($params['activity_id'])) {
            throw new RuntimeException(['activity_id_is_required']);
        }

        $activityId = $params['activity_id'];

        $updateData = [
            'name'        => $params['name'],
            'url'         => $params['url'],
            'banner'      => $params['banner'],
            'show_rule'   => $params['show_rule'],
            'button'      => $params['button'],
            'label'       => $params['label'],
            'channel'     => $params['channel'],
            'update_time' => time(),
        ];

        $res = ActivityCenterModel::updateRecord($activityId, $updateData);
        if (!$res) {
            throw new RuntimeException(['update_disabled_fail']);
        }
        return true;
    }


    /**
     * 获取详情
     * @param int $activityId
     * @return array
     */
    public static function getDetail(int $activityId) :array
    {
        $detail = ActivityCenterModel::getById($activityId);

        if (empty($detail)) {
            return [];
        }
        $detail['banner_url'] = AliOSS::replaceCdnDomainForDss($detail['banner']);

        return $detail;
    }


    /**
     * 获取用户列表
     * @param array $params
     * @param int $userId
     * @param int $page
     * @param int $count
     * @return array
     */
    public static function getUserList(array $params,int $userId, int $page, int $count)
    {

        $student = DssStudentModel::getById($userId);
        if (empty($student)){
            return [];
        }

        $params['show_rule'] = self::showRule($student);

        return self::getList($params, $page, $count);
    }


    /**
     * @param array $student
     * @return array
     */
    public static function showRule(array $student):array
    {
        //显示规则 1仅注册、2体验期中、3体验过期未付费正式课、4付费正式课有效期中、5付费正式课已过期
        $showRule = [];
        switch ($student['has_review_course']) {
            case DssStudentModel::REVIEW_COURSE_49:
                if ($student['sub_end_date'] < date("Ymd")) {
                    //体验期过期
                    $showRule[] = 3;
                } else {
                    //体验课 - 体验期
                    $showRule[] = 2;
                }
                break;
            case DssStudentModel::REVIEW_COURSE_1980:
                //付费正式课
                //是否仍在有效期
                $appStatus = StudentService::checkSubStatus($student['sub_status'], $student['sub_end_date']);
                if ($appStatus) {
                    //付费正式课有效期中
                    $showRule[] = 4;

                    if (self::isExperience($student['id'])){
                        $showRule[] = 2;
                    }

                } else {
                    //付费正式课已过期
                    $showRule[] = 5;
                }
                break;
            default:
                //注册
                $showRule[] = 1;
                break;
        }
        return $showRule;
    }

    /**
     * @param int $userId
     * @return bool
     */
    private static function isExperience(int $userId):bool
    {
        //年卡
        $yearCard = DssGiftCodeDetailedModel::getRecord([
            'apply_user'   => $userId,
            'status'       => Constants::STATUS_TRUE,
            'package_type' => DssPackageExtModel::PACKAGE_TYPE_NORMAL,
            'ORDER'        => ['code_start_date' => 'ASC']
        ], ['code_start_date', 'code_end_date']);

        if (empty($yearCard)) return false;

        //获取体验卡判断体验卡是否有效
        $detail = DssGiftCodeDetailedModel::getRecords([
            'apply_user'          => $userId,
            'status'              => Constants::STATUS_TRUE,
            'package_type'        => DssPackageExtModel::PACKAGE_TYPE_TRIAL,
            'code_start_date[>=]' => $yearCard['code_start_date']
        ], ['code_start_date', 'code_end_date']);

        if (empty($detail)) return false;

        foreach ($detail as $value) {
            //体验卡结束时间大于年卡开始时间
            if ($value['code_end_date'] > $yearCard['code_start_date']) {
                return true;
            }
        }

        return  false;
    }

}