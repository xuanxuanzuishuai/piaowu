<?php

namespace App\Services\Activity\Lottery;

use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Erp;
use App\Libs\Excel\ExcelImportFormat;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\Util;
use App\Models\Erp\ErpStudentModel;
use App\Models\LotteryActivityModel;
use App\Models\OperationActivityModel;
use App\Services\Activity\Lottery\LotteryServices\LotteryActivityService;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardInfoService;
use App\Services\Activity\Lottery\LotteryServices\LotteryAwardRecordService;
use App\Services\Activity\Lottery\LotteryServices\LotteryImportUserService;
use App\Services\EmployeeService;

/**
 * 转盘抽奖活动管理后台服务文件
 */
class LotteryAdminService
{
    /**
     * 增加/修改抽奖活动基础信息
     * @param $paramsData
     * @return int
     * @throws RunTimeException
     */
    public static function addOrUpdate($paramsData): int
    {
        $formatParams = self::formatParams($paramsData);
        if (isset($paramsData['op_activity_id'])) {
            $res = LotteryActivityService::update($paramsData['op_activity_id'], $formatParams);
        } else {
            $res = LotteryActivityService::add($formatParams);
        }
        if (empty($res)) {
            throw new RunTimeException(["update_failure"]);
        }
        return $formatParams['base_data']['op_activity_id'];
    }

