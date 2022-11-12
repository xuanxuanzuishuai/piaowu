<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/16
 * Time: 3:49 PM
 */

namespace App\Routers;



use App\Controllers\Privilege\Privilege;

class RouterFactory
{
    const CLIENT_ORG_WEB = 'org_web'; // 机构后台
    const CLIENT_EMPLOYEE = 'employee'; //后台
    const CLIENT_COMMON  = 'common'; //公共方法


    /**
     * client_type 对应的 Router class
     */
    const ROUTER_CLASSES = [
        self::CLIENT_ORG_WEB                => OrgWebRouter::class, // 机构后台
        self::CLIENT_EMPLOYEE               => EmployeeRouter::class, //后台调用
        self::CLIENT_COMMON                 => CommonRouter::class
    ];

    /**
     * 指定接口的 client_type
     * 特殊的接口不能按照 /client_type/... 格式命名时，在这里指定对应的 client_type
     */
    const URI_CLIENT_TYPES = [
        '/api/uictl/dropdown'    => self::CLIENT_ORG_WEB,
        '/api/oss/signature'     => self::CLIENT_ORG_WEB,
        '/api/oss/callback'      => self::CLIENT_ORG_WEB,
    ];

    /**
     * 获取客户端类型
     * @param string $uriPath
     * @return string
     */
    public static function getClientType($uriPath)
    {
        if (!empty(self::URI_CLIENT_TYPES[$uriPath])) {
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
        $router = new $class($uriPath);
        $router->load($uriPath, $app, $alias ?? null);
    }
}