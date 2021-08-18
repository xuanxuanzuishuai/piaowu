<?php


namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\DictConstants;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\MysqlDB;
use App\Libs\SimpleLogger;
use App\Libs\Util;
use App\Libs\WeChat\WeChatMiniPro;
use App\Models\BannerConfigModel;
use App\Models\DictModel;
use App\Models\Dss\DssStudentModel;
use App\Models\Dss\DssUserQrTicketModel;
use App\Models\EmployeeModel;
use App\Models\Erp\ErpPackageV1Model;
use App\Models\Erp\ErpStudentAccountDetail;
use App\Models\Erp\ErpStudentModel;
use App\Models\ShareConfigModel;
use App\Models\ShareMaterialConfig;
use App\Models\SourceMaterialModel;
use App\Models\StudentReferralStudentStatisticsModel;
use App\Models\TemplatePosterModel;
use App\Models\TemplatePosterWordModel;
use App\Libs\RedisDB;

class SourceMaterialService
{

    const SOURCE_TYPE_CONFIG = 'source_type_config';

    const SOURCE_ENABLE_STATUS = 'source_enable_status';

    const IS_DISPLAY  = 1;  //显示
    const NOT_DISPLAY = 2; //隐藏

    const WAN_UNIT = 10000; //单位万

    /**
     * 素材添加
     * @param $data
     * @param $employeeId
     * @return int|mixed|string
     * @throws RunTimeException
     */
    public static function soucrceAdd($data, $employeeId)
    {
        $data['create_time'] = time();
        $data['operator_id'] = $employeeId;
        $data['mark']        = !empty($data['mark']) ? Util::textEncode($data['mark']) : '';

        $res = SourceMaterialModel::insertRecord($data);
        if (empty($res)) {
            SimpleLogger::error('source material add fail', $data);
            throw new RunTimeException(['insert_failure']);
        }
        return $res;
    }

    /**
     * 素材编辑
     * @param $data
     * @param $employeeId
     * @return int
     * @throws RunTimeException
     */
    public static function soucrceEdit($data, $employeeId)
    {
        $sourceMaterial = SourceMaterialModel::getRecord(['id' => $data['id']]);
        if (empty($sourceMaterial)) {
            throw new RunTimeException(['record_not_found']);
        }
        $update = [
            'name' => $data['name'],
            'mark' => !empty($data['mark']) ? Util::textEncode($data['mark']) : '',
        ];
        if ($sourceMaterial['enable_status'] == 1) {
            $update['type']       = $data['type'];
            $update['image_path'] = $data['image_path'];
        }
        $update['update_time'] = time();
        $update['operator_id'] = $employeeId;
        $res = SourceMaterialModel::updateRecord($data['id'], $update);
        if (empty($res)) {
            SimpleLogger::error('source material edit fail', $update);
            throw new RunTimeException(['update_failure']);
        }
        return $res;
    }

    /**
     * 素材库列表
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     */
    public static function sourceList($params, $page, $limit)
    {
        $limitOffset = [($page - 1) * $limit, $limit];
        list($lists, $totalCount) = SourceMaterialModel::sourceList($params, $limitOffset);
        if (empty($lists)) {
            return compact('lists', 'totalCount');
        }
        $dictInfos = DictService::getList(self::SOURCE_TYPE_CONFIG);
        $dictInfos = !empty($dictInfos) ? array_column($dictInfos, 'key_value', 'key_code') : [];
        foreach ($lists as &$val) {
            $val['status']        = $val['enable_status'];
            $val['type_value']    = $dictInfos[$val['type']] ?? '';
            $val['image_path']    = AliOSS::replaceCdnDomainForDss($val['image_path']);
            $val['enable_status'] = SourceMaterialModel::$enableStatusLists[$val['enable_status']] ?? '未知';
            $val['create_time']   = date('Y-m-d H:i:s', $val['create_time']);
        }
        return compact('lists', 'totalCount');
    }

