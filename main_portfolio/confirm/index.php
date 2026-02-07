<?php ob_start(); ?>
<?php
require_once __DIR__ . '/../vendor/autoload.php';
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
$dotenv = new Dotenv(__DIR__ . '/../');
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
        'rule'    => []
    ],

    'inputCheck' => [
        'name'    => 'お問い合わせ種別',
        'type'    => 'checkbox',
        'require' => true,
        'rule'    => []
    ],

    'inputLname' => [
        'name'    => '氏名（姓）',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'min' => 1,
            'max' => 50,
        ]
    ],

    'inputFname' => [
        'name'    => '氏名（名）',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'min' => 1,
            'max' => 50,
        ]
    ],

    'inputTel' => [
        'name'    => '電話番号',
        'type'    => 'text',
        'require' => true,
        'rule'    => []
    ],

    'inputZip' => [
        'name'    => '郵便番号',
        'type'    => 'text',
        'require' => true,
        'rule'    => []
    ],

    'inputMail' => [
        'name'    => 'メールアドレス',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'mail' => true
        ]
    ],

    'inputAddress' => [
        'name'    => '住所 ',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'min' => 2,
            'max' => 50,
        ]
    ],

    'inputContent' => [
        'name'    => 'その他（ご質問・ご要望）',
        'type'    => 'text',
        'require' => true,
        'rule'    => [
            'min' => 0,
            'max' => 1000,
        ]
    ]
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

    // バリデーションエラーが格納される
    $messages   = $formTool->getMessages();
    $isComplete = $formTool->isComplete($messages);
}

$isEmptyPost         = empty($_POST);
$hasValidayteError   = (!empty($_POST) && !empty($messages)) || (isset($_POST["send"]) && $_POST["send"] == "false");
$hasNotValidateError = (!isset($_POST["send"]) || $_POST["send"] != "false") && (empty($messages) || !empty($_POST["send"]));
$alreadySendMail     = isset($_SESSION["sendMail"]) && $_SESSION["sendMail"] == 1 ? true : false;

$isIndex    = (($isEmptyPost || $hasValidayteError) && !$isComplete) || $alreadySendMail;
$isConfirm  = !$isEmptyPost && $hasNotValidateError && !$isComplete && !$alreadySendMail;
$isComplete = !$isEmptyPost && $isComplete && !$alreadySendMail;

$protocol  = empty($_SERVER["HTTPS"]) ? "http://" : "https://";
$canonical = $protocol . $_SERVER["HTTP_HOST"];

if ($isIndex) {
    unset($_POST["send"]);
}

if (isset($_SESSION['sendMail'])) {
    unset($_SESSION['sendMail']);
    $_POST = [];
}

// エラー時はindexに飛ばす
if ($hasValidayteError) {
    echo "<form method='post' action='../' id='form01'>";
    foreach ($_POST as $key => $val) {
        if ($key === 'inputCheck' && !empty($formTool->currentValue($key))) {
            foreach ($formTool->currentValue($key) as $value) {
                echo '<input type="hidden" style="display:none;" name="' . $key . '[]" value="' . $value . '" >';
            }
        } else {
            echo '<input type="hidden" id="' . $key . '" name="' . $key . '" value="' . $formTool->currentValue($key) . '" >';
        }
    }
    echo '<input type="hidden" id="pageKind" name="pageKind" value="1">';
    echo '</form>';
    echo '<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>';
    echo '<script>$("#form01").submit();</script>';
    exit;
}

$root        = '../';
$title       = 'ページタイトル';
$description = 'ページディスクリプション';

// start content
?>

