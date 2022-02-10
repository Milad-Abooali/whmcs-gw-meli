<?php
/**
 **************************************************************************
 * IranMelliBank Gateway
 * payment.php
 * Send Request & Callback
 * @author           Milad Abooali <m.abooali@hotmail.com>
 * @version          1.0
 **************************************************************************
 * @noinspection PhpUnused
 * @noinspection PhpUndefinedFunctionInspection
 * @noinspection SpellCheckingInspection
 * @noinspection PhpIncludeInspection
 * @noinspection PhpDeprecationInspection
 * @noinspection PhpUndefinedMethodInspection
 * @noinspection PhpUnhandledExceptionInspection
 **************************************************************************
 */

global $CONFIG;
$cb_output = [$_POST,$_GET];
$cb_gw_name = 'IranMelliBank';
$action = isset($_GET['a']) ? $_GET['a'] : false;
$root_path     = '../../../';
$includes_path = '../../../includes/';
include($root_path.((file_exists($root_path.'init.php'))?'init.php':'dbconnect.php'));
include($includes_path.'functions.php');
include($includes_path.'gatewayfunctions.php');
include($includes_path.'invoicefunctions.php');
$modules    = getGatewayVariables($cb_gw_name);
if (!$modules['type']) die('Module Not Activated');
$amount 			= intval($_REQUEST['amount']);
$invoice_id 	    = $_REQUEST['invoiceid'];
$gw_id          	= $modules['cb_gw_id'];

/**
 * Telegram Notify
 * @param $notify
 */