    /**
     * 素材类型添加
     * @param $data
     * @param $employeeId
     * @return int|mixed|string
     * @throws RunTimeException
     */
    public static function sourceTypeAdd($data)
    {
        //查询是否有重复的
        $conds = [
            'type'      => self::SOURCE_TYPE_CONFIG,
            'key_value' => $data['name']
        ];
        $count = DictModel::getCount($conds);
        if ($count > 0) {
            throw new RunTimeException(['source_type_is_repeat']);
        }
        $insert = [
            'type'      => self::SOURCE_TYPE_CONFIG,
            'key_value' => $data['name']
        ];
        unset($conds['key_value']);
        $conds['ORDER']     = ['id' => 'DESC'];
        $dict               = DictModel::getRecord($conds, ['key_code']);

        $insert['key_code'] = empty($dict) ? 0 : $dict['key_code'] + 1;
        $res                = DictModel::insertRecord($insert);
        if (empty($res)) {
            SimpleLogger::error('source material type add fail', $insert);
            throw new RunTimeException(['source_material_type_add_fail']);
        }
        // 缓存失效
        DictModel::delCache(self::SOURCE_TYPE_CONFIG, 'dict_list_');
        return $res;
    }

    /**
     * 启用状态修改
     * @param $id
     * @param $enableStatus
     * @param $employeeId
     * @return int
     * @throws RunTimeException
     */
    public static function editEnableStatus($id, $enableStatus, $employeeId)
    {
        $sourceMaterial = SourceMaterialModel::getRecord(['id' => $id]);
        if (empty($sourceMaterial) || $sourceMaterial['enable_status'] != SourceMaterialModel::NOT_ENABLED_STATUS) {
            throw new RunTimeException(['record_not_found']);
        }
        $update = [
            'enable_status' => $enableStatus,
            'operator_id'   => $employeeId,
        ];
        $res    = SourceMaterialModel::updateRecord($id, $update);
        if (empty($res)) {
            throw new RunTimeException(['update_failure']);
        }
        return $res;
    }

    /**
     * 分享配置新增
     * @param $request
     * @return int|mixed|string
     * @throws RunTimeException
     */
    public static function shareAdd($request)
    {
        $shareInfo = ShareConfigModel::getRecord([]);
        if ($shareInfo) {
            throw new RunTimeException(["record_exist"]);
        }
        $time      = time();
        $shareInfo = [
            'image_path'  => $request['image_path'],
            'remark'      =>  Util::textEncode($request['remark']),
            'operator_id' => $request['employee_id'],
            'create_time' => $time,
        ];
        $db        = MysqlDB::getDB();
        $db->beginTransaction();
        //保存活动总表信息
        $shareConfigId = ShareConfigModel::insertRecord($shareInfo);
        if (empty($shareConfigId)) {
            $db->rollBack();
            SimpleLogger::info("shareAdd insert share_config fail", ['data' => $shareInfo]);
            throw new RunTimeException(["add share config fail"]);
        }
        //保存海报分享语关联关系
        foreach ($request['poster_id'] as $val) {
            $shareMaterialInfo[] = [
                'share_config_id' => $shareConfigId,
                'type'            => ShareMaterialConfig::POSTER_TYPE,
                'material_id'     => $val,
                'operator_id'     => $request['employee_id'],
                'create_time'     => $time
            ];
        }
        foreach ($request['poster_word_id'] as $val) {
            $shareMaterialInfo[] = [
                'share_config_id' => $shareConfigId,
                'type'            => ShareMaterialConfig::POSTER_WORD_TYPE,
                'material_id'     => $val,
                'operator_id'     => $request['employee_id'],
                'create_time'     => $time
            ];
        }
        $shareMaterialRes = ShareMaterialConfig::batchInsert($shareMaterialInfo);
        if (empty($shareMaterialRes)) {
            $db->rollBack();
            SimpleLogger::info("shareAdd insert share_material_config fail", ['data' => $shareMaterialInfo]);
            throw new RunTimeException(["add share material config fail"]);
        }
        $db->commit();
        return $shareConfigId;
    }

