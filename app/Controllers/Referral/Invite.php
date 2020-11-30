<?php
/**
 * Created by PhpStorm.
 * User: lizao
 * Date: 2020/11/23
 * Time: 6:33 PM
 */

namespace App\Controllers\Referral;

use App\Controllers\ControllerBase;
use App\Libs\HttpHelper;
use App\Services\ReferralService;
use App\Libs\Exceptions\RunTimeException;
use Slim\Http\Request;
use Slim\Http\Response;

class Invite extends ControllerBase
{
    /**
     * 列表
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function list(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();
            $params['page'] = $params['page'] ?? 1;
            $params['count'] = $params['count'] ?? $_ENV['PAGE_RESULT_COUNT'];
            list($records, $totalCount) = ReferralService::getReferralList($params);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getWebErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'records' => $records,
            'total_count' => $totalCount
        ]);
    }

}