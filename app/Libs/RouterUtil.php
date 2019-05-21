<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 11:58 AM
 */

namespace App\Libs;


class RouterUtil
{

    /*
     * 根据请求地址区分客户端类型
     * /student_app/...
     * /teacher_app/...
     * /student_wx/...
     * /teacher_wx/...
     * /student_org_wx/...
     * 其他情况为 org_web
     */
    const CLIENT_STUDENT_APP = 'student_app'; // AI练琴
    const CLIENT_TEACHER_APP = 'teacher_app'; // 智能琴房
    const CLIENT_STUDENT_WX = 'student_wx'; // 家长微信
    const CLIENT_TEACHER_WX = 'teacher_wx'; // 老师微信
    const CLIENT_STUDENT_ORG_WX = 'student_org_wx'; // 机构微信
    const CLIENT_ORG_WEB = 'org_web'; // 机构后台

    /**
     * 获取客户端类型
     * @return string
     */
    public static function getClientType()
    {
        $urlInfo = parse_url($_SERVER['REQUEST_URI']);
        $path = explode('/', $urlInfo['path']);
        $clientType = $path[1];
        if (!in_array($clientType, [
            self::CLIENT_STUDENT_APP,
            self::CLIENT_TEACHER_APP,
            self::CLIENT_STUDENT_WX,
            self::CLIENT_TEACHER_WX,
            self::CLIENT_STUDENT_ORG_WX,
            self::CLIENT_ORG_WEB
        ])) {
            $clientType = self::CLIENT_ORG_WEB;
        }
        return $clientType;
    }

    public static function loadByClientType($clientType)
    {

    }
}