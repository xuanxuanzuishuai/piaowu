<?php
/**
 * Created by PhpStorm.
 * User: xyz
 * Date: 2019/11/1
 * Time: 下午3:15
 */

namespace App\Controllers\ExamMinApp;

use App\Libs\AliOSS;
use App\Libs\DictConstants;
use App\Libs\HttpHelper;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Controllers\ControllerBase;
use App\Services\StudentForMinAppService;

//音基小程序的配置类
//提供广告轮播图，用户答多少道题后弹窗，等参数
class Config extends ControllerBase
{
    public function config(Request $request, Response $response)
    {
        $banners = DictConstants::get(DictConstants::EXAM_BANNER, DictConstants::EXAM_BANNER['keys']);
        foreach($banners as $k => $b) {
            $banners[$k] = AliOSS::signUrls($b);
        }

        $pop = DictConstants::get(DictConstants::EXAM_POP, DictConstants::EXAM_POP['keys']);

        $hasMobile = StudentForMinAppService::hasMobile($this->ci['exam_openid']);

        return HttpHelper::buildResponse($response, [
            'banner' => $banners,
            'pop' => $pop[0],
            'has_mobile' => $hasMobile
        ]);
    }
}