<?php
/**
 * Created by PhpStorm.
 * User:
 * Date: 2020/6/22
 * Time: 下午5:35
 */

namespace App\Services;


interface VoiceCallIf
{

    public function createTask($params);
    public function execTask($params);
    public function saveExecResult($taskId,$data);
    public function saveCallbackResult();

}