<?php
/**
 * CubeCart v6
 * ========================================
 * CubeCart is a registered trade mark of CubeCart Limited
 * Copyright CubeCart Limited 2014. All rights reserved.
 * UK Private Limited Company No. 5323904
 * ========================================
 * Web:   http://www.cubecart.com
 * Email:  sales@devellion.com
 * License:  GPL-2.0 http://opensource.org/licenses/GPL-2.0
 */
class Gateway {
	private $_config;
	private $_module;
	private $_basket;
	private $_result_message;
	private $_url;
	private $_path;
	private $_supported_currency = array('AUD', 'USD', 'NZD', 'SGD', 'EUR', 'GBP', 'CAD', 'HKD');

	public function __construct($module = false, $basket = false) {
		$this->_db		=& $GLOBALS['db'];
		$this->_module	= $module;
		$this->_basket  =& $GLOBALS['cart']->basket;
		$this->_url	    = $this->_module['testMode'] ? 'test-api.pin.net.au' : 'api.pin.net.au';
		$this->_path	= '/1/charges';
	}

	##################################################

	public function transfer() {
		$transfer	= array(
			'action'	=> currentPage(),
			'method'	=> 'post',
			'target'	=> '_self',
			'submit'	=> 'manual',
		);
		return $transfer;
	}

	##################################################

	public function repeatVariables() {
		return (isset($hidden)) ? $hidden : false;
	}

	public function fixedVariables() {
		$hidden['gateway']	= basename(dirname(__FILE__));
		return (isset($hidden)) ? $hidden : false;
	}

	public function call() {
		return false;
	}

	public function process() {
	
		$order			= Order::getInstance();
		$cart_order_id  = $this->_basket['cart_order_id'];
		$order_summary  = $order->getSummary($cart_order_id);
		$cardToken 		= (string) $_POST['card_token'];

		$defaultCurrency = $GLOBALS['config']->get('config','default_currency');

		if(!in_array($defaultCurrency, $this->_supported_currency)){
			$this->_result_message = 'Store default currency ('.$defaultCurrency.') is not supported by this payment gateway module.';
			return false;
		}

		$chargeData = array(
			'email'       => trim($this->_basket['billing_address']['email']),
			'description' => $cart_order_id,
			'amount'      => $this->amountToCents($this->_basket['total']),
			'ip_address'  => get_ip_address(),
			'currency'    => $defaultCurrency,
			'card_token'  => $cardToken,
		);

		//$baseUnit = $this->amountToCents($this->_basket['total']);
		//echo '<pre>'; var_dump($this->_basket['total']); echo '</pre>';
		//echo '<pre>'; var_dump($baseUnit); echo '</pre>';exit;

		$request = new Request($this->_url, $this->_path);
		$request->customOption(CURLOPT_FAILONERROR, false);
		$request->authenticate($this->_module['apiKey'], '');
		$request->setSSL();
		$request->setData($chargeData);
		$response = $request->send();
		$responseData = json_decode($response);

		//echo '<pre>'; var_dump($this->_basket); echo '</pre>';

		if($response && isset($responseData->response)){
			$transData['trans_id'] = $responseData->response->token;
			$transData['amount']   = $this->centsToAmount($responseData->response->amount);

			$status	= 'Approved';
			$order->orderStatus(Order::ORDER_PROCESS, $cart_order_id);
			$order->paymentStatus(Order::PAYMENT_SUCCESS, $cart_order_id);
			$this->_result_message = 'Payment Successful';
			$transData['notes']    = 'Payment Successful';
		}else if($response && isset($responseData->error) && $responseData->error == 'card_declined'){
			$status	= 'Declined';
			$order->orderStatus(Order::ORDER_PENDING, $cart_order_id);
			$order->paymentStatus(Order::PAYMENT_DECLINE, $cart_order_id);
			$this->_result_message = $responseData->error_description;
			$transData['notes']    = $responseData->error_description;
		}else if($response && isset($responseData->error)){
			$status	= 'Error';
			$this->_result_message = $responseData->error_description;
			$transData['notes']    = $responseData->error_description;
			$order->orderStatus(Order::ORDER_PENDING, $cart_order_id);
		}else{
			$status	= 'Error';
			$this->_result_message = 'Unknown Error';
			$transData['notes']    = 'Unknown Error';
			$order->orderStatus(Order::ORDER_PENDING, $cart_order_id);
		}

		$transData['status']		= $status;
		$transData['customer_id']	= $order_summary['customer_id'];
		$transData['gateway']		= 'Pin Payments';
		$order->logTransaction($transData);

		if($status == 'Approved') {
			httpredir(currentPage(array('_g', 'type', 'cmd', 'module'), array('_a' => 'complete')));
		}
	}

