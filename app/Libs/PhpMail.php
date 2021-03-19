<?php


namespace App\Libs;


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

class PhpMail
{
    /**
     * 邮件发送
     * @param $to
     * @param string $subject
     * @param string $content
     * @return bool
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public static function sendEmail($to, $subject = '', $content = '', $attachment = '')
    {
        $mail = new PHPMailer(true);    // Passing `true` enables exceptions
        try {
            list($fromMail, $fromMailPasd, $fromName, $smtpServer, $smtpPort) = DictConstants::get(DictConstants::SEND_MAIL_CONFIG, [
                'from_mail',
                'from_mail_pasd',
                'from_name',
                'smtp_server',
                'smtp_port'
            ]);
            //服务器配置
            $mail->CharSet = "UTF-8";            //设定邮件编码
            $mail->SMTPDebug = SMTP::DEBUG_OFF; // 调试模式输出
            $mail->isSMTP();                    // 使用SMTP
            $mail->Host = $smtpServer;          // SMTP服务器
            $mail->SMTPAuth = true;             // 允许 SMTP 认证
            $mail->Username = $fromMail;        // SMTP 用户名  即邮箱的用户名
            $mail->Password = $fromMailPasd;    // SMTP 密码  部分邮箱是授权码(例如163邮箱)
            $mail->Port = $smtpPort;            // 服务器端口 25 或者465 具体要看邮箱服务器支持
            if ($mail->Port == 465) {
                $mail->SMTPSecure = 'ssl';      // 使用安全协议
            }

            $mail->setFrom($fromMail, $fromName);  //发件人
            // 收件人
            if (is_array($to)) {
                foreach ($to as $v) {
                    $mail->addAddress($v);
                }
            } else {
                $mail->addAddress($to);
            }
            // $mail->addReplyTo('xxxx@163.com', 'info'); //回复的时候回复给哪个邮箱 建议和发件人一致
            //$mail->addCC('cc@example.com');                    //抄送
            //$mail->addBCC('bcc@example.com');                    //密送

            //发送附件
            if (!empty($attachment) && file_exists($attachment)) {
                $mail->addAttachment($attachment);         // 添加附件
                // $mail->addAttachment('../thumb-1.jpg', 'new.jpg');    // 发送附件并且重命名
            }

            //Content
            $mail->isHTML(true);        // 是否以HTML文档格式发送  发送后客户端可直接显示对应HTML内容
            $mail->Subject = $subject;
            $mail->Body = $content;
            $mail->AltBody = $content;
            return $mail->send();
        } catch (\Exception $e) {
            SimpleLogger::error('phpMail::sendEmail error',['error' => $mail->ErrorInfo]);
            return false;
        }
    }
}