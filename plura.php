<?php

/*
* @author pluracoin.org
* @version $Id: plura.php 1 2018-08-24 15:03:42Z dave $
* @package VirtueMart
* @subpackage payment
* @license http://www.gnu.org/copyleft/gpl.html GNU/GPL, see LICENSE.php
* VirtueMart is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See /administrator/components/com_virtuemart/COPYRIGHT.php for copyright notices and details.
*
* http://virtuemart.org
*/

defined ('_JEXEC') or die('Restricted access');
if (!class_exists ('vmPSPlugin')) {
	require(VMPATH_PLUGINLIBS . DS . 'vmpsplugin.php');
}

class plgVmpaymentPlura extends vmPSPlugin {

	function __construct (& $subject, $config) {

		parent::__construct ($subject, $config);
		// unique filelanguage for all SKRILL methods
		$jlang = JFactory::getLanguage ();
		$jlang->load ('plg_vmpayment_plura', JPATH_ADMINISTRATOR, NULL, TRUE);
		$this->_loggable = TRUE;
		$this->_debug = TRUE;
		$this->tableFields = array_keys ($this->getTableSQLFields ());
		$this->_tablepkey = 'id'; //virtuemart_SKRILL_id';
		$this->_tableId = 'id'; //'virtuemart_SKRILL_id';

		$varsToPush = array(
			'plura_address' => array('', 'char'),
			'plura_viewkey' => array('', 'char'),
			'plura_api' => array('', 'char'),
			'plura_confirmations' => array('10', 'int')
			);

		$this->setConfigParameterable ($this->_configTableFieldName, $varsToPush);
	}

	public function getVmPluginCreateTableSQL () {

		return $this->createTableSQL ('Payment PLURA Table');
	}			

