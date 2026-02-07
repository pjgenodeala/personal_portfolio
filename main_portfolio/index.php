<?php ob_start(); ?>
<?php
require_once __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 0);

use \APP\Form;
use \APP\CsrfValidator;
use \Dotenv\Dotenv;

// 他サイトでiframe引用を禁止
header('X-FRAME-OPTIONS: SAMEORIGIN');

// セッションの開始
session_cache_expire(0);
session_cache_limiter('private_no_expire');
session_start();

// 環境で振り分けできるように.envの読み込み
$dotenv = new Dotenv(__DIR__);
$dotenv->load();

// フォームクラスの作成
$formTool = new Form();

// .envを読み込んでメールアドレスをセット
$thisEnv = getenv('THIS_ENV');
$formTool->setAdminToEmail(getenv($thisEnv . '_ADMIN_TO_EMAIL'));
$formTool->setAdminFromEmail(getenv($thisEnv . '_ADMIN_FORM_EMAIL'));
$formTool->setAdminFromName(getenv($thisEnv . '_ADMIN_FORM_NAME'));
$formTool->setAdminReturnPath(getenv($thisEnv . '_ADMIN_RETURN_PATH'));

$formTool->setUserFormEmail(getenv($thisEnv . '_USER_FORM_EMAIL'));
$formTool->setUserFormName(getenv($thisEnv . '_USER_FORM_NAME'));
$formTool->setUserReturnPath(getenv($thisEnv . '_USER_RETURN_PATH'));

$formTool->setSmtpUserName(getenv($thisEnv . '_SMTP_USER'));
$formTool->setSmtpPassword(getenv($thisEnv . '_SMTP_PASSWORD'));
$formTool->setSmtpHost(getenv($thisEnv . '_SMTP_HOST'));
$server = getenv('MAIN_SERVER');

// フォームとバリデーションルールを設定
$formTool->setForms([
    'inputPropertyInfo' => [
        'name'    => '物件情報',
        'type'    => 'text',
        'require' => false,
        'rule'    => [],
    ],

    'inputCheck' => [
        'name'    => 'お問い合わせ種別',
        'type'    => 'checkbox',
        'require' => true,
        'rule'    => [],
    ],

    'inputLname' => [
        'name'    => '氏名（姓）',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'min' => 1,
            'max' => 50,
        ],
    ],

    'inputFname' => [
        'name'    => '氏名（名）',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'min' => 1,
            'max' => 50,
        ],
    ],

    'inputTel' => [
        'name'    => '電話番号',
        'type'    => 'text',
        'require' => true,
        'rule'    => [],
    ],

    'inputZip' => [
        'name'    => '郵便番号',
        'type'    => 'text',
        'require' => true,
        'rule'    => [],
    ],

    'inputMail' => [
        'name'    => 'メールアドレス',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'mail' => true,
        ],
    ],

    'inputAddress' => [
        'name'    => '住所 ',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'min' => 2,
            'max' => 50,
        ],
    ],

    'inputContent' => [
        'name'    => 'その他（ご質問・ご要望）',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'min' => 0,
            'max' => 1000,
        ],
    ],
]);

// メッセージバッグの初期化
if ($formTool->getMessages() === false) {
    $formTool->setMessages([]);
    $messages = $formTool->getMessages();
}

// バリデーション
$isComplete = false;
if (!empty($_POST)) {
    // POST値のバリデーション
    $formTool->validate();
    // バリデーションエラーが格納される。
    $messages   = $formTool->getMessages();
    $isComplete = $formTool->isComplete($messages);
}

$isEmptyPost         = empty($_POST);
$hasValidayteError   = (!empty($_POST) && !empty($messages)) || (isset($_POST['send']) && $_POST['send'] == 'false');
$hasNotValidateError = (!isset($_POST['send']) || $_POST['send'] != 'false') && (empty($messages) || !empty($_POST['send']));
$alreadySendMail     = isset($_SESSION['sendMail']) && $_SESSION['sendMail'] == 1 ? true : false;

$isIndex    = (($isEmptyPost || $hasValidayteError) && !$isComplete) || $alreadySendMail;
$isConfirm  = !$isEmptyPost && $hasNotValidateError && !$isComplete && !$alreadySendMail;
$isComplete = !$isEmptyPost && $isComplete && !$alreadySendMail;

$protocol  = empty($_SERVER['HTTPS']) ? 'http://' : 'https://';
$canonical = $protocol . $_SERVER['HTTP_HOST'];

if ($isIndex) {
    unset($_POST['send']);
}

if (isset($_SESSION['sendMail'])) {
    unset($_SESSION['sendMail']);
    $_POST = [];
}

$root        = './';
$title       = 'pj_portfolio';
$description = 'ページディスクリプション';

// start content
?>


<?php
// end content
?>
<?php $content = ob_get_contents(); ?>
<?php ob_end_clean(); ?>
<?php
include($root . '_layout.php');
?>