<article id="warp">
    <header id="header">
        <div class="logo">
            <img src="/assets/img/logo.webp" alt="GOODFIELD">
        </div>
    </header>

    <section class="confirm">
        <h2 class="contact__tit">資料請求・見学予約</h2>

        <form method="post" action="../complete/" class="inner" id="form01">
            <div class="form-items">

                <div class="form-item">
                    <div class="form-item__label">物件情報</div>
                    <div class="form-item__body">
                        <?php
                        $inputPropertyInfo = $formTool->currentValue('inputPropertyInfo') ?? '';
                        echo htmlspecialchars($inputPropertyInfo, ENT_QUOTES, 'UTF-8');
                        ?>
                    </div>
                </div>

                <div class="form-item form-item--hol">
                    <div class="form-item__label">お問い合わせ種別</div>
                    <div class="form-item__body">
                        <?php
                        $inputCheck = ["選択なし"];
                        if ($formTool->currentValue('inputCheck') !== null) {
                            $inputCheck = $formTool->currentValue('inputCheck');
                        }
                        echo htmlspecialchars(implode('、', (array)$inputCheck), ENT_QUOTES, 'UTF-8');
                        ?>
                    </div>
                </div>

                <div class="form-item">
                    <div class="form-item__label require">氏名</div>
                    <div class="form-item__body col2">
                        <?php
                        $inputLname = $formTool->currentValue('inputLname') ?? '';
                        $inputFname = $formTool->currentValue('inputFname') ?? '';
                        ?>
                        <div class="col"><?= htmlspecialchars($inputLname, ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="col"><?= htmlspecialchars($inputFname, ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>
                </div>

                <div class="form-item">
                    <div class="form-item__label require">メールアドレス</div>
                    <div class="form-item__body">
                        <?= htmlspecialchars($formTool->currentValue('inputMail') ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>

                <div class="form-item">
                    <div class="form-item__label require">電話番号</div>
                    <div class="form-item__body">
                        <?= htmlspecialchars($formTool->currentValue('inputTel') ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>

                <div class="form-item">
                    <div class="form-item__label require">郵便番号</div>
                    <div class="form-item__body">
                        <?= htmlspecialchars($formTool->currentValue('inputZip') ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>

                <div class="form-item">
                    <div class="form-item__label require">住所</div>
                    <div class="form-item__body">
                        <?= htmlspecialchars($formTool->currentValue('inputAddress') ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                </div>

                <div class="form-item">
                    <div class="form-item__label require">その他（ご質問・ご要望）</div>
                    <div class="form-item__body">
                        <?php
                        $inputContent = '';
                        if ($formTool->currentValue('inputContent') !== null) {
                            $inputContent = preg_replace('/\r\n|\r|\n/', '<br>', $formTool->currentValue('inputContent'));
                        }
                        echo $inputContent;
                        ?>
                    </div>
                </div>

            </div>

            <?php
            $token     = new \APP\CsrfValidator();
            $formValue = $formTool->getForms();
            foreach ($formValue as $key => $value) {
                if ($key === 'inputCheck' && !empty($formTool->currentValue($key))) {
                    foreach ($formTool->currentValue($key) as $val) {
                        echo '<input type="hidden" style="display:none;" name="' . $key . '[]" value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '" >';
                    }
                } else {
                    echo '<input type="hidden" id="' . $key . '" name="' . $key . '" value="' . htmlspecialchars($formTool->currentValue($key), ENT_QUOTES, 'UTF-8') . '" >';
                }
            }
            ?>
            <input type="hidden" name="token" value="<?php echo $token->generate(); ?>">
            <input type="hidden" name="send" value="true" class="sendFlg" />
            <input type="hidden" id="pageKind" name="pageKind" value="2">

            <div class="form-buttons">
                <button type="button" class="form-button back" id="back_btn">
                    <span>戻る</span>
                </button>
                <button type="submit" class="form-button" name="submitConfirm" id="comp_btn">
                    <span>送信する</span>
                    <img src="/assets/img/btn-arw.webp" alt="arw">
                </button>
            </div>
        </form>

        <div class="caution">
            <p class="u-text-center">注意事項</p>
            <p>
                ・本キャンペーンは本ページの来場予約フォームからご来場予約の上、ご見学頂いた新規のご家族が対象となります。<br>
                ・本キャンペーンはご見学の上、見学時のアンケートに回答頂いた方のみ対象とさせていただきます。<br>
                ・本キャンペーンはひと家族１回限りとさせていただきます。
            </p>
        </div>
    </section>
</article>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script>
$(function () {
    $('#back_btn').on('click', function () {
        $('#form01').attr('action', '../');
        $('#form01').submit();
    });
    $('#comp_btn').on('click', function () {
        $('#form01').submit();
    });
});
</script>

<?php
// end content
?>
<?php $content = ob_get_contents(); ?>
<?php ob_end_clean(); ?>
<?php
include($root . '_layout.php');
?>
