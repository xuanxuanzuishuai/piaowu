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
    /**
     * client_type
     * 根据请求地址区分客户端类型
     * /student_app/...
     * /teacher_app/...
     * /student_wx/...
     * /teacher_wx/...
     * /student_org_wx/...
     * /admin/...
     * 其他情况为 org_web
     */
    const CLIENT_STUDENT_APP = 'student_app'; // AI练琴
    const CLIENT_ORG_WEB = 'org_web'; // 机构后台
    const CLIENT_API = 'api'; // 外部api调用
    const CLIENT_SALES_MASTER = 'sales_master'; // 鲸鱼跳跃
    const CLIENT_STUDENT_WX = 'student_wx'; // 家长微信
    const CLIENT_STUDENT_WEB = 'student_web'; // 学生WEB
    const CLIENT_DSS = 'dss';
    const CLIENT_AGENT_MINI = 'agent';
    const CLIENT_SHOW_MINI = 'show_miniapp';
    const CLIENT_ERP_API = 'erp';
    const CLIENT_CRM_API = 'crm';
    const CLIENT_REFERRAL_MINI_APP = 'referral_miniapp'; // 体验营小程序
    const CLIENT_AI_PLAY_MINI_APP = 'ai_play_miniapp'; // 上音社合作-小叶子AI智能陪练小程序
    const CLIENT_REAL_REFERRAL_MINI_APP = 'real_referral_miniapp'; // 真人转介绍小程序
    const CLIENT_REAL_STUDENT_WX = 'real_student_wx'; // 真人业务线学生微信端
    const CLIENT_REAL_STUDENT_APP = 'real_student_app'; // 真人业务线学生app端
    const CLIENT_REAL_STUDENT_WEB = 'real_student_web'; // 真人业务线学生web端
    const CLIENT_REAL_STUDENT_COMMON = 'real_student_common'; // 真人业务线学生公共接口路由
    const CLIENT_REF_API = 'ref'; // 真人业务线学生app端


    /**
     * client_type 对应的 Router class
     */
    const ROUTER_CLASSES = [
        self::CLIENT_STUDENT_APP => StudentAppRouter::class, // AI练琴APP
        self::CLIENT_ORG_WEB => OrgWebRouter::class, // 机构后台
        self::CLIENT_API => APIRouter::class, // 外部api调用
        self::CLIENT_STUDENT_WX => StudentWXRouter::class, // 家长微信
        self::CLIENT_DSS => DSSRouter::class, // 家长微信
//        self::CLIENT_AGENT_MINI => AgentMiniAppRouter::class, // 代理小程序禁止使用2022-1-8
        self::CLIENT_SHOW_MINI => ShowMiniAppRouter::class, // 测评分享小程序
        self::CLIENT_STUDENT_WEB => StudentWebRouter::class, // WEB端
        self::CLIENT_ERP_API => ErpRouter::class, // ERP
        self::CLIENT_CRM_API => CrmRouter::class, // CRM
        self::CLIENT_REFERRAL_MINI_APP => ReferralMinAppRouter::class, // 体验营小程序
        self::CLIENT_AI_PLAY_MINI_APP => AIPlayMiniAppRouter::class, // 上音社合作-小叶子AI智能陪练小程序
        self::CLIENT_REAL_REFERRAL_MINI_APP => RealReferralMinAppRouter::class, // 真人转介绍小程序
        self::CLIENT_REAL_STUDENT_WX => RealStudentWXRouter::class, // 真人业务线学生微信端
        self::CLIENT_REAL_STUDENT_APP => RealStudentAppRouter::class, // 真人业务线学生app端
        self::CLIENT_REAL_STUDENT_WEB => RealStudentWebRouter::class, // 真人业务线学生web端
        self::CLIENT_REAL_STUDENT_COMMON => RealStudentCommonRouter::class, // 真人业务线学生公共接口路由
        self::CLIENT_REF_API => REFRoute::class, // referral_backend

    ];

    /**
     * 指定接口的 client_type
     * 特殊的接口不能按照 /client_type/... 格式命名时，在这里指定对应的 client_type
     */
    const URI_CLIENT_TYPES = [
        '/user/auth/get_user_id' => self::CLIENT_STUDENT_APP,
        '/api/qiniu/token' => self::CLIENT_ORG_WEB,
        '/api/qiniu/callback' => self::CLIENT_ORG_WEB,
        '/api/uictl/dropdown' => self::CLIENT_ORG_WEB,
        '/api/oss/signature' => self::CLIENT_ORG_WEB,
        '/api/oss/callback' => self::CLIENT_ORG_WEB,
        '/sm/customer/upload' => self::CLIENT_SALES_MASTER,
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

        // 处理 consumer api 的 env 前缀
        if ($clientType == self::CLIENT_API && substr($uriPath, 0, 14) == '/api/consumer/') {
            $alias = preg_replace('/dev_|test_|pre_|prod_/i', '', $uriPath);
        }

        /** @var RouterBase $router */
        $class = self::ROUTER_CLASSES[$clientType];
        $router = new $class();
        $router->load($uriPath, $app, $alias ?? null);
    }
}