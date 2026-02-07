<?php
namespace APP;

use \libphonenumber\PhoneNumberUtil;
use \APP\CsrfValidator;
use \Genkgo\Mail\FormattedMessageFactory;
use \Genkgo\Mail\AlternativeText;
use \Genkgo\Mail\Header\From;
use \Genkgo\Mail\Header\To;
use \Genkgo\Mail\Header\Subject;
use \Genkgo\Mail\Transport\SmtpTransport;
use \Genkgo\Mail\Protocol\Smtp\ClientFactory;
use \Genkgo\Mail\Transport\EnvelopeFactory;
use \Twig\Loader\FilesystemLoader;
use \Twig\Environment;
use \Detection\MobileDetect;

/**
 * @SuppressWarnings(PHPMD.Superglobals)
 */
class Form
{
    private $var = [];
    public function __call($name, $args)
    {
        if (strncmp($name, 'get', 3) === 0) {
            return $this->get(substr($name, 3), reset($args));
        }
        if (strncmp($name, 'set', 3) === 0) {
            return $this->set(substr($name, 3), reset($args));
        }
        throw new \BadMethodCallException('Method "'.$name.'" dose not exists.');
    }

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    public function get($key, $default = null)
    {
        if (array_key_exists($key, $this->var)) {
            return $this->var[$key];
        }
        return $default;
    }

    public function set($key, $value)
    {
        $this->var[$key] = $value;
    }

    public function escape($text, $double = true, $charset = 'UTF-8')
    {
        $type = gettype($text);
        switch ($type) {
            case "array":
                $texts = [];
                foreach ($text as $key => $value) {
                    $texts[$key] = $this->escape($value, $double, $charset);
                }
                return $texts;
                break;
            case "object":
                if (method_exists($text, '__toString')) {
                    $text = (string) $text;
                }
                if (!method_exists($text, '__toString')) {
                    $text = '(object)' . get_class($text);
                }
                break;
            case "bool":
                return $text;
                break;
        }
        return htmlspecialchars($text, ENT_QUOTES, $charset, $double);
    }
    
    public function escapeAdmin($text, $double = true, $charset = 'UTF-8')
    {
        $type = gettype($text);
        switch ($type) {
            case "array":
                $texts = [];
                foreach ($text as $key => $value) {
                    $texts[$key] = $this->escape($value, $double, $charset);
                }
                return $texts;
                break;
            case "object":
                if (method_exists($text, '__toString')) {
                    $text = (string) $text;
                }
                if (!method_exists($text, '__toString')) {
                    $text = '(object)' . get_class($text);
                }
                break;
            case "bool":
                return $text;
                break;
        }
        return htmlspecialchars(str_replace(["　"], "", $text), ENT_QUOTES, $charset, $double);
    }

    public function isComplete($messages)
    {
        if (!empty($_POST) &&
            empty($messages) &&
            isset($_POST["send"]) &&
            $_POST["send"] == "true"
        ) {
            return true;
        }
        return false;
    }

    public function currentValue($value)
    {
        if (isset($_POST[$value])) {
            if (!empty($_POST[$value])) {
                return $this->escape($_POST[$value]);
            }
        }
    }

    public function currentTextArea($value)
    {
        if (isset($_POST[$value])) {
            if (!empty($_POST[$value])) {
                return $this->escape($_POST[$value]);
            }
        }
    }

