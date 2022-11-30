<?php

namespace App\Controllers\BaWx;
use App\Controllers\ControllerBase;
use App\Libs\Valid;
use App\Services\ReceiptApplyService;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Libs\HttpHelper;
use Slim\Http\StatusCode;
use App\Libs\Exceptions\RunTimeException;

class Receipt extends ControllerBase
{

    public function addReceipt(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'pic_url',
                'type' => 'required',
                'error_code' => 'mobile_is_required'
            ],
            [
                'key' => 'receipt_number',
                'type' => 'required',
                'error_code' => 'receipt_number_is_required'
            ]

        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $baInfo = $this->ci['ba_info'];
            $openId = $this->ci['open_id'];

            ReceiptApplyService::uploadApply($params, $baInfo['ba_id'], $openId);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, []);


    }
}