<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2020/2/28
 * Time: 3:14 PM
 */

namespace App\Services;


use App\Libs\AliOSS;
use App\Models\BannerModel;
use App\Models\ReviewCourseModel;
use App\Models\StudentModel;

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
            if (!empty($b['filter']) && !self::userFilter($b['filter'], $studentId)) {
                continue;
            }
            $bannerList[] = [
                'id' => $b['id'],
                'image_main' => empty($b['image_main']) ? '' : AliOSS::signUrls($b['image_main']),
                'image_list' => empty($b['image_main']) ? '' : AliOSS::signUrls($b['image_list']),
                'action_type' => $b['action_type'],
                'action' => self::prepareAction($b['action_type'], json_decode($b['action_detail'], true)),
            ];
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
        /*
        $student = StudentModel::getById($studentId);

        if ($student['has_review_course'] == ReviewCourseModel::REVIEW_COURSE_NO) {
            return false;
        }

        $appSubStatus = StudentServiceForApp::checkSubStatus($student['sub_status'], $student['sub_end_date']);
        if (!$appSubStatus) {
            return false;
        }

        $subWx = false;
        if ($subWx) {
            return false;
        }
        */

        return true;
    }
}