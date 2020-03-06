<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/28
 * Time: 3:14 PM
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Libs\UserCenter;
use App\Models\BannerModel;
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
            $item['image_list'] = $b['show_main'] ? AliOSS::signUrls($b['image_list']) : '';

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
}