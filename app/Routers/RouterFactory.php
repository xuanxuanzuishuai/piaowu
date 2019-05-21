<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:49 PM
 */

namespace App\Routers;


class RouterFactory
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
    const CLIENT_STUDENT_ORG_WX = 'student_org_wx'; // 机构微信
    const CLIENT_TEACHER_WX = 'teacher_wx'; // 老师微信
    const CLIENT_ORG_WEB = 'org_web'; // 机构后台

    const ROUTER_CLASSES = [
        self::CLIENT_STUDENT_APP => StudentAppRouter::class,
        self::CLIENT_TEACHER_APP => TeacherAppRouter::class,
        self::CLIENT_STUDENT_WX => StudentWXRouter::class,
        self::CLIENT_STUDENT_ORG_WX => StudentWXRouter::class,
        self::CLIENT_TEACHER_WX => TeacherWXRouter::class,
        self::CLIENT_ORG_WEB => OrgWebRouter::class,
    ];

    const URI_CLIENT_TYPES = [
        '/user/auth/get_user_id' => self::CLIENT_STUDENT_APP
    ];

    /**
     * 获取客户端类型
     * @param string $uriPath
     * @return string
     */
    public static function getClientType($uriPath)
    {
        if (in_array($uriPath, self::URI_CLIENT_TYPES)) {
            return self::URI_CLIENT_TYPES[$uriPath];
        }

        $path = explode('/', $uriPath);
        $clientType = $path[1];
        $allTypes = array_keys(self::ROUTER_CLASSES);

        if (!in_array($clientType, $allTypes)) {
            $clientType = self::CLIENT_ORG_WEB;
        }
        return $clientType;
    }

    /**
     * 根据uri加载router
     * @param $uri
     * @param $app
     */
    public static function loadRouter($uri, $app)
    {
        $uriInfo = parse_url($uri);
        $uriPath = $uriInfo['path'];

        $clientType = self::getClientType($uriPath);

        /** @var RouterBase $router */
        $class = self::ROUTER_CLASSES[$clientType];
        $router = new $class();
        $router->load($uriPath, $app);
    }
}