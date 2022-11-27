<?php
namespace App\Services;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Models\GoodsModel;
use App\Models\ReceiptApplyModel;

class AutoCheckPicture
{
    private static function getOcrContent($imagePath)
    {
        //调用ocr-识别图片
        $host    = "https://tysbgpu.market.alicloudapi.com";
        $path    = "/api/predict/ocr_general";
        $appcode = "af272f9db1a14eecb3d5c0fb1153051e";
        //根据API的要求，定义相对应的Content-Type
        $headers = [
            'Authorization' => 'APPCODE ' . $appcode,
            'Content-Type'  => 'application/json; charset=UTF-8'
        ];
        $bodys   = [
            'image'     => $imagePath,
            'configure' => [
                'min_size'                     => 1, #图片中文字的最小高度，单位像素
                'output_prob'                  => true,#是否输出文字框的概率
                'output_keypoints'             => false, #是否输出文字框角点
                'skip_detection'               => false,#是否跳过文字检测步骤直接进行文字识别
                'without_predicting_direction' => true#是否关闭文字行方向预测
            ]
        ];
        $url     = $host . $path;
        return HttpHelper::requestJson($url, $bodys, 'POST', $headers);
    }

    public static function dealReceiptInfo($imagePath)
    {
        $content = self::getOcrContent($imagePath);

        $receiptFrom = ReceiptApplyModel::SHOP_RECEIPT;

        $wordArr = array_column($content['ret'], 'word');

        $count = count($wordArr);


        $receiptNumber = '';
        $buyTime = '';
        $goodsInfo = [];

        $notFoundGoods = [];


        //云单比较固定，先确定什么单子进行不同得计算
        foreach($wordArr as $k => $value) {
            if (Util::sensitiveWordFilter(['编号'], $value)) {

                if (Util::sensitiveWordFilter(['复制'], $wordArr[$k+2])) {
                    $receiptNumber = '00' . $wordArr[$k+1];
                    $receiptFrom = ReceiptApplyModel::CLOUD_RECEIPT;
                    break;

                }
            }
        }


        if ($receiptFrom == ReceiptApplyModel::CLOUD_RECEIPT) {

            foreach($wordArr as $k => $value) {

                if (Util::sensitiveWordFilter(['下单', '日期'], $value)) {
                    $buyTime = $wordArr[$k+1];
                    if (strtotime($buyTime) == false) {
                        $newBuyTime = str_replace('：', ':', $buyTime);
                        $buyTime = substr($newBuyTime, 0, 10) . ' ' . substr($newBuyTime, 10);

                    }

                }


                if (Util::sensitiveWordFilter(['￥'], $value)) {
                    $goodsName = $wordArr[$k-1];

                    $goods = GoodsModel::getRecord(['goods_name[~]' => $goodsName]);

                    if (empty($goods)) {
                        $notFoundGoods[] = $goodsName;
                    }


                    $num =
                }












            }






        }


        die();

    }
}