    /**
     * 格式化处理参数
     * @throws RunTimeException
     */
    private static function formatParams($paramsData): array
    {
        $nowTime = time();
        $formatParams = [];
        //检测参数
        if ($paramsData['start_time'] < $nowTime ||
            $paramsData['end_time'] <= $paramsData['start_time']) {
            throw new RuntimeException(["activity_start_time_error"]);
        }
        $formatParams['base_data']['name'] = Util::textEncode(trim($paramsData['name']));
        $formatParams['base_data']['activity_desc'] = Util::textEncode(trim($paramsData['activity_desc']));
        $formatParams['base_data']['title_url'] = trim($paramsData['title_url']);
        $formatParams['base_data']['start_time'] = $paramsData['start_time'];
        $formatParams['base_data']['end_time'] = $paramsData['end_time'];
        $formatParams['base_data']['app_id'] = $paramsData['app_id'];
        $formatParams['base_data']['user_source'] = $paramsData['user_source'];
        $formatParams['base_data']['start_pay_time'] = 0;
        $formatParams['base_data']['end_pay_time'] = 0;
        $formatParams['base_data']['rest_award_num'] = 0;
        //参与用户规则
        $formatParams['import_user'] = $formatParams['lottery_times_rule'] = $formatParams['win_prize_rule'] = [];
        if ($paramsData['user_source'] == LotteryActivityModel::USER_SOURCE_FILTER) {
            //筛选规则
            if (empty($paramsData['start_pay_time'])) {
                throw new RuntimeException(["pay_start_time_is_required"]);
            }
            if (empty($paramsData['end_pay_time'])) {
                throw new RuntimeException(["pay_end_time_is_required"]);
            }
            if ($paramsData['end_pay_time'] < $paramsData['start_pay_time']) {
                throw new RuntimeException(["start_time_eq_end_time"]);
            }
            $formatParams['base_data']['start_pay_time'] = $paramsData['start_pay_time'];
            $formatParams['base_data']['end_pay_time'] = $paramsData['end_pay_time'];
            //中奖规则
            if (empty($paramsData['lottery_times_rule'])) {
                throw new RuntimeException(["lottery_times_rule_is_required"]);
            }
            if (count($paramsData['lottery_times_rule']) > 3) {
                throw new RuntimeException(["lottery_times_rule_count_error"]);
            }
            array_multisort(array_column($paramsData['lottery_times_rule'], 'high_pay_amount'), SORT_ASC,
                $paramsData['lottery_times_rule']);
            $checkTimesAmount = 0;
            foreach ($paramsData['lottery_times_rule'] as &$pv) {
                $pv['high_pay_amount'] = floatval($pv['high_pay_amount']);
                $pv['low_pay_amount'] = floatval($pv['low_pay_amount']);
                if ($pv['low_pay_amount'] <= 0 || $pv['high_pay_amount'] <= 0 || $pv['low_pay_amount'] >= $pv['high_pay_amount']) {
                    throw new RuntimeException(["pay_amount_error"]);
                }
                if ($checkTimesAmount > 0 && $pv['low_pay_amount'] < $checkTimesAmount) {
                    throw new RuntimeException(["win_prize_rule_account_error"]);
                }
                $checkTimesAmount = $pv['high_pay_amount'];
                $formatParams['lottery_times_rule'][] = [
                    'low_pay_amount'  => substr(sprintf("%.3f", $pv['low_pay_amount']), 0, -1) * 100,
                    'high_pay_amount' => substr(sprintf("%.3f", $pv['high_pay_amount']), 0, -1) * 100,
                    'times'           => $pv['times'],
                    'status'          => Constants::STATUS_TRUE,
                ];
            }
            //中奖规则
            if (!empty($paramsData['win_prize_rule'])) {
                if (!is_array($paramsData['win_prize_rule'])) {
                    throw new RuntimeException(["win_prize_rule_is_required"]);
                }
                if (count($paramsData['win_prize_rule']) > 5) {
                    throw new RuntimeException(["win_prize_rule_count_error"]);
                }
                //校验中奖的付款时间组是否有交集
                array_multisort(array_column($paramsData['win_prize_rule'], 'high_pay_amount'), SORT_ASC,
                    $paramsData['win_prize_rule']);
                $checkAmount = 0;
                foreach ($paramsData['win_prize_rule'] as &$wpv) {
                    $wpv['high_pay_amount'] = floatval($wpv['high_pay_amount']);
                    $wpv['low_pay_amount'] = floatval($wpv['low_pay_amount']);
                    if ($wpv['low_pay_amount'] <= 0 || $wpv['high_pay_amount'] <= 0 || $wpv['low_pay_amount'] >= $wpv['high_pay_amount']) {
                        throw new RuntimeException(["pay_amount_error"]);
                    }
                    //可抽中奖品等级:将兜底奖品拼接起来
                    $tmpAwardLevels = explode(',', $wpv['award_level']);
                    if (empty($tmpAwardLevels) || !is_array($tmpAwardLevels)) {
                        throw new RuntimeException(["win_prize_rule_account_error"]);
                    }
                    if ($checkAmount > 0 && $wpv['low_pay_amount'] < $checkAmount) {
                        throw new RuntimeException(["win_prize_rule_account_error"]);
                    }
                    $checkAmount = $wpv['high_pay_amount'];
                    array_push($formatParams['win_prize_rule'], [
                        'low_pay_amount'  => substr(sprintf("%.3f", $wpv['low_pay_amount']), 0, -1) * 100,
                        'high_pay_amount' => substr(sprintf("%.3f", $wpv['high_pay_amount']), 0, -1) * 100,
                        'award_level'     => $wpv['award_level'],
                        'status'          => Constants::STATUS_TRUE,
                    ]);
                }
            }
        }
        //中奖限制条件
        if ($paramsData['day_max_hit_type'] == LotteryActivityModel::MAX_HIT_TYPE_UNLIMITED) {
            $paramsData['day_max_hit'] = -1;
        }
        if ($paramsData['max_hit_type'] == LotteryActivityModel::MAX_HIT_TYPE_UNLIMITED) {
            $paramsData['max_hit'] = -1;
        }
        if ($paramsData['max_hit_type'] == LotteryActivityModel::MAX_HIT_TYPE_CUSTOM &&
            $paramsData['day_max_hit_type'] == LotteryActivityModel::MAX_HIT_TYPE_CUSTOM &&
            $paramsData['day_max_hit'] > $paramsData['max_hit']) {
            throw new RuntimeException(["max_hit_and_day_hit_relation_error"]);
        }
        $formatParams['base_data']['day_max_hit_type'] = $paramsData['day_max_hit_type'];
        $formatParams['base_data']['day_max_hit'] = $paramsData['day_max_hit'];
        $formatParams['base_data']['max_hit_type'] = $paramsData['max_hit_type'];
        $formatParams['base_data']['max_hit'] = $paramsData['max_hit'];

        //奖品设置:最后一个奖品默认设置为兜底奖品（1.库存=-1 2.中奖时间段=活动开始结束时间 3.奖品类型不能是实物
        if (!is_array($paramsData['awards']) || empty($paramsData['awards'])) {
            throw new RuntimeException(["award_params_error"]);
        }
        $awardCount = count($paramsData['awards']);
        if ($awardCount > 8 || $awardCount < 5) {
            throw new RuntimeException(["award_counts_error"]);
        }
        //兜底奖品
        if ($paramsData['awards'][$awardCount - 1]['type'] == Constants::AWARD_TYPE_TYPE_ENTITY) {
            throw new RuntimeException(["default_award_not_entity"]);
        }
        //奖品的权重总和
        $weightTotal = 0;
        foreach ($paramsData['awards'] as $awk => &$awv) {
            //批量验证参数格式
            $formatParams['awards'][$awk] = self::checkAwardsParams($awv);
            $formatParams['awards'][$awk]['rest_num'] = $formatParams['awards'][$awk]['num'];
            $weightTotal += $awv['weight'];
            //区分不同奖励，校验不同参数
            switch ($awv['type']) {
                case Constants::AWARD_TYPE_GOLD_LEAF:
                case Constants::AWARD_TYPE_MAGIC_STONE:
                case Constants::AWARD_TYPE_EMPTY:
                case Constants::AWARD_TYPE_TYPE_NOTE:
                    $awv['common_award_id'] = 0;
                    break;
                case Constants::AWARD_TYPE_TIME:
                    $awv['common_award_id'] = 0;
                    if ($awv['common_award_amount'] > 40000) {
                        throw new RuntimeException(["free_time_award_counts_max_40000_error"]);
                    }
                    break;

                case Constants::AWARD_TYPE_TYPE_LESSON:
                case Constants::AWARD_TYPE_TYPE_ENTITY:
                    //课时&实物
                    if (empty($awv['common_award_id'])) {
                        throw new RuntimeException(["award_id_is_required"]);
                    }
                    break;
                default:
                    throw new RuntimeException(["award_type_is_error"]);
            }
            //兜底奖品：计算权重总和是否小于100
            if ($awk == $awardCount - 1) {
                if (100 - $weightTotal < 0) {
                    throw new RuntimeException(["total_weight_is_error"]);
                }
                $formatParams['awards'][$awk]['num'] = $formatParams['awards'][$awk]['rest_num'] = -1;
            } else {
                $formatParams['base_data']['rest_award_num'] += $formatParams['awards'][$awk]['num'];

            }
            //奖品中奖时间段
            if (!in_array($awv['hit_times_type'],
                [LotteryActivityModel::HIT_TIMES_TYPE_KEEP_ACTIVITY, LotteryActivityModel::HIT_TIMES_TYPE_CUSTOM])) {
                throw new RuntimeException(["hit_time_type_error"]);
            }
            //奖品中奖时间类型同活动时间
            if ($awv['hit_times_type'] == LotteryActivityModel::HIT_TIMES_TYPE_KEEP_ACTIVITY) {
                $awv['hit_times'] = [
                    [
                        'start_time' => (int)$formatParams['base_data']['start_time'],
                        'end_time'   => (int)$formatParams['base_data']['end_time'],
                    ]
                ];
            } else {
                if (!is_array($awv['hit_times']) || count($awv['hit_times']) > 3) {
                    throw new RuntimeException(["hit_times_value_count_error"]);
                }

                array_multisort(array_column($awv['hit_times'], 'end_time'), SORT_ASC,
                    $awv['hit_times']);
                foreach ($awv['hit_times'] as $thk => $thv) {
                    $tmpHitTimesItem = [
                        'start_time' => (int)$thv['start_time'],
                        'end_time'   => (int)$thv['end_time'],
                    ];
                    if ($tmpHitTimesItem['start_time'] <= $nowTime ||
                        $tmpHitTimesItem['start_time'] >= $tmpHitTimesItem['end_time']) {
                        throw new RuntimeException(["hit_times_start_time_error"]);
                    }
                    if (isset($awv['hit_times'][$thk + 1]['start_time']) &&
                        $tmpHitTimesItem['end_time'] >= $awv['hit_times'][$thk + 1]['start_time']) {
                        throw new RuntimeException(["hit_times_join"]);
                    }
                }

            }
            $formatParams['awards'][$awk]['weight'] = substr(sprintf("%.3f", $awv['weight']), 0, -1) * 100;
            $formatParams['awards'][$awk]['hit_times_type'] = $awv['hit_times_type'];
            $formatParams['awards'][$awk]['hit_times'] = json_encode($awv['hit_times']);
            $formatParams['awards'][$awk]['award_detail'] = json_encode([
                "common_award_id"     => (int)$awv['common_award_id'],
                "common_award_amount" => (int)$awv['common_award_amount'],
            ]);
            $formatParams['awards'][$awk]['level'] = $awk + 1;
            $formatParams['awards'][$awk]['type'] = $awv['type'];
        }

        if (isset($paramsData['op_activity_id'])) {
            $formatParams['base_data']['update_time'] = $nowTime;
            $formatParams['base_data']['update_uuid'] = $paramsData['employee_uuid'];
            $formatParams['base_data']['status'] = (int)$paramsData['enable_status'];
            $opActivityId = $paramsData['op_activity_id'];
        } else {
            //全局活动ID
            $opActivityId = OperationActivityModel::insertRecord(
                [
                    'name'        => $formatParams['base_data']['name'],
                    'create_time' => $nowTime,
                    'app_id'      => $formatParams['base_data']['app_id'],
                ]
            );
            $formatParams['base_data']['create_time'] = $nowTime;
            $formatParams['base_data']['create_uuid'] = $paramsData['employee_uuid'];
            $formatParams['base_data']['status'] = !empty($paramsData['enable_status']) ?(int)$paramsData['enable_status']: OperationActivityModel::ENABLE_STATUS_OFF;
        }

        if (empty($opActivityId)) {
            throw new RuntimeException(["op_activity_id_create_fail"]);
        }
        foreach ($formatParams['win_prize_rule'] as &$fwv) {
            $fwv['op_activity_id'] = $opActivityId;
            $fwv['award_level'] .= ','.$awardCount;
        }
        foreach ($formatParams['lottery_times_rule'] as &$flv) {
            $flv['op_activity_id'] = $opActivityId;
        }
        foreach ($formatParams['awards'] as &$fav) {
            $fav['op_activity_id'] = $opActivityId;
        }
        foreach ($formatParams['import_user'] as &$fiv) {
            $fiv['op_activity_id'] = $opActivityId;
            $fiv['create_time'] = $nowTime;
            $fiv['create_uuid'] = $paramsData['employee_uuid'];
        }
        $formatParams['base_data']['op_activity_id'] = $opActivityId;
        return $formatParams;
    }

