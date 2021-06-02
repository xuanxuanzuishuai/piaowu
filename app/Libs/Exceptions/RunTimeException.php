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

    private $data; // 在一些情况下，客户端不仅需要获得错误提示，也需要知道是哪些数据出现了错误，成员变量data作为错误的扩展返回给客户端

    /**
     * 生成一个异常
     * 异常可能包含多个原因(error code)
     *
     * RunTimeException constructor.
     * @param array $errors [[errorCode, errorField, msgArgs], ...]
     *     errorCode 表示对应的错误,message 在lang文件中定义
     *     errorField(可选) 表示产生错误对应的前端字段
     *     msgArgs(可选) 格式化 errorMessage 需要的参数
     * @param $data
     */
    public function __construct($errors, $data = [])
    {
        parent::__construct("runtime_exception");
        if (is_string($errors[0])) { // [errorCode, errorField, msgArgs] 格式
            $errors = [$errors];
        }
        SimpleLogger::error("[runtime exception]", $errors);
        $this->errors = $errors;
        $this->data = $data;
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

    public function getData()
    {
        return $this->data;
    }
}