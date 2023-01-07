<?php

namespace App\Controllers\BaWx;
use App\Controllers\ControllerBase;
use App\Libs\AliOSS;
use App\Libs\Util;
use App\Libs\Valid;
use App\Models\ReceiptApplyModel;
use App\Services\AutoCheckPicture;
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
                'error_code' => 'pic_url_is_required'
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

            ReceiptApplyService::uploadApply($params, $baInfo['ba_id']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, []);
    }

    public function getPicInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'pic_url',
                'type' => 'required',
                'error_code' => 'pic_url_is_required'
            ]
        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            //图片识别结果，仅供系统审核建议,对于关联的商品信息不能确定时，要提供建议
            list($referReceiptFrom, $picOriginalReceiptNumber) = AutoCheckPicture::dealReceiptInfo($params['pic_url']);

        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, [
            'receipt_from' => $referReceiptFrom,
            'receipt_from_msg' => ReceiptApplyModel::RECEIPT_FROM[$referReceiptFrom],
            'receipt_number' => $picOriginalReceiptNumber ?: '未成功识别，请手动输入'
        ]);
    }

    public function receiptList(Request $request, Response $response)
    {

        try {
            $baInfo = $this->ci['ba_info'];
            $params = $request->getParams();
            list($page, $count) = Util::formatPageCount($params);
            list($totalCount, $receiptList) = ReceiptApplyService::getBAReceiptList($baInfo['ba_id'], $page, $count, $params);


        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, ['total_count' => $totalCount, 'receipt_list' => $receiptList]);
    }

    public function receiptInfo(Request $request, Response $response)
    {
        $rules = [
            [
                'key' => 'receipt_id',
                'type' => 'required',
                'error_code' => 'receipt_id_is_required'
            ]

        ];

        $params = $request->getParams();
        $result = Valid::appValidate($params, $rules);
        if ($result['code'] != Valid::CODE_SUCCESS) {
            return $response->withJson($result, StatusCode::HTTP_OK);
        }

        try {
            $baInfo = $this->ci['ba_info'];
            $receiptInfo = ReceiptApplyService::getBaReceiptInfo($params['receipt_id'], $baInfo['ba_id']);


        } catch (RunTimeException $e) {
            return HttpHelper::buildErrorResponse($response, $e->getAppErrorData());
        }

        return HttpHelper::buildResponse($response, ['receipt_info' => $receiptInfo]);
    }
}