    /**
     * 校验奖励数据参数
     * @param $awv
     * @return array
     * @throws RunTimeException
     */
    public static function checkAwardsParams($awv): array
    {
        $checkParamsConfig = [
            "name"    => ['error' => "award_name_is_required", 'type' => 'string'],
            "img_url" => ['error' => "img_is_required", 'type' => 'string'],
            "weight"  => ['error' => "weight_is_error", 'type' => 'float', '0_relation' => ">"],
            "num"     => ['error' => "award_storage_num_error", 'type' => 'int', '0_relation' => ">="],
        ];
        $awardCheckParams = [];
        foreach ($checkParamsConfig as $ck => $cv) {
            $tmpCv = $awv[$ck];
            switch ($cv['type']) {
                case "string":
                    $tmpCv = (string)$awv[$ck];
                    break;
                case "int":
                    $tmpCv = (int)$awv[$ck];
                    break;
                case "float":
                    $tmpCv = (float)$awv[$ck];
                    break;
            }
            if ($cv['type'] == "string" && empty($tmpCv)) {
                throw new RuntimeException([$cv['error']]);
            } elseif ($cv['type'] == "float" && $cv['0_relation'] == ">" && $tmpCv < 0) {
                throw new RuntimeException([$cv['error']]);
            } elseif ($cv['type'] == "int" && $cv['0_relation'] == ">=" && $tmpCv < 0) {
                throw new RuntimeException([$cv['error']]);
            }
            $awardCheckParams[$ck] = $tmpCv;
        }
        return $awardCheckParams;
    }