function notifyTelegram($notify) {
    global $modules;
    $row = "------------------";
    $pm= "\n".$row.$row.$row."\n".$notify['title']."\n".$row."\n".$notify['text'];
    $chat_id = $modules['cb_telegram_chatid'];
    $botToken = $modules['cb_telegram_bot']; // "291958747:AAF65_lFLaap35HS5zYxSbO1ycNb8Pl2vTk";
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
    if($receivers) foreach ($receivers as $receiver){
        $cb_output['mail'][] = mail($receiver, $notify['title'], $notify['text'], $headers);
    }
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
    $tran_id  = $order_id  = $invoice_id;
    $ref_code = $_POST['SaleReferenceId'];
    if(!empty($invoice_id)) {
        $cb_output['invoice_id'] = $invoice_id;
        if(!empty($order_id) && !empty($tran_id) && !empty($ref_code)) {
            $cb_output['tran_id'] = $tran_id;
            $invoice_id = checkCbInvoiceID($invoice_id, $modules['name']);
            $results = select_query("tblinvoices", "", array("id" => $invoice_id));
            $data = mysql_fetch_array($results);
            $db_amount = strtok($data['total'], '.');
            if ($_POST['ResCode'] == '0') {
                include_once('nusoap.php');
                $client = new nusoap_client('https://bpm.shaparak.ir/pgwchannel/services/pgw?wsdl');
                $namespace='http://interfaces.core.sw.bps.com/';
                $parameters = array(
                    'terminalId' 		=> $modules['cb_gw_terminal_id'],
                    'userName' 			=> $modules['cb_gw_user'],
                    'userPassword' 		=> $modules['cb_gw_pass'],
                    'orderId'           => $_POST['SaleOrderId'],
                    'saleOrderId'       => $_POST['SaleOrderId'],
                    'saleReferenceId'   => $_POST['SaleReferenceId']
                );
                $cb_output['res']['result'] = $bpVerifyRequest = $client->call('bpVerifyRequest', $parameters, $namespace);
                if($bpVerifyRequest == 0) {
                    $bpSettleRequest = $client->call('bpSettleRequest', $parameters, $namespace);
                    if($bpSettleRequest == 0) {
                        $cartNumber = $_POST['CardHolderPan'];
                        addInvoicePayment($invoice_id, $ref_code, $amount, 0, $cb_gw_name);
                        logTransaction($modules["name"], array('invoiceid' => $invoice_id,'order_id' => $order_id,'amount' => $amount." ".(($modules['cb_gw_unit']>1) ? 'Toman' : 'Rial'),'tran_id' => $tran_id,'RefId' => $_POST['RefId'],'SaleReferenceId' => $ref_code,'CardNumber' => $cartNumber,'status' => "OK"), "موفق");
                        $notify['title'] = $cb_gw_name.' | '."تراکنش موفق";
                        $notify['text']  = "\n\rGateway: $cb_gw_name\n\rAmount: $amount ".(($modules['cb_gw_unit']>1) ? 'Toman' : 'Rial')."\n\rOrder: $order_id\n\rInvoice: $invoice_id\n\rCart Number: $cartNumber";
                        if($modules['cb_email_on_success']) notifyEmail($notify);
                        if($modules['cb_telegram_on_success']) notifyTelegram($notify);
                    }
                    else {
                        $client->call('bpReversalRequest', $parameters, $namespace);
                        logTransaction($modules["name"], array('invoiceid' => $invoice_id,'order_id' => $order_id,'amount' => $amount." ".(($modules['cb_gw_unit']>1) ? 'Toman' : 'Rial'),'tran_id' => $tran_id, 'status' => $bpSettleRequest), "ناموفق");
                        $notify['title'] = $cb_gw_name.' | '."تراکنش ناموفق";
                        $notify['text']  = "\n\rGateway: $cb_gw_name\n\rAmount: $amount ".(($modules['cb_gw_unit']>1) ? 'Toman' : 'Rial')."\n\rOrder: $order_id\n\rInvoice: $invoice_id\n\rError: به دلیل رخ دادن خطا در پرداخت، درخواست بازگشت وجه داده شد.";
                        if($modules['cb_email_on_error']) notifyEmail($notify);
                        if($modules['cb_telegram_on_error']) notifyTelegram($notify);
                    }
                }
                else {
                    $client->call('bpReversalRequest', $parameters, $namespace);
                    logTransaction($modules["name"], array('invoiceid' => $invoice_id,'order_id' => $order_id,'amount' => $amount." ".(($modules['cb_gw_unit']>1) ? 'Toman' : 'Rial'),'tran_id' => $tran_id, 'status' => $bpVerifyRequest), "ناموفق");
                    $notify['title'] = $cb_gw_name.' | '."تراکنش ناموفق";
                    $notify['text']  = "\n\rGateway: $cb_gw_name\n\rAmount: $amount ".(($modules['cb_gw_unit']>1) ? 'Toman' : 'Rial')."\n\rOrder: $order_id\n\rInvoice: $invoice_id\n\rError: به دلیل رخ دادن خطا در پرداخت، درخواست بازگشت وجه داده شد.";
                    if($modules['cb_email_on_error']) notifyEmail($notify);
                    if($modules['cb_telegram_on_error']) notifyTelegram($notify);
                }
            } else {
                logTransaction($modules["name"], array('invoiceid' => $invoice_id,'order_id' => $order_id,'amount' => $amount." ".(($modules['cb_gw_unit']>1) ? 'Toman' : 'Rial'),'tran_id' => $tran_id, 'status' => $_POST['ResCode']), "ناموفق");
                $notify['title'] = $cb_gw_name.' | '."تراکنش ناموفق";
                $notify['text']  = "\n\rGateway: $cb_gw_name\n\rAmount: $amount ".(($modules['cb_gw_unit']>1) ? 'Toman' : 'Rial')."\n\rOrder: $order_id\n\rInvoice: $invoice_id\n\rError: به دلیل رخ دادن خطا در پرداخت، درخواست بازگشت وجه داده شد.";
                if($modules['cb_email_on_error']) notifyEmail($notify);
                if($modules['cb_telegram_on_error']) notifyTelegram($notify);
            }


        }
        $action = $CONFIG['SystemURL'] . "/viewinvoice.php?id=" . $invoice_id;
        header('Location: ' . $action);
        //print("<pre>".print_r($cb_output,true)."</pre>");
    }
    else {
        echo "invoice id is blank";
    }
}
else if($action==='send') {
    $key 			= $modules['cb_gw_TerminalKey'];
    $MerchantId 	= $modules['cb_gw_MerchantId'];
    $TerminalId 	= $modules['cb_gw_TerminalId'];
    $LocalDateTime 	= date("m/d/Y g:i:s a");
    $SignData 		= sing_maker("$TerminalId;$invoice_id;$amount","$key");
    $callback_URL   = $CONFIG['SystemURL']."/modules/gateways/$cb_gw_name/payment.php?a=callback&invoiceid=". $invoice_id.'&amount='.$amount;
    $data 			= array(
        'TerminalId'	=> $TerminalId,
        'MerchantId'	=> $MerchantId,
        'Amount' 		=> $amount,
        'SignData' 		=> $SignData,
        'ReturnUrl' 	=> $callback_URL,
        'LocalDateTime' => $LocalDateTime,
        'OrderId' 		=> $invoice_id
    );
    $str_data 	= json_encode($data);
    $res 		= curl_webservice('https://sadad.shaparak.ir/vpg/api/v0/Request/PaymentRequest',$str_data);
    $arrres 	= json_decode($res);
    if($arrres->ResCode==0) {
        $Token 	= $arrres->Token;
        $url 	= "https://sadad.shaparak.ir/VPG/Purchase?Token=$Token";
        header("Location:$url");
    } else {
        die($arrres->Description);
    }
}