	public function form() {
		
		## Process transaction
		if (isset($_POST['card_token'])) {
			$return	= $this->process();
		}

		// Display payment result message
		if (!empty($this->_result_message))	{
			$GLOBALS['gui']->setError($this->_result_message);
		}

		// Pass the public api key and mode to the template
		$GLOBALS['smarty']->assign('APIKEYPUB', $this->_module['apiKeyPub']);
		$GLOBALS['smarty']->assign('PINMODE', $this->_module['testMode'] ? 'test' : 'live');
		
		//Show Expire Months
		$selectedMonth	= (isset($_POST['expirationMonth'])) ? $_POST['expirationMonth'] : date('m');
		for($i = 1; $i <= 12; ++$i) {
			$val = sprintf('%02d',$i);
			$smarty_data['card']['months'][]	= array(
				'selected'	=> ($val == $selectedMonth) ? 'selected="selected"' : '',
				'value'		=> $val,
				'display'	=> $this->formatMonth($val),
			);
		}

		## Show Expire Years
		$thisYear = date("Y");
		$maxYear = $thisYear + 10;
		$selectedYear = isset($_POST['expirationYear']) ? $_POST['expirationYear'] : ($thisYear + 2);
		for($i = $thisYear; $i <= $maxYear; ++$i) {
			$smarty_data['card']['years'][]	= array(
				'selected'	=> ($i == $selectedYear) ? 'selected="selected"' : '',
				'value'		=> $i,
			);
		}
		$GLOBALS['smarty']->assign('CARD', $smarty_data['card']);
		
		$smarty_data['customer'] = array(
			'name'       => isset($_POST['name']) ? $_POST['name'] : $this->_basket['billing_address']['first_name'] .' '.$this->_basket['billing_address']['last_name'],
			'add1'		 => isset($_POST['addr1']) ? $_POST['addr1'] : $this->_basket['billing_address']['line1'],
			'add2'		 => isset($_POST['addr2']) ? $_POST['addr2'] : $this->_basket['billing_address']['line2'],
			'city'		 => isset($_POST['city']) ? $_POST['city'] : $this->_basket['billing_address']['town'],
			'state'		 => isset($_POST['state']) ? $_POST['state'] : $this->_basket['billing_address']['state'],
			'postcode'   => isset($_POST['postcode']) ? $_POST['postcode'] : $this->_basket['billing_address']['postcode']
		);
		
		$GLOBALS['smarty']->assign('CUSTOMER', $smarty_data['customer']);
		
		## Country list
		$countries = $GLOBALS['db']->select('CubeCart_geo_country', false, false, array('name' => 'ASC'));
		if ($countries) {
			$currentIso = isset($_POST['country']) ? $_POST['country'] : $this->_basket['billing_address']['country_iso'];
			foreach ($countries as $country) {
				$country['selected']	= ($country['iso'] == $currentIso) ? 'selected="selected"' : '';
				$smarty_data['countries'][]	= $country;
			}
			$GLOBALS['smarty']->assign('COUNTRIES', $smarty_data['countries']);
		}
		
		## Check for custom template for module in skin folder
		$file_name = 'form.tpl';
		$form_file = $GLOBALS['gui']->getCustomModuleSkin('gateway', dirname(__FILE__), $file_name);
		$GLOBALS['gui']->changeTemplateDir($form_file);
		$ret = $GLOBALS['smarty']->fetch($file_name);
		$GLOBALS['gui']->changeTemplateDir();
		return $ret;
	}

	##################################################

	private function amountToCents($amount) {
		$dollars = str_replace('$', '', $amount);
		$cents = bcmul($dollars, 100);
		return $cents;
	}

	private function centsToAmount($cents) {
		return number_format(($cents / 100), 2, '.', ' ');
	}

	private function formatMonth($val) {
		return $val." - ".strftime("%b", mktime(0,0,0,$val,1 ,2009));
	}

}