    /**
     * 检测表格数据
     * @return array|array[]
     * @throws RunTimeException
     */
    private static function checkImportExcelData(): array
    {
        $importData = ExcelImportFormat::formatImportExcelData(true, ['uuid', 'rest_times']);
        foreach ($importData as $k => $v) {
            if (strlen($v['uuid']) != 18 || (int)$v['rest_times'] <= 0) {
                unset($importData[$k]);
            }
        }
        if (empty($importData) || !is_array($importData)) {
            throw new RuntimeException(["invalid_import_user_excel_data"]);
        }
        return $importData;
    }

    /**
     * 追加导流账户
     * @param $opActivityId
     * @param $employeeUuid
     * @param $isCover
     * @return bool
     * @throws RunTimeException
     */
    public static function appendImportUserData($opActivityId, $employeeUuid, $isCover): bool
    {
        //导入名单数据处理
        $importData = self::checkImportExcelData();
        $nowTime = time();
        foreach ($importData as &$iv) {
            $iv['op_activity_id'] = $opActivityId;
            $iv['create_time'] = $nowTime;
            $iv['create_uuid'] = $employeeUuid;
        }
        $importRes = LotteryImportUserService::appendImportUserData($opActivityId, $importData, $isCover);
        if (empty($importRes)) {
            throw new RuntimeException(["insert_failure"]);
        }
        return true;
    }

