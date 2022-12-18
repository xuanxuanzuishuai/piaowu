<?php

namespace App\Controllers\Employee;

use App\Controllers\ControllerBase;
use App\Libs\Exceptions\RunTimeException;
use App\Libs\HttpHelper;
use App\Libs\Valid;
use App\Models\AreaRegionModel;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Http\StatusCode;


class Region extends ControllerBase
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     */
    public function regionList(Request $request, Response $response, $args)
    {
        $params = $request->getParams();
        try {
            $info = AreaRegionModel::getRecords(['id[>]' => 0]);
        } catch (RuntimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return $response->withJson([
            'code' => Valid::CODE_SUCCESS,
            'region_list' => $info,
        ], StatusCode::HTTP_OK);
    }
}