	function _getInternalData ($virtuemart_order_id, $order_number = '') {

		$db = JFactory::getDBO ();
		$q = 'SELECT * FROM `' . $this->_tablename . '` WHERE ';
		if ($order_number) {
			$q .= " `order_number` = '" . $order_number . "'";
		} else {
			$q .= ' `virtuemart_order_id` = ' . $virtuemart_order_id;
		}

		$db->setQuery ($q);
		if (!($paymentTable = $db->loadObject ())) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}
		return $paymentTable;
	}

	function _storeInternalData ($method, $mb_data, $virtuemart_order_id) {

		// get all know columns of the table
		$db = JFactory::getDBO ();
		$query = 'SHOW COLUMNS FROM `' . $this->_tablename . '` ';
		$db->setQuery ($query);
		$columns = $db->loadColumn (0);

		$post_msg = '';
		foreach ($mb_data as $key => $value) {
			$post_msg .= $key . "=" . $value . "<br />";
			$table_key = 'mb_' . $key;
			if (in_array ($table_key, $columns)) {
				$response_fields[$table_key] = $value;
			}
		}

		$response_fields['payment_name'] = $this->renderPluginName ($method);
		$response_fields['mbresponse_raw'] = $post_msg;
		$response_fields['order_number'] = $mb_data['transaction_id'];
		$response_fields['virtuemart_order_id'] = $virtuemart_order_id;
		$this->storePSPluginInternalData ($response_fields, 'virtuemart_order_id', TRUE);
	}	

	function getTableSQLFields () {

		$SQLfields = array('id'                     => 'int(11) UNSIGNED NOT NULL AUTO_INCREMENT',
		                   'virtuemart_order_id'    => 'int(1) UNSIGNED',
		                   'order_number'           => 'char(64)',		                   
		                   'payment_order_total'    => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',		                   
		                   'confirmations'          => 'int(11)',
		                   'payment_id'            => 'varchar(128)'
						);

		return $SQLfields;
	}

	function plgVmConfirmedOrder ($cart, $order) {

		if (!($method = $this->getVmPluginMethod ($order['details']['BT']->virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		$session = JFactory::getSession ();
		$return_context = $session->getId ();
		$this->logInfo ('plgVmConfirmedOrder order number: ' . $order['details']['BT']->order_number, 'message');

		if (!class_exists ('VirtueMartModelOrders')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
		}
		if (!class_exists ('VirtueMartModelCurrency')) {
			require(VMPATH_ADMIN . DS . 'models' . DS . 'currency.php');
		}

		$usrBT = $order['details']['BT'];
		//$address = ((isset($order['details']['ST'])) ? $order['details']['ST'] : $order['details']['BT']);

		if (!class_exists ('TableVendors')) {
			require(VMPATH_ADMIN . DS . 'tables' . DS . 'vendors.php');
		}
		$vendorModel = VmModel::getModel ('Vendor');
		$vendorModel->setId (1);
		$vendor = $vendorModel->getVendor ();
		$vendorModel->addImages ($vendor, 1);
		$this->getPaymentCurrency ($method);

		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .
			$method->payment_currency . '" ';
		$db = JFactory::getDBO ();
		$db->setQuery ($q);
		$currency_code_3 = $db->loadResult ();

		$totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total,$method->payment_currency);
		$cartCurrency = CurrencyDisplay::getInstance($cart->pricesCurrency);

		if ($totalInPaymentCurrency['value'] <= 0) {
			vmInfo (vmText::_ ('VMPAYMENT_PLURA_PAYMENT_AMOUNT_INCORRECT'));
			return FALSE;
		}
		
		$lang = JFactory::getLanguage ();
		$tag = substr ($lang->get ('tag'), 0, 2);		

		// Prepare data that should be stored in the database		
		$dbValues['order_number'] = $order['details']['BT']->order_number;			
		$dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
		$dbValues['payment_id'] = $this->getPaymentId();
		$this->storePSPluginInternalData ($dbValues);

		
		$html .= '<p style="text-align:center"><a href="pluracoin:'.$method->plura_address.'?amount='.$totalInPaymentCurrency['value'].'&payment_id='.$dbValues['payment_id'].'&label=Order_'.$order['details']['BT']->order_number.'" class="button">'.vmText::_ ('VMPAYMENT_PLURA_PAY').' '.$totalInPaymentCurrency['value'].' '.vmText::_ ('VMPAYMENT_PLURA_DESKTOP').'</a></p><p style="text-align:center">'.vmText::_ ('VMPAYMENT_PLURA_LATER').'</p>';

		$html .= '<p><table>
			<tr><td width="20%"><b>'.vmText::_ ('VMPAYMENT_PLURA_SENDTO').'</b></td><td width="80%"><textarea cols="80" rows="2">'.$method->plura_address.'</textarea></td></tr>
			<tr><td><b>'.vmText::_ ('VMPAYMENT_PLURA_PAYMENTID').'</b></td><td><textarea style="width:100%">'.$dbValues['payment_id'].'</textarea></td></tr>
			<tr><td><b>'.vmText::_ ('VMPAYMENT_PLURA_AMOUNT').'</b></td><td><b>'.$totalInPaymentCurrency['value'].' PLURA</b></td></tr>
		</table></p>';
		

		$cart = VirtueMartCart::getCart ();
		$cart->emptyCart ();

		vRequest::setVar ('html', $html);
	}

	function getPaymentId() {
  		return bin2hex(openssl_random_pseudo_bytes(32));
		}

	function plgVmgetPaymentCurrency ($virtuemart_paymentmethod_id, &$paymentCurrencyId) {

		if (!($method = $this->getVmPluginMethod ($virtuemart_paymentmethod_id))) {
			return NULL;
		} // Another method was selected, do nothing

		if (!$this->selectedThisElement ($method->payment_element)) {
			return FALSE;
		}

		$this->getPaymentCurrency ($method);
		$paymentCurrencyId = $method->payment_currency;
	}

	function plgVmOnPaymentResponseReceived (&$html) {
		//TODO
		return TRUE;
	}

	function plgVmOnUserPaymentCancel () {
		//TODO
		return TRUE;
	}

	function plgVmOnPaymentNotification () {		
		//TODO
		//$modelOrder->updateStatusForOneOrder ($virtuemart_order_id, $order, TRUE);
		// remove vmcart
		$this->emptyCart ($payment->user_session, $mb_data['transaction_id']);
	}

	function plgVmOnShowOrderBEPayment ($virtuemart_order_id, $payment_method_id) {

		if (!$this->selectedThisByMethodId ($payment_method_id)) {
			return NULL;
		} // Another method was selected, do nothing

		if (!($paymentTable = $this->_getInternalData ($virtuemart_order_id))) {
			// JError::raiseWarning(500, $db->getErrorMsg());
			return '';
		}

		$this->getPaymentCurrency ($paymentTable);
		$q = 'SELECT `currency_code_3` FROM `#__virtuemart_currencies` WHERE `virtuemart_currency_id`="' .
			$paymentTable->payment_currency . '" ';
		$db = JFactory::getDBO ();
		$db->setQuery ($q);
		$currency_code_3 = $db->loadResult ();
		$html = '<table class="adminlist table">' . "\n";
		$html .= $this->getHtmlHeaderBE ();
		$html .= $this->getHtmlRowBE ('PAYMENT_NAME', $paymentTable->payment_name);

		$code = "mb_";
		foreach ($paymentTable as $key => $value) {
			if (substr ($key, 0, strlen ($code)) == $code) {
				$html .= $this->getHtmlRowBE ($key, $value);
			}
		}
		$html .= '</table>' . "\n";
		return $html;
	}

	protected function checkConditions ($cart, $method, $cart_prices) {

		$this->convert_condition_amount($method);

		$address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

		$amount = $this->getCartAmount($cart_prices);
		$amount_cond = ($amount >= $method->min_amount AND $amount <= $method->max_amount
			OR
			($method->min_amount <= $amount AND ($method->max_amount == 0)));

		$countries = array();
		if (!empty($method->countries)) {
			if (!is_array ($method->countries)) {
				$countries[0] = $method->countries;
			} else {
				$countries = $method->countries;
			}
		}

		// probably did not gave his BT:ST address
		if (!is_array ($address)) {
			$address = array();
			$address['virtuemart_country_id'] = 0;
		}

		if (!isset($address['virtuemart_country_id'])) {
			$address['virtuemart_country_id'] = 0;
		}
		if (in_array ($address['virtuemart_country_id'], $countries) || count ($countries) == 0) {
			if ($amount_cond) {
				return TRUE;
			}
		}

		return FALSE;
	}


	/**
	 * We must reimplement this triggers for joomla 1.7
	 */

	/**
	 * Create the table for this plugin if it does not yet exist.
	 * This functions checks if the called plugin is active one.
	 * When yes it is calling the standard method to create the tables
	 *
	 * @author Valérie Isaksen
	 *
	 */
	function plgVmOnStoreInstallPaymentPluginTable ($jplugin_id) {

		return $this->onStoreInstallPluginTable ($jplugin_id);
	}

	/**
	 * This event is fired after the payment method has been selected. It can be used to store
	 * additional payment info in the cart.
	 *
	 * @author Max Milbers
	 * @author Valérie isaksen
	 *
	 * @param VirtueMartCart $cart: the actual cart
	 * @return null if the payment was not selected, true if the data is valid, error message if the data is not valid
	 *
	 */
	public function plgVmOnSelectCheckPayment (VirtueMartCart $cart,  &$msg) {

		return $this->OnSelectCheck ($cart);
	}

	/**
	 * plgVmDisplayListFEPayment
	 * This event is fired to display the pluginmethods in the cart (edit shipment/payment) for exampel
	 *
	 * @param object  $cart Cart object
	 * @param integer $selected ID of the method selected
	 * @return boolean True on success, false on failures, null when this plugin was not selected.
	 * On errors, JError::raiseWarning (or JError::raiseError) must be used to set a message.
	 *
	 * @author Valerie Isaksen
	 * @author Max Milbers
	 */
	public function plgVmDisplayListFEPayment (VirtueMartCart $cart, $selected = 0, &$htmlIn) {

		return $this->displayListFE ($cart, $selected, $htmlIn);
	}


	public function plgVmonSelectedCalculatePricePayment (VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) {

		return $this->onSelectedCalculatePrice ($cart, $cart_prices, $cart_prices_name);
	}

	/**
	 * plgVmOnCheckAutomaticSelectedPayment
	 * Checks how many plugins are available. If only one, the user will not have the choice. Enter edit_xxx page
	 * The plugin must check first if it is the correct type
	 *
	 * @author Valerie Isaksen
	 * @param VirtueMartCart cart: the cart object
	 * @return null if no plugin was found, 0 if more then one plugin was found,  virtuemart_xxx_id if only one plugin is found
	 *
	 */
	function plgVmOnCheckAutomaticSelectedPayment (VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter) {

		return $this->onCheckAutomaticSelected ($cart, $cart_prices, $paymentCounter);
	}

	/**
	 * This method is fired when showing the order details in the frontend.
	 * It displays the method-specific data.
	 *
	 * @param integer $order_id The order ID
	 * @return mixed Null for methods that aren't active, text (HTML) otherwise
	 * @author Max Milbers
	 * @author Valerie Isaksen
	 */
	public function plgVmOnShowOrderFEPayment ($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name) {

		$this->onShowOrderFE ($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
	}

	/**
	 * This event is fired during the checkout process. It can be used to validate the
	 * method data as entered by the user.
	 *
	 * @return boolean True when the data was valid, false otherwise. If the plugin is not activated, it should return null.
	 * @author Max Milbers

	public function plgVmOnCheckoutCheckDataPayment($psType, VirtueMartCart $cart) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing when priting an Order
	 * It displays the the payment method-specific data.
	 *
	 * @param integer $_virtuemart_order_id The order ID
	 * @param integer $method_id  method used for this order
	 * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
	 * @author Valerie Isaksen
	 */
	function plgVmonShowOrderPrintPayment ($order_number, $method_id) {

		return $this->onShowOrderPrint ($order_number, $method_id);
	}

	/**
	 * Save updated order data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not activated.

	public function plgVmOnUpdateOrderPayment(  $_formData) {
	return null;
	}
	 */
	/**
	 * Save updated orderline data to the method specific table
	 *
	 * @param array $_formData Form data
	 * @return mixed, True on success, false on failures (the rest of the save-process will be
	 * skipped!), or null when this method is not actived.

	public function plgVmOnUpdateOrderLine(  $_formData) {
	return null;
	}
	 */
	/**
	 * plgVmOnEditOrderLineBE
	 * This method is fired when editing the order line details in the backend.
	 * It can be used to add line specific package codes
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise

	public function plgVmOnEditOrderLineBE(  $_orderId, $_lineId) {
	return null;
	}
	 */

	/**
	 * This method is fired when showing the order details in the frontend, for every orderline.
	 * It can be used to display line specific package codes, e.g. with a link to external tracking and
	 * tracing systems
	 *
	 * @param integer $_orderId The order ID
	 * @param integer $_lineId
	 * @return mixed Null for method that aren't active, text (HTML) otherwise

	public function plgVmOnShowOrderLineFE(  $_orderId, $_lineId) {
	return null;
	}
	 */
	function plgVmDeclarePluginParamsPaymentVM3( &$data) {
		return $this->declarePluginParams('payment', $data);
	}

	function plgVmSetOnTablePluginParamsPayment ($name, $id, &$table) {

		return $this->setOnTablePluginParams ($name, $id, $table);
	}

} // end of class plgVmpaymentSkrill

// No closing tag