    /**
     * 数据列表
     * @param $params
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function list($params, $page, $pageSize): array
    {
        $listData = LotteryActivityService::search($params, $page, $pageSize);
        if (empty($listData['list'])) {
            return $listData;
        }
        $listData['list'] = self::formatListData($listData['list']);
        return $listData;
    }

    /**
     * 格式化处理列表数据
     * @param $list
     * @return array
     */
    private static function formatListData($list): array
    {
        $formatList = [];
        $dictData = DictConstants::getTypesMap([
            DictConstants::USER_SOURCE['type'],
            DictConstants::ACTIVITY_TIME_STATUS['type'],
            DictConstants::ACTIVITY_ENABLE_STATUS['type'],
            DictConstants::LOTTERY_CONFIG['type'],
            DictConstants::AWARD_LEVEL['type'],
        ]);
        //操作人信息
        $employeeData = array_column(EmployeeService::getEmployeeByUuids(array_column($list, 'create_uuid'),
            ['uuid', 'name']), null, 'uuid');
        //奖品等级数据
        $awardData = LotteryAwardInfoService::getAwardInfo(array_column($list, 'op_activity_id'),
            ['op_activity_id', 'level']);
        $awardFormatData = [];
        foreach ($awardData as $avl) {
            $awardFormatData[$avl['op_activity_id']][] = [
                'code'  => $avl['level'],
                'value' => $dictData[DictConstants::AWARD_LEVEL['type']][$avl['level']]['value']
            ];
        }
        foreach ($list as $lv) {
            if ($lv['status'] == OperationActivityModel::ENABLE_STATUS_ON) {
                $timeStatus = OperationActivityModel::dataMapToTimeStatus($lv['start_time'], $lv['end_time']);
                $showStatus = OperationActivityModel::TIME_STATUS_MAP_SHOW_STATUS[$timeStatus];
                $showStatusZh = $dictData[DictConstants::ACTIVITY_TIME_STATUS['type']][$timeStatus]['value'];
            } else {
                $showStatusZh = $dictData[DictConstants::ACTIVITY_ENABLE_STATUS['type']][$lv['status']]['value'];
                $showStatus = $lv['status'];
            }
            $tmpEndAward = end($awardFormatData[$lv['op_activity_id']]);
            $tmpEndAward['value'] = '兜底奖';
            array_splice($awardFormatData[$lv['op_activity_id']], -1, 1, [$tmpEndAward]);
            $formatList[] = [
                'op_activity_id'   => $lv['op_activity_id'],
                'name'             => Util::textDecode($lv['name']),
                'start_time'       => date("Y-m-d H:i:s", $lv['start_time']),
                'end_time'         => date("Y-m-d H:i:s", $lv['end_time']),
                'rest_award_num'   => $lv['rest_award_num'],
                'hit_times'        => $lv['hit_times'],
                'join_num'         => $lv['join_num'],
                'user_source_zh'   => $dictData[DictConstants::USER_SOURCE['type']][$lv['user_source']]['value'],
                'show_status_zh'   => $showStatusZh,
                'show_status'      => $showStatus,
                'enable_status'    => $lv['status'],
                'app_id'           => $lv['app_id'],
                'channel_id'       => $dictData[DictConstants::LOTTERY_CONFIG['type']][$lv['app_id']]['value'],
                'creator_name'     => $employeeData[$lv['create_uuid']]['name'] ?? '',
                'create_time'      => date("Y-m-d H:i:s", $lv['create_time']),
                'award_level_dict' => $awardFormatData[$lv['op_activity_id']],
            ];
        }
        return $formatList;
    }

