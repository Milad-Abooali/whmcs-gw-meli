<?php
/**
 **************************************************************************
 * IranMelliBank Gateway
 * IranMelliBank.php
 * Meta Data & Config & Link
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

/**
 * Meta Data
 * @return array
 */
function IranMelliBank_MetaData()
{
    return [
        "DisplayName"                   => "IranMelliBank",
        "gatewayType"                   => "Bank",
        "failedEmail"                   => "Custom Payment_Failed",
        "successEmail"                  => "Custom Payment_Success",
        "pendingEmail"                  => "Custom Payment_Pending",
        "APIVersion"                    => "1.1",
        "DisableLocalCreditCardInput"   => true,
        "TokenisedStorage"              => false
    ];
}

/**
 * Config
 * @return array
 */
function IranMelliBank_config()
{
    return [
        "FriendlyName" 			 => ["Type" => "System", "Value" => "بانک ملی ایران"],
        // Gateway Setup
        "cb_gw_method"           => ["FriendlyName" => "روش اتصال", "Type" => "radio", "Options" => "Token,Old"],
        "cb_gw_id"               => ["FriendlyName" => "کد پذيرنده", "Type" => "text", "Size" => "50"],
        "cb_gw_pass"             => ["FriendlyName" => "رمز پذيرنده", "Type" => "text", "Size" => "50"],
        "cb_gw_unit"             => ["FriendlyName" => "واحد پول سیستم", "Type" => "dropdown", "Options" => ["1" => "ریال", "10" => "تومان"],"Description" => "لطفا واحد پول سیستم خود را انتخاب کنید."],
        // Email Notification
        "cb_email"               => ["FriendlyName" => "Email Notify", "Type" => "", "Description" => "<i class='far fa-envelope'></i> ارسال هشدار پرداخت به پست‌الکترونیک"],
        "cb_email_on_success"    => ["FriendlyName" => "On Success", "Type" => "dropdown", "Options" => ["0" => "خیر", "1" => "بله"],"Description" => "ارسال هشدار تراکنش موفق به پست‌الکترونیک"],
        "cb_email_on_error"      => ["FriendlyName" => "On Failed ", "Type" => "dropdown", "Options" => ["0" => "خیر", "1" => "بله"], "Description" => "ارسال هشدار تراکنش ناموفق به پست‌الکترونیک"],
        "cb_email_from"          => ["FriendlyName" => "Email From", "Type" => "text", "Value"=>"info@example.ir", "Description" => "جهت اطمینان از دریافت ایمیل حتما آدرس واقعی مستقر روی همین سرور را استفاده کنید."],
        "cb_email_address"       => ["FriendlyName" => "Email TO", "Type" => "text", "Description" => "جهت جداسازی از کاراکتر , استفاده کنید."],
        // Telegram Notification
        "cb_telegram"            => ["FriendlyName" => "Telegram Notify", "Type" => "", "Description" => "<img alt='Telegram' src='https://t3.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=https://telegram.org'> ارسال هشدار پرداخت به تلگرام"],
        "cb_telegram_on_success" => ["FriendlyName" => "On Success", "Type" => "dropdown", "Options" => ["0" => "خیر", "1" => "بله"],"Description" => "ارسال هشدار تراکنش موفق به تلگرام"],
        "cb_telegram_on_error"   => ["FriendlyName" => "On Failed", "Type" => "dropdown", "Options" => ["0" => "خیر", "1" => "بله"], "Description" => "ارسال هشدار تراکنش ناموفق به تلگرام"],
        "cb_telegram_chatid"     => ["FriendlyName" => "Telegram Chat Id", "Type" => "text", "Description" => "شناسه چت مقصد"],
        "cb_telegram_bot"        => ["FriendlyName" => "Telegram Bot Token", "Type" => "text", "Description" => "توکن ربات تلگرام"],
        // Copyright & Branding Link
        # "cb_copyright"    => ["FriendlyName"  => "حق نشر", "Type" => "", "Description" => "Copyright (c) <a href='https://codebox.ir' target='_blank' style='color:#00741b'>Codebox</a> - 2022"],
        "cb_copyright"          => ["FriendlyName"  => "حق نشر", "Type" => "", "Description" => "Copyright (c) <a href='https://whmcsco.ir' target='_blank'>مجموعه WHMCS فارسی</a>"]
    ];
}

/**
 * Link
 * @param $params
 * @return string
 */
function IranMelliBank_link($params)
{
    $gateway_name = 'IranMelliBank';
    $amount_Rial  = round(($params['amount']-'.00') * $params['cb_gw_unit']);
    return '<form method="post" action="modules/gateways/'.$gateway_name.'/payment.php?a=send">
	<input type="hidden" name="invoiceid" value="'.$params['invoiceid'].'">
	<input type="hidden" name="amount" value="'.$amount_Rial.'">
	<input type="hidden" name="email" value="'.$params['clientdetails']['email'].'">
	<input type="submit" name="pay" value="پرداخت"></form>
	<img src="/modules/gateways/'.$gateway_name.'/logo.png" alt="'.$gateway_name.'" style="max-width:170px;height:45px;">';
}

