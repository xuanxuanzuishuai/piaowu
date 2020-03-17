<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/28
 * Time: 3:14 PM
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\Constants;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\BannerModel;
use App\Models\EmployeeModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentModel;

use App\Models\UserWeixinModel;

class BannerService
{
    const FILTER_NEED_SUB_WX = 'NeedSubWx';
    const FILTERS = [
        self::FILTER_NEED_SUB_WX,
    ];

    public static function getStudentBanner($studentId)
    {
        $banner = BannerModel::getBanner();

        $bannerList = [];
        foreach ($banner as $b)
        {
            $item = [
                'id' => $b['id'],
                'action_type' => $b['action_type'],
                'action' => self::prepareAction($b['action_type'], json_decode($b['action_detail'], true)),
            ];

            $showMain = $b['show_main'];
            if (!empty($b['filter'])) {
                $showMain = self::userFilter($b['filter'], $studentId);
            }
            $item['image_main'] = $showMain ? AliOSS::signUrls($b['image_main']) : '';
            $item['image_list'] = $b['show_list'] ? AliOSS::signUrls($b['image_list']) : '';

            $bannerList[] = $item;
        }

        return $bannerList;
    }

    public static function prepareAction($type, $detail)
    {
        switch ($type) {
            case BannerModel::ACTION_MINI_PRO:
                $detail['no_wx_image'] = empty($detail['no_wx_image']) ? '' : AliOSS::signUrls($detail['no_wx_image']);
                break;
        }
        return $detail;
    }

    public static function userFilter($filter, $studentId)
    {
        $result = call_user_func(self::class . '::filter' . $filter, $studentId);
        return $result;
    }

