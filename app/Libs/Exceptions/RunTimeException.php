<?php
/**
 * Created by PhpStorm.
 * User: newtype0092
 * Date: 2019/8/20
 * Time: 4:29 PM
 */

namespace App\Libs\Exceptions;


use App\Libs\SimpleLogger;
use I18N\Lang;

class RunTimeException extends \Exception
{
    private $errors;

    /**
     * 生成一个异常
     * 异常可能包含多个原因(error code)
     *
     * RunTimeException constructor.
     * @param array $errors [[errorCode, errorField, msgArgs], ...]
     *     errorCode 表示对应的错误,message 在lang文件中定义
     *     errorField(可选) 表示产生错误对应的前端字段
     *     msgArgs(可选) 格式化 errorMessage 需要的参数
     */
    public function __construct($errors)
    {
        parent::__construct("runtime_exception");
        if (is_string($errors[0])) { // [errorCode, errorField, msgArgs] 格式
            $errors = [$errors];
        }
        SimpleLogger::error("[runtime exception]", $errors);
        $this->errors = $errors;
    }

    public function getWebErrorData()
    {
        $errorData = [];
        foreach ($this->errors as $error) {
            $errorCode = $error[0];
            $errorField = $error[1] ?? $errorCode;
            $errorFormat = Lang::getWord($errorCode);
            if (empty($errorFormat)) {
                $errorMessage = $errorCode;
            } else {
                $msgArgs = $error[3] ?? [];
                $errorMessage = sprintf($errorFormat, ...$msgArgs);
            }
            $errorData[$errorField][] = [
                'err_no' => $errorCode,
                'err_msg' => $errorMessage,
            ];
        }
        return $errorData;
    }

    public function getAppErrorData()
    {
        $errorData = [];
        foreach ($this->errors as $error) {
            $errorCode = $error[0];
            $errorFormat = Lang::getWord($errorCode);
            if (empty($errorFormat)) {
                $errorMessage = $errorCode;
            } else {
                $msgArgs = $error[3] ?? [];
                $errorMessage = sprintf($errorFormat, ...$msgArgs);
            }
            $errorData[] = [
                'err_no' => $errorCode,
                'err_msg' => $errorMessage,
            ];
        }
        return $errorData;
    }

    public function sendCaptureMessage($data)
    {
        $sentryClient = new \Raven_Client($_ENV['SENTRY_NOTIFY_URL']);
        $sentryClient->captureMessage('RUNTIME EXCEPTION: ' . $this->getMessage(), null, $data);
    }
}