    /**
     * 活动详情
     * @param $opActivityId
     * @return array
     * @throws RunTimeException
     */
    public static function detail($opActivityId): array
    {
        $detailData = LotteryActivityService::detail($opActivityId);
        if (empty($detailData['base_data'])) {
            throw new RunTimeException(["record_not_found"]);
        }
        //基础数据
        $detailData['base_data']['title_url_oss'] = AliOSS::replaceCdnDomainForDss($detailData['base_data']['title_url']);
        $detailData['base_data']['name'] = Util::textDecode($detailData['base_data']['name']);
        $detailData['base_data']['activity_desc'] = Util::textDecode($detailData['base_data']['activity_desc']);
        $detailData['base_data']['max_hit'] = $detailData['base_data']['max_hit_type'] == LotteryActivityModel::MAX_HIT_TYPE_UNLIMITED ? 0 : $detailData['base_data']['max_hit'];
        $detailData['base_data']['day_max_hit'] = $detailData['base_data']['day_max_hit_type'] == LotteryActivityModel::MAX_HIT_TYPE_UNLIMITED ? 0 : $detailData['base_data']['day_max_hit'];
        //奖品数据
        list($detailData['awards'],$detailData['goods_list']) = self::formatEntityAwardDetailData($detailData['awards']);
        //抽奖次数
        foreach ($detailData['lottery_times_rule'] as &$lr) {
            $lr['low_pay_amount'] /= 100;
            $lr['high_pay_amount'] /= 100;
        }
        //中奖规则
        foreach ($detailData['win_prize_rule'] as &$wr) {
            $wr['low_pay_amount'] /= 100;
            $wr['high_pay_amount'] /= 100;
        }
        return $detailData;
    }