    /**
     * 分享配置更新
     * @param $request
     * @return mixed
     * @throws RunTimeException
     */
    public static function shareEdit($request)
    {
        $shareInfo = ShareConfigModel::getRecord(['id' => $request['id']]);
        if (empty($shareInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        $time      = time();
        $shareInfo = [
            'image_path'  => $request['image_path'],
            'remark'      => Util::textEncode($request['remark']),
            'operator_id' => $request['employee_id'],
            'update_time' => $time,
        ];
        //处理海报分享语关联数据
        $shareMaterialData = ShareMaterialConfig::getRecords(['share_config_id' => $request['id'], 'status' => ShareMaterialConfig::NORMAL_STATUS], ['material_id','type']);
        foreach ($shareMaterialData as $val) {
            if ($val['type'] == ShareMaterialConfig::POSTER_WORD_TYPE) {
                $posterWordIds[] = $val['material_id'];
            } elseif ($val['type'] == ShareMaterialConfig::POSTER_TYPE) {
                $posterIds[] = $val['material_id'];
            }
        }
        //海报
        $diffPoster = $request['poster_id'] === $posterIds ? true : false;
        if (!$diffPoster) {
            foreach ($request['poster_id'] as $val) {
                $shareMaterialInfo[] = [
                    'share_config_id' => $request['id'],
                    'type'            => ShareMaterialConfig::POSTER_TYPE,
                    'material_id'     => $val,
                    'operator_id'     => $request['employee_id'],
                    'create_time'     => $time
                ];
            }
        }
        //分享语
        $diffPosterWord = $request['poster_word_id'] === $posterWordIds ? true : false;
        if (!$diffPosterWord) {
            foreach ($request['poster_word_id'] as $val) {
                $shareMaterialInfo[] = [
                    'share_config_id' => $request['id'],
                    'type'            => ShareMaterialConfig::POSTER_WORD_TYPE,
                    'material_id'     => $val,
                    'operator_id'     => $request['employee_id'],
                    'create_time'     => $time
                ];
            }
        }
        $db = MysqlDB::getDB();
        $db->beginTransaction();
        //保存分享配置信息
        $shareConfigRes = ShareConfigModel::updateRecord($request['id'], $shareInfo);
        if (empty($shareConfigRes)) {
            $db->rollBack();
            SimpleLogger::info("shareEdit edit share_config fail", ['data' => $shareInfo]);
            throw new RunTimeException(["edit share config fail"]);
        }
        //保存海报分享语关联数据
        if (!$diffPoster) {
            $updatePosterInfo = [
                'status' => ShareMaterialConfig::DISABLE_STATUS,
                'update_time' => $time
            ];
            $updateShareMaterialRes = ShareMaterialConfig::batchUpdateRecord($updatePosterInfo, ['share_config_id' => $request['id'], 'type' => ShareMaterialConfig::POSTER_TYPE]);
            if (empty($updateShareMaterialRes)) {
                $db->rollBack();
                SimpleLogger::info("shareEdit edit share_material_config fail", ['data' => $shareMaterialInfo]);
                throw new RunTimeException(["edit share material config fail"]);
            }
        }
        if (!$diffPosterWord) {
            $updatePosterWordInfo = [
                'status' => ShareMaterialConfig::DISABLE_STATUS,
                'update_time' => $time
            ];
            $updateShareMaterialRes = ShareMaterialConfig::batchUpdateRecord($updatePosterWordInfo, ['share_config_id' => $request['id'], 'type' => ShareMaterialConfig::POSTER_WORD_TYPE]);
            if (empty($updateShareMaterialRes)) {
                $db->rollBack();
                SimpleLogger::info("shareEdit edit share_material_config fail", ['data' => $shareMaterialInfo]);
                throw new RunTimeException(["edit share material config fail"]);
            }
        }
        if (!empty($shareMaterialInfo)) {
            $addShareMaterialRes = ShareMaterialConfig::batchInsert($shareMaterialInfo);
            if (empty($addShareMaterialRes)) {
                $db->rollBack();
                SimpleLogger::info("shareAdd insert share_material_config fail", ['data' => $shareMaterialInfo]);
                throw new RunTimeException(["add share material config fail"]);
            }
        }
        $db->commit();
        return $request['id'];
    }

    /**
     * 分享配置详情
     * @param $request
     * @return array
     * @throws RunTimeException
     */
    public static function shareDetail()
    {
        $shareInfo = ShareConfigModel::getRecord([]);
        if (empty($shareInfo)) {
            return [];
        }
        $shareMaterialInfo = ShareMaterialConfig::getRecords(['share_config_id' => $shareInfo['id'], 'status' => ShareMaterialConfig::NORMAL_STATUS, 'show_status' => ShareMaterialConfig::NORMAL_SHOW_STATUS]);
        foreach ($shareMaterialInfo as $val) {
            if ($val['type'] == ShareMaterialConfig::POSTER_WORD_TYPE) {
                $posterWordIds[] = $val['material_id'];
            } elseif ($val['type'] == ShareMaterialConfig::POSTER_TYPE) {
                $posterIds[] = $val['material_id'];
            }
        }
        //获取海报列表
        if (!empty($posterIds)) {
            $posterLists = TemplatePosterModel::getRecords(['id' => $posterIds], ['id', 'name', 'poster_path']);
            $posterLists = !empty($posterLists) ? array_column($posterLists, null, 'id') : [];
            $posterInfos = [];
            foreach ($posterIds as $key => $val) {
                if (!isset($posterLists[$val])) {
                    continue;
                }
                $posterInfos[] = [
                    'id'          => $val,
                    'name'        => $posterLists[$val]['name'],
                    'image_path'  => $posterLists[$val]['poster_path'],
                    'poster_path' => AliOSS::replaceCdnDomainForDss($posterLists[$val]['poster_path']),
                    'poster_type' => TemplatePosterModel::STANDARD_POSTER_TXT,
                    'order'       => $key + 1
                ];
            }
        }

        //获取分享语列表
        if (!empty($posterWordIds)) {
            $posterWorkLists = TemplatePosterWordModel::getRecords(['id' => $posterWordIds], ['id', 'content']);
            $posterWorkLists = !empty($posterWorkLists) ? array_column($posterWorkLists, null, 'id') : [];
            $posterWordInfos = [];
            foreach ($posterWordIds as $key => $val) {
                if (!isset($posterWorkLists[$val])) {
                    continue;
                }
                $posterWordInfos[] = [
                    'id'      => $val,
                    'content' => Util::textDecode($posterWorkLists[$val]['content']),
                    'order'   => $key + 1
                ];

            }
        }
        $data = [
            'id'                => $shareInfo['id'],
            'image_path'        => $shareInfo['image_path'],
            'image_url'         => AliOSS::replaceCdnDomainForDss($shareInfo['image_path']),
            'remark'            => Util::textDecode($shareInfo['remark']),
            'poster_lists'      => $posterInfos ?? [],
            'poster_work_lists' => $posterWordInfos ?? [],
        ];
        return $data;
    }

    /**
     * banner保存
     * @param $request
     * @return int|mixed|string
     * @throws RunTimeException
     */
    public static function bannerSave($request)
    {
        if (!Util::checkPositiveInteger($request['order'])) {
            throw new RunTimeException(['order_is_positive_integer']);
        }
        $time = time();
        if (strtotime($request['start_time']) >= strtotime($request['end_time'])) {
            throw new RunTimeException(['start_time_eq_end_time']);
        }
        if (strtotime($request['end_time'])  < $time) {
            throw new RunTimeException(['end_time_eq_time']);
        }
        $bannerInfo = [
            'app_id'      => $request['app_id'],
            'name'        => $request['name'],
            'site_type'   => $request['site_type'],
            'user_group'  => $request['user_group'],
            'start_time'  => strtotime($request['start_time']),
            'end_time'    => strtotime($request['end_time']),
            'image_type'  => $request['image_type'],
            'image_path'  => $request['image_path'],
            'jump_rule'   => $request['jump_rule'],
            'jump_url'    => $request['jump_rule'] == BannerConfigModel::IS_ALLOW_JUMP ? urlencode($request['jump_url']) : '',
            'order'       => $request['order'],
            'remark'      => $request['remark'],
            'operator_id' => $request['employee_id'],
        ];
        if (empty($request['id'])) {
            $bannerInfo['create_time'] = $time;
            $bannerConfigId =  BannerConfigModel::insertRecord($bannerInfo);
            if (empty($bannerConfigId)) {
                SimpleLogger::info("bannerAdd insert banner_config fail", ['data' => $bannerInfo]);
                throw new RunTimeException(["add banner config fail"]);
            }
        } else {
            $bannerConfigId = $request['id'];
            $bannerData     = BannerConfigModel::getRecord(['id' => $request['id']], 'id');
            if (empty($bannerData)) {
                throw new RunTimeException(['record_not_found']);
            }
            $bannerInfo['update_time'] = $time;
            $updateBannerConfigRes = BannerConfigModel::updateRecord($request['id'], $bannerInfo);
            if (empty($updateBannerConfigRes)) {
                SimpleLogger::info("bannerEdit edit banner_config fail", ['data' => $bannerInfo]);
                throw new RunTimeException(["edit banner config fail"]);
            }
        }
        return $bannerConfigId;
    }

    /**
     * 上下架编辑
     * @param $request
     * @return bool
     * @throws RunTimeException
     */
    public static function bannerEditEnableStatus($request)
    {
        $bannerConfigInfo = BannerConfigModel::getRecord(['id' => $request['id']]);
        if ($bannerConfigInfo['status'] == $request['enable_status']) {
            throw new RunTimeException(['invalid_status']);
        }
        if (empty($bannerConfigInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        $statusArray = array_keys(BannerConfigModel::$statusArray);
        if (!in_array($request['enable_status'], $statusArray)) {
            throw new RunTimeException(['enable_status_invalid_error']);
        }
        $update = [
            'status'      => $request['enable_status'],
            'operator_id' => $request['employee_id'],
            'update_time' => time()
        ];
        $res    = BannerConfigModel::updateRecord($request['id'], $update);
        if (empty($res)) {
            throw new RunTimeException(['update_failure']);
        }
        return true;
    }

    /**
     * 获取下拉列表
     * @return mixed
     */
    public static function selectLists()
    {
        $data  = DictService::getListsByTypes(['APP_ID','SHARE_SITE_TYPE','SHARE_USER_GROUP','SHARE_JUML_RULE','SHARE_BANNER_STATUS']);
        return $data;
    }


    /**
     * banner列表
     * @param $params
     * @param $page
     * @param $limit
     * @return array
     */
    public static function bannerLists($params, $page, $limit)
    {
        $limitOffset = [($page - 1) * $limit, $limit];
        list($lists, $totalCount) = BannerConfigModel::bannerLists($params, $limitOffset);
        if (empty($lists)) {
            return compact('lists', 'totalCount');
        }
        $dictInfos = DictService::getListsByTypes(['SHARE_SITE_TYPE', 'SHARE_USER_GROUP']);

        $shareSiteType  = !empty($dictInfos['SHARE_SITE_TYPE']) ? array_column($dictInfos['SHARE_SITE_TYPE'], 'value', 'code') : [];
        $shareUserGroup = !empty($dictInfos['SHARE_USER_GROUP']) ? array_column($dictInfos['SHARE_USER_GROUP'], 'value', 'code') : [];
        //获取操作人
        $employeeIds  = array_column($lists, 'operator_id');
        $employeeInfo = EmployeeModel::getRecords(['id' => $employeeIds], ['id', 'name']);
        $employeeInfo = !empty($employeeInfo) ? array_column($employeeInfo, 'name', 'id') : [];


        $time = time();
        foreach ($lists as &$val) {
            //状态展示处理
            if (in_array($val['status'], [BannerConfigModel::INITIAL_STATUS, BannerConfigModel::DISABLE_STATUS])) {
                $val['status_txt'] = BannerConfigModel::$statusArray[$val['status']] ?? '未知';
            } elseif ($val['status'] == BannerConfigModel::NORMAL_STATUS) {
                if ($time >= $val['start_time'] && $time <= $val['end_time']) {
                    $val['status_txt'] = BannerConfigModel::IN_PROGRESS;
                } elseif ($time < $val['start_time']) {
                    $val['status_txt'] = BannerConfigModel::NOT_STARTED;
                } elseif ($time > $val['end_time']) {
                    $val['status_txt'] = BannerConfigModel::HAS_ENDED;
                }
            }
            $val['site_type_txt']  = $shareSiteType[$val['site_type']] ?? '';
            $val['user_group_txt'] = $shareUserGroup[$val['user_group']] ?? '';
            $val['start_time']     = date('Y-m-d H:i:s', $val['start_time']);
            $val['end_time']       = date('Y-m-d H:i:s', $val['end_time']);
            $val['operator_name']  = $employeeInfo[$val['operator_id']] ?? '';
            $val['image_path']     = AliOSS::replaceCdnDomainForDss($val['image_path']);
            $val['create_time']    = date('Y-m-d H:i:s', $val['create_time']);
            $val['jump_url']       = $val['jump_rule'] == BannerConfigModel::IS_ALLOW_JUMP ? urldecode($val['jump_url']) : '';
        }
        return compact('lists', 'totalCount');
    }

    /**
     * banner详情
     * @param $request
     * @return mixed
     * @throws RunTimeException
     */
    public static function bannerDetail($request)
    {
        $bannerInfo = BannerConfigModel::getRecord(['id' => $request['id']]);
        if (empty($bannerInfo)) {
            throw new RunTimeException(['record_not_found']);
        }
        $bannerInfo['image_url']  = AliOSS::replaceCdnDomainForDss($bannerInfo['image_path']);
        $bannerInfo['jump_url']   = $bannerInfo['jump_rule'] == BannerConfigModel::IS_ALLOW_JUMP ? urldecode($bannerInfo['jump_url']) : '';

        return $bannerInfo;
    }

    /**
     * 获取button信息
     * @param $request
     * @return array
     * @throws RunTimeException
     */
    public static function getHtmlButtonInfo($request)
    {
        $student = DssStudentModel::getRecord(['id' => $request['student_id']], ['id']);
        if (empty($student)) {
            throw new RunTimeException(['record_not_found']);
        }
        $goldLeaf    = self::getGoldLeafMaxNum();
        $time        = time();
        $conds       = [
            'status'         => BannerConfigModel::NORMAL_STATUS,
            'start_time[<=]' => $time,
            'end_time[>=]'   => $time,
            'ORDER'          => ['order' => 'ASC','create_time' => 'DESC']
        ];
        $bannerLists = BannerConfigModel::getRecords($conds, ['id', 'image_path','jump_url','jump_rule']);
        $defaultBanner = DictConstants::get(DictConstants::SALE_SHOP_CONFIG, 'dafault_banner');
        if (empty($bannerLists)) {
            array_push($bannerLists, ['id' => 1, 'image_path' => $defaultBanner, 'jump_url' => '']);
        }
        foreach ($bannerLists as &$val) {
            $val['image_path'] = AliOSS::replaceCdnDomainForDss($val['image_path']);
            $val['jump_url']   = $val['jump_rule'] == BannerConfigModel::IS_ALLOW_JUMP ? urldecode($val['jump_url']) : '';
        }
        $data = [
            'gold_leaf_num' => $goldLeaf / self::WAN_UNIT,
            'banner_list'   => $bannerLists
        ];
        return $data;
    }


    /**
     * 获取海报列表
     * @param $request
     * @return array
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getPosterLists($request)
    {
        $student = DssStudentModel::getRecord(['id' => $request['student_id']], ['id']);
        if (empty($student)) {
            throw new RunTimeException(['record_not_found']);
        }
        $goldLeaf   = self::getGoldLeafMaxNum();
        $shareInfo  = self::shareDetail();
        $posterList = $shareInfo['poster_lists'] ?? [];
        if (!empty($posterList)) {
            $posterList = self::getCommonPosterLists($posterList, ['student_id' => $request['student_id'], 'channel_id' => $request['channel_id']]);
        }
        $data = [
            'amount'        => ceil($goldLeaf / ErpPackageV1Model::DEFAULT_SCALE_NUM),
            'gold_leaf_num' => $goldLeaf / self::WAN_UNIT,
            'lists'         => $posterList
        ];
        return $data;
    }

    /**
     * 分享语列表
     * @param $request
     * @return array
     * @throws RunTimeException
     */
    public static function getPosterWordLists($request)
    {
        $student = DssStudentModel::getRecord(['id' => $request['student_id']], ['id']);
        if (empty($student)) {
            throw new RunTimeException(['record_not_found']);
        }
        $shareInfo  = self::shareDetail();
        return ['lists' => $shareInfo['poster_work_lists'] ?? []];
    }

    /**
     * app-获取banner及小程序信息
     * @param $request
     * @return array
     * @throws RunTimeException
     */
    public static function bannerInfo($request)
    {
        $shareInfo  = ShareConfigModel::getRecord([]);
        $bannerInfo = self::getHtmlButtonInfo($request);

        $wechat          = WeChatMiniPro::factory(Constants::SMART_APP_ID, Constants::SMART_APP_ID);
        $wechatInitialId = DictConstants::get(DictConstants::WECHAT_INITIAL_ID, $wechat->nowWxApp);
        $webpageUrl      = DictConstants::get(DictConstants::SALE_SHOP_CONFIG, 'home_index');
        $qrType          = DictConstants::get(DictConstants::MINI_APP_QR, 'qr_type_mini');
        $studentStatus   = StudentService::dssStudentStatusCheck($request['student_id']);
        $qrData = [
            'user_id'        => $request['student_id'],
            'user_type'      => DssUserQrTicketModel::STUDENT_TYPE,
            'channel_id'     => $request['channel_id'],
            'user_status'    => $studentStatus['student_status'],
            'app_id'         => Constants::SMART_APP_ID,
            'qr_type'        => $qrType,
        ];
        $qrInfo = QrInfoService::getQrIdList([$qrData]);
        $qrId = !empty($qrInfo) ? end($qrInfo)['qr_id'] : null;
        if (empty($qrId)) {
            throw new RunTimeException(["create_qr_id_error"]);
        }
        $bannerInfo['mini_app'] = [
            'title'           => Util::textDecode($shareInfo['remark']) ?? '',
            'description'     => '',
            'userName'        => $wechatInitialId,
            'path'            => sprintf('pages/index?scene=%s', $qrId),
            'previewImageUrl' => !empty($shareInfo['image_path']) ? AliOSS::replaceCdnDomainForDss($shareInfo['image_path']) : '',
            'webpageUrl'      => $webpageUrl
        ];
        return $bannerInfo;
    }


    /**
     * 跑马灯数据-获取用户金叶子相关信息
     * @return array
     */
    public static function userRewardDetails()
    {
        // 从redis缓存读取
        $redis = RedisDB::getConn();
        $cacheKey = 'user_reward_details';
        $value = $redis->get($cacheKey);
        if (!empty($value)) {
            return json_decode($value, true);
        }
        //获取邀请用户年卡数量前20人
        $referral = StudentReferralStudentStatisticsModel::getReferralBySort();
        if (empty($referral)) {
            return [];
        }
        $referral    = array_column($referral, null, 'referee_id');
        $referralIds = array_keys($referral);
        //获取推荐人信息
        $student = DssStudentModel::getRecords(['id' => $referralIds], ['id', 'name', 'mobile', 'thumb','uuid']);
        if (empty($student)) {
            return [];
        }
        $student = array_column($student, null, 'uuid');
        $uuids   = array_column($student, 'uuid');

        //获取erp学生信息
        $erpStudent = ErpStudentModel::getRecords(['uuid' => $uuids], ['id','uuid']);
        if (empty($erpStudent)) {
            return [];
        }
        $erpStudent    = array_column($erpStudent, 'uuid', 'id');
        $erpStudentIds = array_keys($erpStudent);
        $accountDetail = ErpStudentAccountDetail::getUserRewardTotal($erpStudentIds);
        if (empty($accountDetail)) {
            return [];
        }
        array_multisort(array_column($accountDetail, 'total'), SORT_DESC, $accountDetail);
        //用户默认头像
        $defaultAvatar =  AliOSS::replaceCdnDomainForDss(DictConstants::get(DictConstants::STUDENT_DEFAULT_INFO, 'default_thumb'));
        foreach ($accountDetail as &$val) {
            $uuid        = $erpStudent[$val['student_id']] ?? '';
            $studentInfo = $student[$uuid] ?? [];
            if (empty($studentInfo)) {
                continue;
            }
            $val['invite_num']    = $referral[$studentInfo['id']]['num'] ?? 0;
            $val['gold_leat_num'] = ceil($val['total'] / (ErpPackageV1Model::DEFAULT_SCALE_NUM * self::WAN_UNIT));
            $val['name']          = $studentInfo['name'] ?: sprintf('尾号%s', substr($studentInfo['mobile'], 7));
            $val['avatar']        = !empty($studentInfo['thumb']) ? AliOSS::replaceCdnDomainForDss($studentInfo['thumb']) : $defaultAvatar;
        }
        $redis->setex($cacheKey, Util::TIMESTAMP_1H, json_encode($accountDetail));
        return $accountDetail;
    }

    /**
     * 海报批量获取小程序码
     * @param $posterList
     * @param $extData
     * @return mixed
     * @throws RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function getCommonPosterLists($posterList, $extParams)
    {
        $posterConfig = PosterService::getPosterConfig();
        foreach ($posterList as &$item) {
            $_tmp['poster_id']    = $item['id'];
            $_tmp['user_id']      = $extParams['student_id'];
            $_tmp['user_type']    = DssUserQrTicketModel::STUDENT_TYPE;
            $_tmp['channel_id']   = $extParams['channel_id'];
            $_tmp['landing_type'] = DssUserQrTicketModel::LANDING_TYPE_NORMAL;
            $_tmp['qr_sign']      = QrInfoService::createQrSign($_tmp);
            $userQrParams[]       = $_tmp;
            $item['qr_sign']      = $_tmp['qr_sign'];
        }
        unset($item);
        $userQrArr = MiniAppQrService::getUserMiniAppQrList($userQrParams);
        foreach ($posterList as &$item) {
            $extParams['poster_id'] = $item['id'];
            // 海报图：
            $poster = PosterService::generateQRPoster(
                $item['image_path'],
                $posterConfig,
                $extParams['student_id'],
                DssUserQrTicketModel::STUDENT_TYPE,
                $extParams['channel_id'],
                $extParams,
                $userQrArr[$item['qr_sign']] ?? []
            );
            $item['image_path'] = $poster['poster_save_full_path'];
        }
        return $posterList;
    }


    /**
     * 获取推荐人可获得金叶子最大数
     * @return mixed
     */
    public static function getGoldLeafMaxNum()
    {
        return DictService::getKeyValue('SALE_SHOP_CONFIG', 'GOLD_LEAF_MAX_NUM');
    }




}