    public static function filterNeedSubWx($studentId)
    {
        $student = StudentModel::getById($studentId);

        // 检测点评课状态，未开通不推送
        if ($student['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_NO) {
            return false;
        }

        // 检测付费状态，未开启不推送
        $appSubStatus = StudentServiceForApp::checkSubStatus($student['sub_status'], $student['sub_end_date']);
        if (!$appSubStatus) {
            return false;
        }

        // 检测公众号信息，绑定且关注不推送，未绑定无法获取关注状态也推送
        $studentWeChatInfo = UserWeixinModel::getBoundInfoByUserId($studentId,
            UserCenter::AUTH_APP_ID_AIPEILIAN_STUDENT,
            WeChatService::USER_TYPE_STUDENT,
            UserWeixinModel::BUSI_TYPE_STUDENT_SERVER
        );
        if (!empty($studentWeChatInfo['open_id'])) {
            $wxUserInfo = WeChatService::getUserInfo($studentWeChatInfo['open_id']);
            if ($wxUserInfo['subscribe'] == 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * 获取列表数据
     * @param $params
     * @param $page
     * @param $count
     * @return array
     */
    public static function getList($params, $page, $count)
    {
        $res = BannerModel::getList($params, $page, $count);
        if(empty($res['count'])){
            return Valid::formatSuccess($res);
        }
        $res['data'] = self::formatListData($res['data']);
        return Valid::formatSuccess($res);
    }

    /**
     * 格式化列表数据
     * @param $data
     * @return array
     */
    public static function formatListData($data)
    {
        //获取触发动作类型MAP
        $actionTypeMap = DictService::getTypeMap(Constants::DICT_TYPE_ACTION_TYPE);

        $res = [];
        foreach($data as $value){
            $row = [];
            $row['id'] = $value['id'];
            $row['name'] = $value['name'];
            $row['create_time'] = date('Y-m-d H:i', $value['create_time']);
            $row['start_time'] = date('Y-m-d H:i', $value['start_time']);
            $row['end_time'] = date('Y-m-d H:i', $value['end_time']);
            $row['status'] = $value['status'];
            $row['operator_id'] = $value['operator'];
            $row['operator_name'] = $value['operator_name'];
            $row['sort'] = $value['sort'];
            $row['show_main'] = $value['show_main'];
            $row['image_main'] = empty($value['image_main']) ? '' : AliOSS::signUrls($value['image_main']);
            $row['show_list'] = $value['show_list'];
            $row['image_list'] = empty($value['image_list']) ? '' : AliOSS::signUrls($value['image_list']);
            $row['action_type'] = isset($actionTypeMap[$value['action_type']]) ? $actionTypeMap[$value['action_type']] : '-';
            $res[] = $row;
        }
        return $res;
    }

    /**
     * 获取banner detail
     * @param $id
     * @return array
     */
    public static function getDetail($id)
    {
        $data = BannerModel::getById($id);
        if(empty($data)){
            return Valid::addErrors([], 'banner_id_error', 'banner_id_error');
        }
        $data['image_main_url'] = empty($data['image_main']) ? '' : AliOSS::signUrls($data['image_main']);
        $data['image_list_url'] = empty($data['image_list']) ? '' : AliOSS::signUrls($data['image_list']);
        if(!empty($data['operator'])){
            $employee = EmployeeModel::getById($data['operator']);
        }
        $data['operator_name'] = isset($employee['name']) ? $employee['name'] : '-';
        return Valid::formatSuccess($data);
    }

    /**
     * 添加banner
     * @param $params
     * @param $employeeId
     * @return array|int|mixed|string|null
     */
    public static function add($params, $employeeId)
    {
        $res = self::checkParams($params);
        if($res['code'] != Valid::CODE_SUCCESS){
            return $res;
        }
        return self::addBanner($params, $employeeId);
    }

    /**
     * 添加数据
     * @param $params
     * @param $employeeId
     * @return array
     */
    public static function addBanner($params, $employeeId)
    {
        $params['operator'] = $employeeId;
        $params['create_time'] = time();
        $res = BannerModel::insertRecord($params);
        if(!$res){
            return Valid::addErrors([], 'add_banner_failed', 'add_banner_failed');
        }
        return Valid::formatSuccess();
    }

    /**
     * 验证 添加、编辑 Banner参数
     * @param $params
     * @return array
     */
    public static function checkParams($params)
    {
        //验证需要显示的图片是否为空
        if($params['show_main'] && empty($params['image_main'])){
            return Valid::addErrors([], 'image_main_is_required', 'image_main_is_required');
        }
        if($params['show_list'] && empty($params['image_list'])){
            return Valid::addErrors([], 'image_list_is_required', 'image_list_is_required');
        }
        //验证触发动作参数是否正确
        $actionDetailMap = DictService::getTypeMap(Constants::DICT_TYPE_ACTION_DETAIL);
        //判断触发动作类型是否正确
        if(!isset($actionDetailMap[$params['action_type']])){
            return Valid::addErrors([], 'action_type_error', 'action_type_error');
        }
        //当前触发动作类型不需要填写参数，直接返回
        if(empty($actionDetailMap[$params['action_type']])){
            return Valid::formatSuccess();
        }
        //触发动作类型要求有参数时，action_detail不能为空
        if(empty($params['action_detail'])){
            return Valid::addErrors([], 'action_detail_is_required', 'action_detail_is_required');
        }
        //解析参数
        $detailTemplate = json_decode($actionDetailMap[$params['action_type']], true);
        $detail = json_decode($params['action_detail'], true);
        //类型错误，直接返回错误
        if(!is_array($detailTemplate) || !is_array($detail)){
            return Valid::addErrors([], 'action_detail_template_error', 'action_detail_template_error');
        }
        //判断参数是否符合要求
        foreach($detailTemplate as $item){
            if(Util::emptyExceptZero($detail[$item])){
                return Valid::addErrors([], 'action_detail_error', 'action_detail_error');
            }
        }
        return Valid::formatSuccess();
    }

    /**
     * 编辑 banner
     * @param $params
     * @return array|int|mixed|string|null
     */
    public static function edit($params)
    {
        $res = self::checkParams($params);
        if($res['code'] != Valid::CODE_SUCCESS){
            return $res;
        }
        return self::editBanner($params);
    }

    /**
     * 更新数据
     * @param $params
     * @return array
     */
    public static function editBanner($params)
    {
        $id = $params['id'];
        unset($params['id']);
        $res = BannerModel::updateRecord($id, $params);
        if($res){
            return Valid::formatSuccess();
        }else{
            return Valid::addErrors([], 'update_date_failed', 'update_date_failed');
        }
    }

}