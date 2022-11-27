<?php
namespace App\Services;
use App\Libs\HttpHelper;
use App\Libs\Util;
use App\Models\GoodsModel;
use App\Models\ReceiptApplyGoodsModel;
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

    /**
     * 图片识别
     * @param $imagePath
     * @return array
     */
    public static function dealReceiptInfo($imagePath)
    {
        $content = self::getOcrContent($imagePath);

        $receiptFrom = ReceiptApplyModel::SHOP_RECEIPT;

        $wordArr = array_column($content['ret'], 'word');

        $count = count($wordArr);


        $receiptNumber = '';
        $buyTime = '';
        $goodsInfo = [];
        $initGoods = [];
        $nums = [];

        $fenge = $count;

        $remark = '';


        //云单比较固定，先确定什么单子进行不同得计算
        foreach($wordArr as $k => $value) {
            $value = Util::trimAllSpace($value);
            if (Util::sensitiveWordFilter(['编号'], $value)) {

                if (Util::sensitiveWordFilter(['复制'], $wordArr[$k+2])) {
                    $receiptNumber = '00' . $wordArr[$k+1];
                    $receiptFrom = ReceiptApplyModel::CLOUD_RECEIPT;
                    break;

                }
            }
        }


        if ($receiptFrom == ReceiptApplyModel::CLOUD_RECEIPT) {

            //处理云单
            foreach($wordArr as $k => $value) {
                $value = Util::trimAllSpace($value);

                if (Util::sensitiveWordFilter(['下单', '日期'], $value)) {
                    $buyTime = $wordArr[$k+1];
                    if (strtotime($buyTime) == false) {
                        $newBuyTime = str_replace('：', ':', $buyTime);
                        $buyTime = substr($newBuyTime, 0, 10) . ' ' . substr($newBuyTime, 10);

                    }

                }

                if (Util::sensitiveWordFilter(['价格'], $value)) {
                    $fenge = $k;

                }


                if (Util::sensitiveWordFilter(['￥'], $value)) {

                    if ($k < $fenge) {
                        $goodsName = $wordArr[$k-1];

                        $initGoods[] = $goodsName;
                    }

                }

                if (Util::sensitiveWordFilter(['X'], $value)) {
                    $num = str_replace('X', NULL, $value);
                    $nums[] = $num;

                }

            }


            if (!empty($initGoods)) {

                if (count($initGoods) != count($nums)) {
                    $remark .= '识别出来的商品个数和数量个数不匹配' . PHP_EOL;
                }


                foreach ($initGoods as $key => $v) {
                    $goods = GoodsModel::getRecord(['goods_name[~]' => $v]);

                    if (empty($goods)) {
                        $remark .= '未找到此商品信息-' . $v;
                    }

                    $goodsInfo[] = [
                        'id' => $goods['id'],
                        'num' => $nums[$key],
                        'status' => ReceiptApplyGoodsModel::STATUS_NORMAL
                    ];

                }
            }

        }

        return [$receiptFrom, $receiptNumber, $buyTime, $goodsInfo, $remark];

    }
}
