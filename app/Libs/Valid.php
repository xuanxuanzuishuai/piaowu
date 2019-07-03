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
    const CODE_NOT_FOUNT = 4;

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
                'data' => array()
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
            if (empty($errorMsg)) {
                $errorMsg = $rule['error_code'];
            }

            $value = isset($rule['value']) ? $rule['value'] : null;

            $msg = [
                'err_no' => $rule['error_code'],
                'err_msg' => $errorMsg
            ];

            $v->rule($rule['type'], $rule['key'], $value)->message(json_encode($msg));
        }

        if ($v->validate()) {
            return array(
                'code' => 0,
                'data' => $v->data()
            );
        }

        $errors = $v->errors();
        foreach ($errors as $key => $err) {
            foreach ($err as $i => $emsg) {
                $emsg = json_decode($emsg, 1);
                $errors[$key][$i] = $emsg;
            }
        }

        return [
            'code' => 1,
            'data' => [
                'errors' => $errors
            ]
        ];
    }

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
            if (empty($errorMsg)) {
                $errorMsg = $rule['error_code'];
            }

            $value = isset($rule['value']) ? $rule['value'] : null;

            $msg = [
                'err_type' => $rule['key'],
                'err_no' => $rule['error_code'],
                'err_msg' => $errorMsg
            ];

            $v->rule($rule['type'], $rule['key'], $value)->message(json_encode($msg));
        }

        if ($v->validate()) {
            return [
                'code' => self::CODE_SUCCESS
            ];
        }

        $errors = $v->errors();
        $result = [];
        foreach ($errors as $key => $err) {
            foreach ($err as $i => $emsg) {
                $emsg = json_decode($emsg, 1);
                $result[] = $emsg;
            }
        }

        return [
            'code' => self::CODE_PARAMS_ERROR,
            'errors' => $result
        ];
    }

    /**
     * 添加错误信息
     *
     * @param array $result 原有错误信息
     * @param $key
     * @param $errorCode
     * @param array $args
     * @return array
     */
    public static function addErrors($result, $key, $errorCode, ...$args)
    {
        if (empty($result)) {
            $result = [
                'code' => self::CODE_PARAMS_ERROR,
                'data' => []
            ];
        } else if ($result['code'] === 0) {
            $result = [
                'code' => self::CODE_PARAMS_ERROR,
                'data' => []
            ];
        }
        if (empty($result['data']['errors'])) {
            $result['data']['errors'] = [];
        }
        if (empty($result['data']['errors'][$key])) {
            $result['data']['errors'][$key] = [];
        }

        array_push($result['data']['errors'][$key], [
            'err_no' => $errorCode,
            'err_msg' => sprintf(Lang::getWord($errorCode), ...$args) ?: $errorCode
        ]);

        return $result;
    }

    /**
     * 添加错误信息(APP端)
     * @param array $result 原有错误信息
     * @param $errorCode
     * @param array $args
     * @return array
     */
    public static function addAppErrors($result, $errorCode, ...$args)
    {
        if (empty($result) || $result['code'] === self::CODE_SUCCESS) {
            $result = [
                'code' => self::CODE_PARAMS_ERROR,
                'errors' => []
            ];
        }

        $msgTemp = Lang::getWord($errorCode);
        if (empty($msgTemp)) {
            $msg = $errorCode;
        } else {
            $msg = sprintf($msgTemp, ...$args);
        }

        array_push($result['errors'], [
            'err_no' => $errorCode,
            'err_msg' => $msg
        ]);

        return $result;
    }

    /**
     * 返回错误信息的HTML文档
     * @param $errors
     * @return string
     */
    public static function errorHTMLPage($errors)
    {
        $errorMSG = '';

        foreach ($errors['data']['errors'] as $k => $err) {
            $errorMSG .= '<p>' . $err[0]['err_msg'] . '</p>';
        }
        $html = <<<EOS
    <!DOCTYPE html>
      <html id="finance">
        <head>
          <meta charset="utf-8">
        </head>
        <body>
          {$errorMSG}
        </body>
      </html>
EOS;
        return $html;
    }
}