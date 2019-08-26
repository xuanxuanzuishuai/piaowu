<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/26
 * Time: 1:41 PM
 */

namespace App\Controllers\StudentApp;


use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ReferralModel;
use App\Services\ReferralService;
use App\Services\StudentServiceForWeb;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;

class Referral extends ControllerBase
{
    public function list(Request $request, Response $response)
    {
        Util::unusedParam($request);

        $data = ReferralService::ReferralList($this->ci['student']['id']);

        return HttpHelper::buildResponse($response, $data);
    }
}