    public function validate()
    {
    //echo var_dump('aaaa');
        $formRules = $this->getForms();
        $this->setMessages([]);
        foreach ($formRules as $key => $value) {
            $this->validateCsrf();
            // requireのバリデーションメッセージ
            if ($value['require']) {
                $this->validateRequire($_POST[$key], $key, $value["name"], $value["type"]);
            }
            // 最小文字列のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("min", $value["rule"])) {
                $this->validateMin($_POST[$key], $key, $value["name"], $value["rule"]);
            }
            // 最大文字列のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("max", $value["rule"])) {
                $this->validateMax($_POST[$key], $key, $value["name"], $value["rule"]);
            }
            // 指定文字数のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("size", $value["rule"])) {
                $this->validateSize($_POST[$key], $key, $value["name"], $value["rule"]);
            }
            // 数字のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("number", $value["rule"])) {
                $this->validateInteger($_POST[$key], $key, $value["name"]);
            }
            // 文字列数字のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("numberText", $value["rule"])) {
                $this->validateIntegerText($_POST[$key], $key, $value["name"]);
            }
            // メールのバリデーション
            if (!empty($_POST[$key]) && array_key_exists("mail", $value["rule"])) {
                $this->validateMail($_POST[$key], $key, $value["name"]);
            }
            // confirmのバリデーション
            if (!empty($_POST[$key]) && array_key_exists("sameWith", $value["rule"])) {
                $this->validateConfirm(
                    $_POST[$key],
                    $_POST[$value["rule"]["sameWith"]],
                    $key,
                    $value["name"],
                    $formRules[$value["rule"]["sameWith"]]
                );
            }
            // 正規表現のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("regex", $value["rule"])) {
                $this->validateRegex($_POST[$key], $key, $value["rule"]);
            }
            // 日付のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("date", $value["rule"])) {
                $this->validateDate($_POST[$key], $key, $value["name"]);
            }
            // 選択肢のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("enum", $value["rule"])) {
                $this->validateEnum($_POST[$key], $key, $value["rule"]["enum"]);
            }
            // 電話番号のバリデーション
            if (!empty($_POST[$key]) && array_key_exists("phone", $value["rule"])) {
                $this->validatePhone($_POST[$key], $key);
            }
        }
    }

    private function validateRequire($value, $item, $name, $type)
    {
        if (empty($value) || $value == 'NULL') {
            if ($type === 'text') {
                $messages = $this->getMessages();
                $messages = $this->getMessages();
                $messages = array_merge_recursive($messages, array($item => "この項目は必須入力です"));
                $this->setMessages($messages);
            }
            if ($type === 'checkbox') {
                if ($item === 'agree') {
                    $messages = $this->getMessages();
                    $messages = $this->getMessages();
                    $messages = array_merge_recursive($messages, array($item => "チェックを入れてください"));
                    $this->setMessages($messages);
                }else{
                    $messages = $this->getMessages();
                    $messages = $this->getMessages();
                    $messages = array_merge_recursive($messages, array($item => "選択してください"));
                    $this->setMessages($messages);
                }
            }
            if ($type === 'radio') {
                $messages = $this->getMessages();
                $messages = $this->getMessages();
                $messages = array_merge_recursive($messages, array($item => "選択してください"));
                $this->setMessages($messages);
            }
            return false;
        }
        return true;
    }

    private function validateMin($value, $item, $name, $rule)
    {
        if (mb_strlen($value, "UTF-8") < $rule["min"]) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => "{$rule["min"]}文字以上で入力してください"));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    private function validateMax($value, $item, $name, $rule)
    {
        if (mb_strlen($value, "UTF-8") > $rule["max"]) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive(
                $messages,
                array(
                    $item => "{$rule["max"]}文字以内で入力してください"
                )
            );
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    private function validateSize($value, $item, $name, $rule)
    {
        if (mb_strlen($value, "UTF-8") !== $rule["size"]) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => "{$rule["size"]}文字で入力してください"));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    private function validateInteger($value, $item, $name)
    {
        if (!ctype_digit($value)) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => "数字で入力してください"));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    // 0から始まるような文字列数字の場合に使用。
    private function validateIntegerText($value, $item, $name)
    {
        if (!preg_match('/^\d+$/', $value)) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive(
                $messages,
                array(
                    $item => "数字のみで入力してください"
                )
            );
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    private function validateMail($value, $item, $name)
    {
        if (!$this->validateMailPhp7($value)) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => "正しく入力してください".$this->validateMailPhp7($value)));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    /**
     * PHP7.1以降のメールバリデーション(国際化メールアドレス対応済み)
     * @param  [type] $value [description]
     * @param  bool $dns true | false trueにすると武@ドメイン.comみたいな国際化メールアドレスもバリデーションできる。
     * @return bool true | false
    */
    private function validateMailPhp7($value, $dns = null)
    {
        $dns = $dns !== null ? $dns : false ;
        switch (true) {
            case false === filter_var($value, FILTER_VALIDATE_EMAIL, FILTER_FLAG_EMAIL_UNICODE):
            case !preg_match('/@([^@\[]++)\z/', $value, $match):
                return false;
            case !$dns:
            case checkdnsrr($match[1], 'MX'):
            case checkdnsrr($match[1], 'A'):
            case checkdnsrr($match[1], 'AAAA'):
                return true;
            default:
                return false;
        }
    }

    private function validateConfirm($value, $value2, $item, $name, $target)
    {
        if ($value !== $value2) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => "「{$name}」は{$target['name']}と同じ内容を入力してください"));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    private function validateRegex($value, $item, $rule)
    {
        if (!preg_match($rule['regex']['pattern'], $value)) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => $rule["regex"]["message"]));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    private function validateDate($value, $item, $name)
    {
        $year = $value;
        $month = $this->escape($_POST["month"]);
        $day = $this->escape($_POST["day"]);
        if (!checkdate($month, $day, $year) === true) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => "「{$name}」が正しい日付ではありません。お手数ですが再度選択してください"));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }
    /**
    * @SuppressWarnings(PHPMD.StaticAccess)
    */
    private function validatePhone($value, $item)
    {
        $phoneUtil = PhoneNumberUtil::getInstance();
        $value = preg_replace('/-/', '', $value);
        try {
            $phoneNumberObject = $phoneUtil->parse($value, "JP");
            if (!$phoneUtil->isValidNumber($phoneNumberObject)) {
                $messages = $this->getMessages();
                $messages = array_merge_recursive($messages, array($item => "正しい電話番号ではありません"));
                $this->setMessages($messages);
                return false;
            }
        } catch (\libphonenumber\NumberParseException $e) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => "正しい電話番号ではありません"));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    /**
    * @SuppressWarnings(PHPMD.ExitExpression)
    */
    private function validateCsrf()
    {
        $detect = new MobileDetect;
        if ($detect->isMobile() || $detect->isTablet()) {
            return true;
        }
        if (!CsrfValidator::validate(filter_input(INPUT_POST, 'token'), true)) {
            header('Content-Type: text/plain; charset=UTF-8', true, 400);
            die('CSRF validation failed.');
        }
    }

    private function validateEnum($value, $item, $rule)
    {
        if (array_search($value, $rule) === false) {
            $messages = $this->getMessages();
            $messages = array_merge_recursive($messages, array($item => "選択肢してください"));
            $this->setMessages($messages);
            return false;
        }
        return true;
    }

    public function sendReturnMail()
    {
        $factory = new FormattedMessageFactory();
        $tmp = $this->createMessage();
        $factory = $factory->withHtmlAndNoGeneratedAlternativeText($tmp);
        $factory = $factory->withAlternativeText(new AlternativeText(strip_tags($tmp)));
        $message = $factory->createMessage();
        $subject = mb_encode_mimeheader($this->getEmailSubject());
        $subject = preg_replace('/\v/', "", $subject);
        $name = mb_encode_mimeheader(
            preg_replace('/\v/', "", $this->escape($_POST["sei"]).$this->escape($_POST["mei"]))
        );
        $message = $message
            ->withHeader(From::fromAddress($this->getAdminEmail(), $this->getAdminName()))
            ->withHeader(new Subject($subject))
            ->withHeader(To::fromArray([[$this->escape($_POST["mail"]), $name]]));
        $transport = new SmtpTransport(
            ClientFactory::fromString('smtp://'.$this->getSmtpUserName().':'.$this->getSmtpPassword().'@'.$this->getSmtpHost().'/')->newClient(),
            EnvelopeFactory::useExtractedHeader()
        );
        $transport->send($message);
    }

    public function sendReturnAdminMail()
    {
        $factory = new FormattedMessageFactory();
        $tmp = $this->createAdminMessage();
        $factory = $factory->withHtmlAndNoGeneratedAlternativeText($tmp);
        $factory = $factory->withAlternativeText(new AlternativeText(strip_tags($tmp)));
        $message = $factory->createMessage();
        $subject = mb_encode_mimeheader($this->getAdminEmailSubject());
        $subject = preg_replace('/\v/', "", $subject);
        $message = $message
            ->withHeader(From::fromAddress($this->getAdminEmail(), $this->getAdminName()))
            ->withHeader(new Subject($subject))
            ->withHeader(To::fromArray([[$this->getAdminEmail(), $this->getAdminName()]]));
        $transport = new SmtpTransport(
            ClientFactory::fromString('smtp://'.$this->getSmtpUserName().':'.$this->getSmtpPassword().'@'.$this->getSmtpHost().'/')->newClient(),
            EnvelopeFactory::useExtractedHeader()
        );
        $transport->send($message);
    }

    public function sendReturnMailSendMail()
    {
        mb_language('japanese');
        mb_internal_encoding('UTF-8');

        $headers = "";
        $headers .= "From: ".mb_encode_mimeheader($this->getUserFormName())." <".$this->getUserFormEmail()."> \n";
        $headers .= "Return-Path: ".mb_encode_mimeheader($this->getUserReturnPath())." \n";

        $subjects = 'お問い合わせありがとうございました。';

        $body = $this->createMessage();
        $text = strip_tags($body, '<br /><br>');
        $text = preg_replace("/\n/", "", $text);
        $text = preg_replace("/<br>/", "\n", $text);

        $messages = "";
        $messages .= $body;

        $mail = $this->escape($_POST["inputMail"]);

        $result = mb_send_mail($mail, $subjects, $messages, $headers);
    }

    public function sendReturnAdminMailSendMail()
    {
        mb_language('japanese');
        mb_internal_encoding('UTF-8');

        $email = isset($_POST["inputMail"]) ? $this->escape($_POST["inputMail"]) : "";

        $headers = "";
        $headers .= "From: ".$email." \n";
        $headers .= "Return-Path: ".mb_encode_mimeheader($this->getAdminReturnPath())." \n";

        $subjects = 'お問い合わせ';
        $body = $this->createAdminMessage();
        $text = strip_tags($body, '<br /><br>');
        $text = preg_replace("/\n/", "", $text);
        $text = preg_replace("/<br>/", "\n", $text);

        $messages = "";
        $messages .= $body;

        $mail = $this->getAdminToEmail();
        $result = mb_send_mail($mail, $subjects, $messages, $headers);
    }

    private function createMessage()
    {
        $week = ['日','月','火','水','木','金','土'];

        // 氏名（姓+名）
        $lname = isset($_POST['inputLname']) ? $this->escape($_POST['inputLname']) : '';
        $fname = isset($_POST['inputFname']) ? $this->escape($_POST['inputFname']) : '';
        $fullname = trim($lname . ' ' . $fname);

    // 各入力値
        $property = isset($_POST['inputPropertyInfo']) ? $this->escape($_POST['inputPropertyInfo']) : '';
        $checks   = isset($_POST['inputCheck']) && is_array($_POST['inputCheck'])
            ? implode('／', array_map([$this,'escape'], $_POST['inputCheck']))
            : '';
        $tel      = isset($_POST['inputTel']) ? $this->escape($_POST['inputTel']) : '';
        $zip      = isset($_POST['inputZip']) ? $this->escape($_POST['inputZip']) : '';
        $mail     = isset($_POST['inputMail']) ? $this->escape($_POST['inputMail']) : '';
        $address  = isset($_POST['inputAddress']) ? $this->escape($_POST['inputAddress']) : '';
        $content  = isset($_POST['inputContent']) ? $this->escape($_POST['inputContent']) : '';

        $message = "";

        // ヘッダー部分
        $message .= $fullname . " 様\n\n";
        $message .= "この度は株式会社GOODFIELDへ\n";
        $message .= "お問い合わせくださり、ありがとうございました。\n\n";
        $message .= "ご連絡の内容を確認の上、3営業日以内に、\n";
        $message .= "当社担当者より連絡を差し上げます。\n\n";
        $message .= "また、お問い合わせから3営業日以内に当社からの連絡がない場合は、\n";
        $message .= "大変お手数をおかけしますが、info@goodfield.ne.jp\n";
        $message .= "もしくは、080-1234-5678までご連絡をお願いいたします。\n\n";
        
        // 入力内容
        $message .= "▼送信いただいた内容\n";
        $message .= "----------------------------------\n\n";
        $message .= "■物件情報\n{$property}\n\n";
        $message .= "■お問い合わせ種別\n{$checks}\n\n";
        $message .= "■氏名\n{$fullname}\n\n";
        $message .= "■電話番号\n{$tel}\n\n";
        $message .= "■郵便番号\n{$zip}\n\n";
        $message .= "■メールアドレス\n{$mail}\n\n";
        $message .= "■住所\n{$address}\n\n";
        $message .= "■その他（ご質問・ご要望）\n{$content}\n\n";
        $message .= "----------------------------------\n\n";
        
        $message .= "以上、よろしくお願いいたします。\n\n";
        $message .= "--------------------------------------------------------------------------\n";
        $message .= "株式会社GOODFIELD\n";
        $message .= "--------------------------------------------------------------------------\n";
        
        return $message;
    }


    private function createAdminMessage()
    {
        $message = "";

        $week = ['日','月','火','水','木','金','土'];

        // Values (escaped for admin)
        $property  = isset($_POST['inputPropertyInfo']) ? $this->escapeAdmin($_POST['inputPropertyInfo']) : '';
        $checksArr = (isset($_POST['inputCheck']) && is_array($_POST['inputCheck']))
            ? array_map([$this,'escapeAdmin'], $_POST['inputCheck'])
            : [];
        $checks    = !empty($checksArr) ? implode('／', $checksArr) : '';

        $lname     = isset($_POST['inputLname']) ? $this->escapeAdmin($_POST['inputLname']) : '';
        $fname     = isset($_POST['inputFname']) ? $this->escapeAdmin($_POST['inputFname']) : '';
        $fullname  = trim($lname . ' ' . $fname);
        
        $tel       = isset($_POST['inputTel']) ? $this->escapeAdmin($_POST['inputTel']) : '';
        $zip       = isset($_POST['inputZip']) ? $this->escapeAdmin($_POST['inputZip']) : '';
        $mail      = isset($_POST['inputMail']) ? $this->escapeAdmin($_POST['inputMail']) : '';
        $address   = isset($_POST['inputAddress']) ? $this->escapeAdmin($_POST['inputAddress']) : '';
        $content   = isset($_POST['inputContent']) ? $this->escapeAdmin($_POST['inputContent']) : '';
        
        // Header
        $message .= "各位\n\n";
        $message .= "GOODFIELD HPより、お問い合わせが発生しました。\n";
        $message .= "内容を確認の上、ご対応願います。\n\n";
        $message .= "------------------------------\n\n";
        
        // Body (all fields)
        $message .= "■物件情報\n{$property}\n\n";
        $message .= "■お問い合わせ種別\n{$checks}\n\n";
        $message .= "■氏名\n{$fullname}\n\n";
        $message .= "■電話番号\n{$tel}\n\n";
        $message .= "■郵便番号\n{$zip}\n\n";
        $message .= "■メールアドレス\n{$mail}\n\n";
        $message .= "■住所\n{$address}\n\n";
        $message .= "■その他（ご質問・ご要望）\n{$content}\n\n";
        $message .= "------------------------------\n\n";
        
        // Footer with metadata
        $message .= "送信日時: ".date('Y/m/d')." (".$week[date('w')].") ".date('H:i')."\n";
        $message .= "ブラウザ: ".$_SERVER['HTTP_USER_AGENT']."\n";
        
        return $message;
    }
}
