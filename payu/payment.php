<?php
/**
 * payment.php
 * -----------
 **/
 
include_once "PayU.cls.php";

function payuOpt()
{
	$button = "<div style='position:absolute; top:50%; left:50%; margin:-40px 0px 0px -60px; '>".
          "<div><img src='/pay_mod/payu/payu.jpg' width='120px' style='margin:5px 5px;'></div>".
          "</div>".
          "<script>
          setTimeout( subform, 100 );
          function subform(){ document.getElementById('PayUForm').submit(); }
          </script>";

	$option  = array( 'merchant' => __MERCHANT__, 
					  'secretkey' => __SECRETKEY__, 
					  'debug' => __DEBUG__ );

	if ( __LUURL__ != "" ) $option['luUrl'] = __LUURL__;
    if ( __BUTTON__ == true  ) $option['button'] = $button;
	return $option;
}


function cleaningGoods( $str )
{	
	if ($str == "" ) return array();
	$arr = explode("|", $str);
	foreach ($arr as $k=>$v) if ( empty($v) ) unset( $arr[$k] );

	return $arr;
}

/**
 * Function pay_go
 **/
function pay_go()
{ 
	
	global $gData, $gOptions;
			
	// Connect to Data 
	$gData = data_connect();	
			

$goods = $_SESSION['goods_info'];
$client = &$_SESSION['client_info'];

foreach ( $goods as $k => $v ) $goods[$k] = cleaningGoods( $v );

foreach ( $goods['name'] as $v) 
{
	$pinfo[] = "";
	$vat[] = ( $_SESSION['vat_cost'] == "" ) ? 0 : $_SESSION['vat_cost'];
}

# Create form for request
$forSend = array (
					'ORDER_REF' => $_SESSION['order_info']['code'], 
					'ORDER_PNAME' => $goods['name'], 
					'ORDER_PCODE' => $goods['code'],
					'ORDER_PINFO' => $pinfo,
					'ORDER_PRICE' => $goods['price'],
					'ORDER_QTY' => $goods['how'], 
					'ORDER_VAT' => $vat,
					'ORDER_SHIPPING' => $_SESSION["order_info"]['delivery_cost'], 
					'PRICES_CURRENCY' => __CURRENCY__,  
					'LANGUAGE' => __LANGUAGE__,
					'BILL_EMAIL' => $client['email'],
					'BILL_PHONE' => $client['CLIENT16'],
				  );

$pay = PayU::getInst()->setOptions( payuOpt() )->setData( $forSend )->LU();
return $pay;
}


/**
 * Function pay_get
 **/
function pay_get()
{ 
	global $gData, $gOptions;
	
	// Connect to Data 
	$gData = data_connect();	
	
	$payansewer = PayU::getInst()->setOptions( payuOpt() )->IPN();

	$order = $gData->GetArchiveOrder( (int)$_POST['REFNOEXT'] );	
		
		// Send e-mail for admin
		if ( $_POST['ORDERSTATUS'] !== "TEST" && $_POST['ORDERSTATUS'] !== 'COMPLETE' ) return $_POST['ORDERSTATUS']." - ". $payansewer;

		$tplm = new FastTemplate('./pay_mod/payu');
		$tplm->DefineTemplate(array('mail_message'	=>	'pay_try_mail.htm'));
		$tplm->Assign(array('ORDER_CODE'	=> htmlspecialchars($_POST['REFNOEXT']),
							'SUMA'		=> $_POST['IPN_TOTALGENERAL'],
							'CURR'		=> htmlspecialchars( $_POST['CURRENCY']  ),
							'SHOPNAMES'	=> htmlspecialchars($gOptions['attr_shop_name']),
					    	'SHOPURL'	=> htmlspecialchars($gOptions['attr_shop_url']),
					    	'PAYMETHOD' => htmlspecialchars( $_POST['PAYMETHOD']  ),
					    	'ORDERSTATUS' => htmlspecialchars( $_POST['ORDERSTATUS']  ),
					    	'REFNO' => htmlspecialchars( $_POST['REFNO']  ),
						   ));
		$tplm->Parse('MAIL', 'mail_message');
		$mailer = new Emailer(MAIL_SERVER);
		$mailer->SetCharset($gOptions['attr_admin_charset']);
		$mailer->SetTypeText();
		$all_message = iconv(SHOP_CHARSET, $gOptions['attr_admin_charset'], $tplm->Fetch('MAIL'));
		$subject = substr($all_message, strpos($all_message, 'Message_subject:')+16, strpos($all_message, 'Message_content:')-16);
		$message = substr($all_message, strpos($all_message, 'Message_content:')+16);		
		$mailer->AddMessage($message);
		$mailer->BuildMessage();
		$mailer->Send( $gOptions['attr_admin_email'], $gOptions['attr_shop_email'],	 ltrim($subject, " "));	
		return $payansewer;
}
?>