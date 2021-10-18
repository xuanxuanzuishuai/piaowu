<?php
/**
 * Created by PhpStorm.
 * User: llp
 * Date: 2021/1/21
 * Time: 10:52
 */

namespace App\Models;

use App\Libs\AliOSS;
use App\Libs\UserCenter;
use App\Libs\Util;
use App\Models\Dss\DssUserQrTicketModel;
use App\Services\AgentService;
use App\Services\PosterService;

class GoodsResourceModel extends Model
{
    public static $table = "goods_resource";

    const CONTENT_TYPE_IMAGE  = 1; // 图片
    const CONTENT_TYPE_TEXT   = 2; // 文字
    const CONTENT_TYPE_POSTER = 3; // 海报

    /**
     * @param $ext
     * @param array $agentInfo
     * @param array $params
     * @return array
     * @throws \App\Libs\Exceptions\RunTimeException
     * @throws \App\Libs\KeyErrorRC4Exception
     */
    public static function formatExt($ext, $agentInfo = [], $params = [])
    {
        if (!is_array($ext)) {
            $ext = json_decode($ext, true);
        }
        if (empty($ext)) {
            return [];
        }
        $data = [];
        $posterConfig = PosterService::getPosterConfig();
        foreach ($ext as $item) {
            if ($item['type'] == GoodsResourceModel::CONTENT_TYPE_IMAGE) {
                $data[$item['key']] = $item['value'];
                $data[$item['key'] . '_url'] = AliOSS::replaceCdnDomainForDss($item['value']);
            } elseif ($item['type'] == GoodsResourceModel::CONTENT_TYPE_TEXT) {
                $data[$item['key']] = Util::textDecode($item['value']);
            } elseif ($item['type'] == GoodsResourceModel::CONTENT_TYPE_POSTER) {
                $data[$item['key']] = $item['value'];
                $data[$item['key'] . '_url'] = AliOSS::replaceCdnDomainForDss($item['value']);
                if (!empty($agentInfo['id'])) {
                    $channel = AgentService::getAgentChannel($agentInfo['type'] ?? 0);
                    $extParams = [
                        'p' => PosterModel::getIdByPath($item['value']),
                        'app_id' => UserCenter::AUTH_APP_ID_OP_AGENT,
                        'lt' => $params['lt'] ?? DssUserQrTicketModel::LANDING_TYPE_MINIAPP,
                        'package_id' => $params['package_id'] ?? 0,
                        'text' => AgentService::agentWordWaterMark($agentInfo['id']),
                    ];
                    $posterUrl = PosterService::generateQRPosterAliOss(
                        $item['value'],
                        $posterConfig,
                        $agentInfo['id'],
                        UserWeiXinModel::USER_TYPE_AGENT,
                        $channel,
                        $extParams
                    );
                    $data[$item['key'] . '_agent_url'] = $posterUrl['poster_save_full_path'] ?? '';
                }
            }
        }
        return $data;
    }
}
