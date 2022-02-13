<?php
/**
 **************************************************************************
 * IranGateway Gateway
 * IranGateway.php
 * Send Request & Callback
 * @author           Milad Abooali <m.abooali@hotmail.com>
 * @version          1.0
 **************************************************************************
 * @noinspection PhpUnused
 * @noinspection PhpUndefinedFunctionInspection
 * @noinspection PhpUndefinedMethodInspection
 * @noinspection PhpDeprecationInspection
 * @noinspection SpellCheckingInspection
 * @noinspection PhpIncludeInspection
 * @noinspection PhpIncludeInspection
 */

global $CONFIG;

$cb_gw_name    = 'IranGateway';
$cb_output     = ['POST'=>$_POST,'GET'=>$_GET];
$action 	   = isset($_GET['a']) ? $_GET['a'] : false;

$root_path     = '../../../';
$includes_path = '../../../includes/';
include($root_path.((file_exists($root_path.'init.php'))?'init.php':'dbconnect.php'));
include($includes_path.'functions.php');
include($includes_path.'gatewayfunctions.php');
include($includes_path.'invoicefunctions.php');

$modules       = getGatewayVariables($cb_gw_name);
if(!$modules['type']) die('Module Not Activated');

$invoice_id    = $_REQUEST['invoiceid'];
$amount_rial   = intval($_REQUEST['amount']);
$amount        = $amount_rial / $modules['cb_gw_unit'];
$callback_URL  = $CONFIG['SystemURL']."/modules/gateways/$cb_gw_name/payment.php?a=callback&invoiceid=". $invoice_id.'&amount='.$amount_rial;
$invoice_URL   = $CONFIG['SystemURL']."/viewinvoice.php?id=".$invoice_id;

/**
 * Telegram Notify
 * @param $notify
 */
function notifyTelegram($notify) {
    global $modules;
    $row = "------------------";
    $pm= "\n".$row.$row.$row."\n".$notify['title']."\n".$row."\n".$notify['text'];
    $chat_id = $modules['cb_telegram_chatid'];
    $botToken = $modules['cb_telegram_bot'];
    $data = ['chat_id' => $chat_id, 'text' => $pm];
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, "https://api.telegram.org/bot$botToken/sendMessage");
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_exec($curl);
    curl_close($curl);
}

/**
 * Email Notify
 * @param $notify
 */
function notifyEmail($notify) {
    global $modules;
    global $cb_output;
    $receivers = explode(',', $modules['cb_email_address']);
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/plain;charset=UTF-8" . "\r\n";
    $headers .= "From: ".$modules['cb_email_from']."\r\n";
    if($receivers) foreach ($receivers as $receiver)
        $cb_output['mail'][] = mail($receiver, $notify['title'], $notify['text'], $headers);
}

/**
 * Payment Failed
 * @param $log
 */
function payment_failed($log)
{
    global $modules;
    global $cb_gw_name;
    global $cb_output;
    $log['status'] = "unpaid";
    $cb_output['payment_failed']=$log;
    logTransaction($modules["name"], $log, "ناموفق");
    if($modules['cb_email_on_error'] || $modules['cb_telegram_on_error']){
        $notify['title'] = $cb_gw_name . ' | ' . "تراکنش ناموفق";
        $notify['text'] = '';
        foreach ($log as $key=>$item)
            $notify['text'] .= "\n\r$key: $item";
        if ($modules['cb_email_on_error']) notifyEmail($notify);
        if ($modules['cb_telegram_on_error']) notifyTelegram($notify);
    }
}

/**
 * Payment Success
 * @param $log
 */
function payment_success($log)
{
    global $modules;
    global $cb_gw_name;
    global $cb_output;
    $log['status'] = "OK";
    $cb_output['payment_success']=$log;
    logTransaction($modules["name"], $log, "موفق");
    if($modules['cb_email_on_success'] || $modules['cb_telegram_on_success']){
        $notify['title'] = $cb_gw_name . ' | ' . "تراکنش موفق";
        $notify['text'] = '';
        foreach ($log as $key=>$item)
            $notify['text'] .= "\n\r$key: $item";
        if ($modules['cb_email_on_success']) notifyEmail($notify);
        if ($modules['cb_telegram_on_success']) notifyTelegram($notify);
    }
}

/**
 * Redirecttion
 * @param $url
 */
function redirect($url)
{
    if (headers_sent())
        echo "<script>window.location.assign('$url')</script>";
    else
        header("Location: $url");
    exit;
}

/**
 * Show Error
 * @param $text
 */
function show_error($text)
{
    global $cb_gw_name;
    global $invoice_URL;
    echo "<img src='/modules/gateways/$cb_gw_name/logo.png' alt='$cb_gw_name'>
        <p>$text</p><a href='$invoice_URL'>بازگشت</a>";
}

/**
 * Get DB Amount
 * @return float
 */
function get_db_amount(){
    global $modules;
    global $invoice_id;
    $sql = select_query("tblinvoices", "", array("id" => $invoice_id));
    $sql_res = mysql_fetch_array($sql);
    $db_amount = strtok($sql_res['total'], '.');
    return $db_amount * $modules['cb_gw_unit'];
}

/**
 * Error Codes Translator
 * @param string $ResCode
 * @return string
 */
