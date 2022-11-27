<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/5/5
 * Time: 12:53 PM
 */

namespace App\Libs;

use App\Models\DictModel;
use App\Services\DictService;

class DictConstants {

    // 阿里云config
    const ALI_OSS_CONFIG = [
        'type' => 'ALI_OSS_CONFIG',
        'keys' => [
            'access_key_id',
            'access_key_secret',
            'bucket',
            'endpoint',
            'callback_url',
            'expire',
            'max_file_size',
        ]
    ];

    // JWT的config
    const JWT_CONFIG = [
        'type' => 'jwt_config',
        'keys' => [
            'JWT_AUDIENCE',
            'JWT_EXPIRE',
            'JWT_ISSUER',
            'JWT_SIGNER_KEY',
            'TOKEN_TYPE_USER'
        ]
    ];

    //不同应用的微信app_id
    const WECHAT_APPID = [
        'type' => 'wechat_app_id',
        'keys' => [
            '1_1'
        ]
    ];
    //不同应用的微信app_secret
    const WECHAT_APP_SECRET = [
        'type' => 'wechat_app_secret',
        'keys' => [
            '1_1'
        ]
    ];

    /**
     * 单个获取op系统dict配置数据
     * @param $type
     * @param $key
     * @return array|mixed|null
     */
    public static function get($type, $key)
    {
        if (empty($type) || empty($key)) {
            return null;
        }

        if (is_array($key)) {
            return self::getValues($type, $key);
        }

        if (!in_array($key, $type['keys'])) {
            SimpleLogger::error(__FILE__ . __LINE__ . ' DictConstants::get [invalid key]', [
                'type' => $type,
                'key' => $key
            ]);
            return null;
        }

        return DictService::getKeyValue($type['type'], $key);
    }

    /**
     * 批量获取op系统dict配置数据
     * @param $type
     * @param $keys
     * @return array
     */
    public static function getValues($type, $keys)
    {
        if (empty($type)) {
            return [];
        }

        if (empty($keys)) {
            return [];
        }
        // 如果给的$keys中有不在$type['keys']里的直接返回空
        if (!empty(array_diff($keys, $type['keys']))) {
            return [];
        }

        return DictService::getKeyValuesByArray($type['type'], $keys);
    }

    /**
     * 获取op系统dict配置数据
     * @param $type
     * @return array
     */
    public static function getSet($type)
    {
        return DictService::getTypeMap($type['type']);
    }

    /**
     * 获取指定多个类型数据map
     * @param $types
     * @return array
     */
    public static function getTypesMap($types)
    {
        return DictService::getTypesMap($types);
    }

	/**
	 * 获取指定type类型下指定的key_code数据：返回数据格式为["code"=>1,"value"=>"11"]
	 * @param string $type
	 * @param array $keyCodes
	 * @return array
	 */
	public static function getTypeKeyCodes(string $type, array $keyCodes): array
	{
		$data = DictModel::getList($type);
		if (!empty($keyCodes)) {
			foreach ($data as $dk => $dv) {
				if (!in_array($dv["key_code"], $keyCodes)) {
					unset($data[$dk]);
				}
			}
		}
		return array_map(function ($item) {
			return [
				'code'  => $item['key_code'],
				'value' => $item['key_value']
			];
		}, $data);
	}
}