    /**
     * 格式化处理奖品数据
     * @param $detailAwardData
     * @return array
     */
    private static function formatEntityAwardDetailData($detailAwardData): array
    {
        $detailAwardData = array_map(function (&$w) use (&$goodsIds) {
            $w += json_decode($w['award_detail'], true);
            if ($w['type'] == Constants::AWARD_TYPE_TYPE_ENTITY) {
                $goodsIds[] = $w['common_award_id'];
            }
            return $w;
        }, $detailAwardData);
        $goodsInfo = [];
        if (!empty($goodsIds)) {
            //商品信息
            $goodsInfo = (new Erp())->getLogisticsGoodsList(['goods_id' => implode(',', $goodsIds)])['data']['list'];
        }
        $dictData = DictConstants::getTypesMap([
            DictConstants::AWARD_LEVEL['type'],
            DictConstants::AWARD_TYPE['type'],
        ]);
        $awardCount = count($detailAwardData);
        foreach ($detailAwardData as $avk => &$avl) {
            $avl['img_url_oss'] = AliOSS::replaceCdnDomainForDss($avl['img_url']);
            $avl['hit_times'] = json_decode($avl['hit_times'], true);
            $avl['weight'] = $avl['weight'] / 100;
            $avl['award_level_zh'] = $dictData[DictConstants::AWARD_LEVEL['type']][$avl['level']]['value'];
            $avl['award_type_zh'] = $dictData[DictConstants::AWARD_TYPE['type']][$avl['type']]['value'];
            unset($avl['award_detail']);
            //兜底奖品
            if ($avk == $awardCount - 1) {
                $avl['num'] = 0;
                $avl['rest_num'] = 0;
                $avl['award_level_zh'] = "兜底奖";
            }
        }
        return [$detailAwardData, $goodsInfo];
    }

    /**
     * 活动参与记录数据
     * @param $searchParams
     * @param $page
     * @param $pageSize
     * @return array
     */
    public static function joinRecords($searchParams, $page, $pageSize): array
    {
        $recordData = LotteryAwardRecordService::search($searchParams, $page, $pageSize);
        $recordData['is_can_update_address'] = Constants::STATUS_TRUE;
        if (time() > ($recordData['activity_end_time'] + 7 * Util::TIMESTAMP_ONEDAY)) {
            $recordData['is_can_update_address'] = Constants::STATUS_FALSE;
        }
        if (empty($recordData['list'])) {
            return $recordData;
        }
        $recordData['list'] = self::formatJoinRecordsData($recordData['list']);
        return $recordData;
    }

    /**
     * 导出活动中奖记录表格
     * @param $searchParams
     * @return string
     * @throws RunTimeException
     */
    public static function exportRecords($searchParams): string
    {
        $recordData = LotteryAwardRecordService::search($searchParams, 1, 0);
        if (empty($recordData['list'])) {
            throw new RunTimeException(["record_not_found"]);
        }
        $formatData = self::formatJoinRecordsData($recordData['list']);
        $title = [
            '中奖时间',
            '奖品等级',
            '奖品名称',
            '奖品类型',
            '学员名',
            '手机号',
            'uuid',
            '收货人手机号',
            '收货人姓名',
            '收货人地址',
            '发货状态',
            '发货单号',
        ];
        $dataResult = [];
        foreach ($formatData as $fv) {
            $dataResult[] = [
                'create_time'        => $fv['create_time'],
                'award_level_zh'     => $fv['award_level_zh'],
                'award_name'         => $fv['name'],
                'award_type_zh'      => $fv['award_type_zh'],
                'student_name'       => $fv['student_name'],
                'mobile'             => $fv['mobile'],
                'uuid'               => $fv['uuid'] . "\t",
                'shipping_mobile'    => $fv['shipping_mobile'],
                'shipping_receiver'  => $fv['shipping_receiver'],
                'shipping_address'   => $fv['shipping_address'],
                'shipping_status_zh' => $fv['shipping_status_zh'],
                'express_number'     => '[' . $fv['logistics_company'] . ']' . $fv['express_number'],
            ];
        }
        $fileName = $recordData['activity_name'] . '(' . date("Y-m-d H:i:s") . mt_rand(1, 100) . ')参与记录.xlsx';
        $tmpFileSavePath = ExcelImportFormat::createExcelTable($dataResult, $title,
            ExcelImportFormat::OUTPUT_TYPE_SAVE_FILE);
        $ossPath = $_ENV['ENV_NAME'] . '/' . AliOSS::DIR_TMP_EXCEL . '/' . $fileName;
        AliOSS::uploadFile($ossPath, $tmpFileSavePath);
        unlink($tmpFileSavePath);
        return AliOSS::replaceCdnDomainForDss($ossPath);
    }