function translate_error($ResCode='')
{
    switch($ResCode)
    {
        case '-1':$prompt="خطا درپردازش اطلاعات ارسالی";break;
        case '-3':$prompt="ورودی حاوی کارکتر غیرمجاز";break;
        case '-4':$prompt="کلمه عبور یا کد فروشنده اشتباه است.";break;
        case '-6':$prompt="سند قبلا برگشت کامل یافته است.";break;
        case '-7':$prompt="رسید دیجیتال خالی است.";break;
        case '-8':$prompt="طول ورودی بیشتر از حد مجاز است";break;
        case '-9':$prompt="وجود کاراکترهای غیرمجاز در مبلغ بازگشتی";break;
        case '-10':$prompt="رسید دیجیتال بصورت Base64 نیست.";break;
        case '-11':$prompt="طول ورودی ها کمتر از حد مجاز است.";break;
        case '-12':$prompt="مبلغ برگشتی منفی است.";break;
        case '-13':$prompt="مبلغ برگشتی برای برگشت جزئی بیش از مبلغ برگشت خورده ی رسید دیجیتال است.";break;
        case '-14':$prompt="چنین تراکنشی تعریف نشده است.";break;
        case '-15':$prompt="مبلغ برگشتی بصورت اعشاری داده شده است.";break;
        case '-16':$prompt="خطای داخلی سیستم";break;
        case '-17':$prompt="برگشت زدن جزوی تراکنش مجاز نمیباشد.";break;
        case '-18':$prompt="ای پی سرور فروشنده نامعتبر است.";break;
        default:$prompt="خطاي نامشخص.";
    }
    return $prompt;
}

/**
 * Sing Maker
 * @param $str
 * @param $key
 * @return false|string
 */
function sing_maker($str, $key)
{
    $method 	= 'DES-EDE3';
    $iv 		= openssl_random_pseudo_bytes(openssl_cipher_iv_length($method));
    return openssl_encrypt($str, $method, $key, 0, $iv);
}

/**
 * Curl Webservice
 * @param $url
 * @param false $data
 * @return bool|string
 */
function curl_webservice($url, $data = false)
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data)));
    $result = curl_exec($curl);
    curl_close($curl);
    return $result;
}

if($action==='callback') {
    if(empty($invoice_id)) die('Invoice ID Missing!');
    $cb_output['invoice_id'] = $invoice_id;
    // Response Items From Bank
    $key 		= $modules['cb_gw_TerminalKey'];
    $OrderId 	= (isset($_POST["OrderId"])) 	? $_POST["OrderId"] 	: "";
    $Token 		= (isset($_POST["token"])) 		? $_POST["token"] 		: "";
    $ResCode 	= (isset($_POST["ResCode"])) 	? $_POST["ResCode"] 	: "";
    // Check Invoice ID by WHMCS
    $invoice_id = checkCbInvoiceID($invoice_id, $modules['name']);
    // Check Invoice Amount From Database
    $db_amount_rial = get_db_amount();
    $cb_output['db_amount_rial'] = $db_amount_rial;

    $log = array(
        'Invoice'        => $invoice_id,
        'Amount'         => number_format($amount).(($modules['cb_gw_unit']>1)?' Toman':' Rial'),
        'Order'          => $OrderId
    );
    $arrres = null;
    if($ResCode==0) {
        $verifyData = array('Token'=>$Token,'SignData'=>sing_maker($Token,$key));
        $str_data 	= json_encode($verifyData);
        $res 		= curl_webservice('https://sadad.shaparak.ir/vpg/api/v0/Advice/Verify',$str_data);
        $arrres 	= json_decode($res);
    }
    if($arrres->ResCode!=-1 && $ResCode==0) {
        // Check Transaction ID by WHMCS
        checkCbTransID($arrres->RetrivalRefNo);
        $log['Transaction'] =   $arrres->RetrivalRefNo;
        $log['TRACENO']     =   $arrres->SystemTraceNo;
        payment_success($log);
        addInvoicePayment($invoice_id, $arrres->RetrivalRefNo, $amount, 0, $cb_gw_name);
    } else {
        $log['Error']  = translate_error($ResCode);
        payment_failed($log);
    }
    // print("<pre>".print_r($cb_output,true)."</pre>");
    redirect($invoice_URL);
}
elseif ($action==='send'){
    $key 			= $modules['cb_gw_TerminalKey'];
    $MerchantId 	= $modules['cb_gw_MerchantId'];
    $TerminalId 	= $modules['cb_gw_TerminalId'];
    $LocalDateTime 	= date("m/d/Y g:i:s a");
    $SignData 		= sing_maker("$TerminalId;$invoice_id;$amount_rial","$key");
    $data 			= array(
        'TerminalId'	=> $TerminalId,
        'MerchantId'	=> $MerchantId,
        'Amount' 		=> $amount_rial,
        'SignData' 		=> $SignData,
        'ReturnUrl' 	=> $callback_URL,
        'LocalDateTime' => $LocalDateTime,
        'OrderId' 		=> $invoice_id
    );
    $str_data 	= json_encode($data);
    $res 		= curl_webservice('https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest',$str_data);
    $arrres 	= json_decode($res);
    if($arrres->ResCode==0) {
        redirect("https://sadad.shaparak.ir/VPG/Purchase?Token=".$arrres->Token);
    } else {
        show_error($arrres->Description);
    }
}