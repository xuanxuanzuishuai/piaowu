<?php
/**
 * 参数验证
 * @author tianye@xiaoyezi.com
 * @since 2016-04-01 16:56:44
 */

namespace App\Libs;

use I18N\Lang;
use Valitron\Validator;

class Valid
{
    const CODE_SUCCESS = 0;
    const CODE_PARAMS_ERROR = 1;
    const CODE_EXCEPTION = 2;
    const CODE_UNAUTHOR = 3;
    const CODE_CONFIRM = 9;
    const CODE_NOT_FOUNT = 404;


    /**
     * parameters validate
     *
     * @param array $params
     * @param array $rules inspection rules
     * @return array
     */
    public static function appValidate($params, $rules)
    {
        if (empty($rules)) {
            return [];
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

            $v->rule($rule['type'], $rule['key'], $value)->message(json_encode($msg));
        }

        if ($v->validate()) {
            return [];
        }

        $errors = $v->errors();
        $result = [];
        foreach ($errors as $key => $err) {
            foreach ($err as $i => $emsg) {
                $emsg = json_decode($emsg, 1);
                $result[] = $emsg;
            }
        }

        return $result;
    }

    /**
     * 添加错误信息
     *
     * @param array $result 原有错误信息
     * @param $errNo
     * @param $errorCode
     * @param array $args
     * @return array
     */
    public static function addErrors($result, $errNo, $errorCode, $args = array())
    {
        if (empty($result['errors'])) {
            $result['errors'] = [];
        }
        array_push($result['errors'], [
            'err_no' => $errorCode,
            'err_msg' => sprintf(Lang::getWord($errorCode), $args)
        ]);

        return ['data' => [], 'err_no' => $errNo, 'err_msg' => $result['errors']];
    }
}