    /**
     * 格式化活动参与记录数据
     * @param $recordsData
     * @return array
     */
    private static function formatJoinRecordsData($recordsData): array
    {
        $dictData = DictConstants::getTypesMap([
            DictConstants::AWARD_TYPE['type'],
            DictConstants::SHIPPING_STATUS['type'],
            DictConstants::AWARD_LEVEL['type'],
        ]);
        $studentData = array_column(ErpStudentModel::getRecords(['uuid' => array_column($recordsData, 'uuid')],
            ['uuid', 'mobile', 'name']), null, 'uuid');
        //收货地址信息
        $awardAddressData = [];
        foreach (array_column($recordsData, 'address_detail') as $ad) {
            $tmpAddressObj = json_decode($ad);
            if (!empty($tmpAddressObj)) {
                $awardAddressData[$tmpAddressObj->id] = json_decode($ad);
            }
        }
        foreach ($recordsData as &$rv) {
            $rv['mobile'] = Util::hideUserMobile($studentData[$rv['uuid']]['mobile']);
            $rv['student_name'] = $studentData[$rv['uuid']]['name'];
            $rv['award_type_zh'] = $dictData[DictConstants::AWARD_TYPE['type']][$rv['award_type']]['value'];
            $rv['award_level_zh'] = $dictData[DictConstants::AWARD_LEVEL['type']][$rv['level']]['value'];
            $rv['shipping_status_zh'] = $dictData[DictConstants::SHIPPING_STATUS['type']][$rv['shipping_status']]['value'];
            $rv['shipping_address'] = $awardAddressData[$rv['erp_address_id']]->address;
            $rv['shipping_mobile'] = $awardAddressData[$rv['erp_address_id']]->mobile;
            $rv['shipping_receiver'] = $awardAddressData[$rv['erp_address_id']]->name;
            $rv['create_time'] = date("Y-m-d H:i:s", $rv['create_time']);
            unset($rv['address_detail']);
        }
        return $recordsData;
    }

    /**
     * 编辑收货地址
     * @param $params
     * @return bool
     * @throws RunTimeException
     */
    public static function updateShippingAddress($params): bool
    {
        $recordId = $params['record_id'];
        $params['default'] = $params['is_default'];
        unset($params['record_id']);
        unset($params['is_default']);
        $updateRes = LotteryAwardRecordService::updateAwardShippingAddress($recordId, $params);
        if (empty($updateRes)) {
            throw new RuntimeException(["update_failure"]);
        }
        return true;
    }

    /**
     * 取消发货
     * @param $params
     * @param $employeeUuid
     * @return bool
     * @throws RunTimeException
     */
    public static function cancelDeliver($params, $employeeUuid): bool
    {
        if (!is_array($params['record_id'])) {
            throw new RuntimeException(["record_id_is_array"]);
        }
        $res = LotteryAwardRecordService::cancelDeliver($params['record_id'], $params['op_activity_id'], $employeeUuid);
        if (empty($res)) {
            throw new RuntimeException(["update_failure"]);
        }
        return true;
    }

    /**
     * 获取物流详情
     * @param $opActivityId
     * @param $uniqueId
     * @return array
     */
    public static function expressDetail($opActivityId, $uniqueId): array
    {
        return LotteryAwardRecordService::expressDetail($opActivityId, $uniqueId);
    }

    /**
     * 编辑状态
     * @param $opActivityId
     * @param $status
     * @param $employeeUuid
     * @return bool
     * @throws RunTimeException
     */
    public static function updateEnableStatus($opActivityId, $status, $employeeUuid): bool
    {
        $res = LotteryActivityService::updateEnableStatus($opActivityId,
            ['status' => $status, 'update_uuid' => $employeeUuid, 'update_time' => time()]);
        if (empty($res)) {
            throw new RuntimeException(["update_failure"]);
        }
        return true;
    }
}