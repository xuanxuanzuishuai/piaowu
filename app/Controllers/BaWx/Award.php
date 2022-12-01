<?php

namespace App\Controllers\BaWx;
use App\Controllers\ControllerBase;
use App\Libs\Util;
use App\Services\AwardService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\StatusCode;
use App\Libs\Valid;

class AWARD extends ControllerBase
{
    public function awardList(Request $request, Response $response)
    {
        try {
            $baInfo = $this->ci['ba_info'];
            $params = $request->getParams();
            list($page, $count) = Util::formatPageCount($params);
            list($totalCount, $list) = AwardService::getAwardList($baInfo['ba_id'], $page, $count);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'total_count' => $totalCount,
            'award_list' => $list
        ], StatusCode::HTTP_OK);

    }
}