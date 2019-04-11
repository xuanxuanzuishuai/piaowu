<?php
/**
 * 参数验证
 * @author tianye@xiaoyezi.com
 * @since 2016-04-01 16:56:44
 */

namespace App\Libs;

use I18N\Lang;
use Valitron\Validator;

class APIValid
{
    const CODE_SUCCESS = 0;
    const CODE_PARAMS_ERROR = 1;
    const CODE_EXCEPTION = 2;
    const CODE_UNAUTHOR = 3;

    /**
     * parameters validate
     *
     * @param array $params
     * @param array $rules inspection rules
     * @return array
     */
    public static function validate($params, $rules)
    {
        if (empty($rules)) {
            return array(
                'code' => 0,
                'data' => new \ArrayObject(),
                'errors' => array()
            );
        }
        $v = new Validator($params);

        foreach ($rules as $key => $rule) {
            if (empty($rule['key'])) {
                throw new \InvalidArgumentException('each rule must be have the key attribute');
            }

            if (empty($rule['type'])) {
                throw new \InvalidArgumentException('each rule must be have the type attribute');
            }

            $errorMsg = isset($rule['error_code']) ? Lang::getWord($rule['error_code']) : '';

            $value = isset($rule['value']) ? $rule['value'] : null;

            $msg = [
                'err_no' => $rule['error_code'],
                'err_msg' => $errorMsg
            ];

            if(stripos($rule['type'],'between') !== false){
                if (!is_array($value) || count($value) != 2){
                    throw new \InvalidArgumentException('value must be array and length is 2');
                }else{
                    $v->rule($rule['type'], $rule['key'], $value[0],$value[1])->message(json_encode($msg));
                }
            }else{
                $v->rule($rule['type'], $rule['key'], $value)->message(json_encode($msg));
            }
        }
        if ($v->validate()) {
            return array(
                'code' => 0,
                'data' => $v->data()
            );
        }
        $errors = $v->errors();
        $res = [];
        foreach ($errors as $key => $err) {
            foreach ($err as $i => $emsg) {
                $emsg = json_decode($emsg, 1);
                $res[] = $emsg;
            }
        }
        return [
            'code' => 1,
            'data' => new \ArrayObject(),
            'errors' => $res
        ];
    }


    /**
     * 添加错误信息
     *
     * @param array $result 原有错误信息
     * @param $errorCode
     * @return array
     */
    public static function addErrors($result, $errorCode, ...$args)
    {
        if (empty($result)) {
            $result = [
                'code' => self::CODE_PARAMS_ERROR,
                'data' => new \ArrayObject()
            ];
        } else if ($result['code'] === 0) {
            $result = [
                'code' => self::CODE_PARAMS_ERROR,
                'data' => new \ArrayObject()
            ];
        }
        if (empty($result['errors'])) {
            $result['errors'] = [];
        }

        array_push($result['errors'], [
            'err_no' => $errorCode,
            'err_msg' => sprintf(Lang::getWord($errorCode), ...$args)
        ]);

        return $result;
    }

    public static function fromValidError($vr)
    {
        $result['code'] = $vr['code'];
        $result['errors'] = array_map(function ($error) {return $error[0];}, array_values($vr['data']['errors']));
        $result['data'] = $vr['data'];
        unset($result['data']['errors']);
        return $result;
    }

    public static function err($order,$errorCode, ...$args){
        return [
            'code'   => self::CODE_EXCEPTION,
            'data'   => [],
            'errors' => [
                'err_no'  => $order,
                'err_msg' => sprintf(Lang::getWord($errorCode), ...$args)
            ],
        ];
    }
}