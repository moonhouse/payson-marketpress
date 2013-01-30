<?php
/**
 * @package Marketpress_Payment_Payson
 * @version 0.2
 */
/*
Plugin Name: Payson for Marketpress
Plugin URI: http://dpg.se/wordpress/payson
Description: Enables purchases via Payson in Marketpress.
Author: David Hall
Version: 0.2
Author URI: http://dpg.se/
*/

add_action('init', 'dpgse_payson_gateway_init');
add_action('mp_load_gateway_plugins', 'register_payson_gateway');
add_action('mp_order_shipped','dpgse_payson_shipment_process',10,1);

function dpgse_payson_shipment_process($order) {
if($order->mp_payment_info['gateway_public_name']=='Payson' && $order->mp_payment_info['method']=='INVOICE') {
print '
<div id="mp-payson-invoice" class="postbox">
        <h3 class="hndle"><span>Payson Invoice</span> - <span class="description">Payson needs to be notified about the shipment in order to issue the invoice</span></h3>
        <div class="inside">
					<p>
'.$order->mp_payment_info['token'].'
					</p>
				</div>
      </div>';
      $post_url = 'https://api.payson.se/1.0/PaymentUpdate/';
      $param_str = 'action=SHIPORDER&token='.$order->mp_payment_info['token'];
       print "Would like to post $param_str to $post_url.";

       $payson_gw = new MP_Gateway_Payson;
       $payson_gw->on_creation();
       

       $response = $payson_gw->payson_api_request($param_str, $post_url);
       print_r($response);
      // need to derive the token
//	print "SHIPORDER";	
}
}

function dpgse_payson_gateway_init() {
	$plugin_dir = basename(dirname(__FILE__));
	load_plugin_textdomain('dpgse-marketpress-payson', false,  $plugin_dir . '/languages' );
}

function register_payson_gateway() {

class MP_Gateway_Payson extends MP_Gateway_API {

  //private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
  var $plugin_name = 'payson';
  
  //name of your gateway, for the admin side.
  var $admin_name = '';
  
  //public name of your gateway, for lists and such.
  var $public_name = '';

  //url for an image for your checkout method. Displayed on checkout form if set
  var $method_img_url = 'https://www.payson.se/sites/all/files/images/external/payson.png';
  
  //url for an submit button image for your checkout method. Displayed on checkout form if set
  var $method_button_img_url = 'https://www.payson.se/sites/all/files/images/external/payson_betala_btn.png';

  //whether or not ssl is needed for checkout page
  var $force_ssl = false;
  
  //always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
  var $ipn_url;

	//whether if this is the only enabled gateway it can skip the payment_form step
  //var $skip_form = false;

  //credit card vars
  var $API_Username, $API_Password, $SandboxFlag, $returnURL, $cancelURL, $API_Endpoint, $version, $currencyCode, $locale;
    
  /****** Below are the public methods you may overwrite via a plugin ******/
  
  /**
   * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
   */
  function on_creation() {
    global $mp;
    $settings = get_option('mp_settings');
    
    //set names here to be able to translate
    $this->admin_name = __('Payson', 'dpgse-marketpress-payson');
    $this->public_name = __('Payson', 'dpgse-marketpress-payson');
       
    if ( isset( $settings['gateways']['payson'] ) ) {
    
    
      $this->currencyCode = $settings['gateways']['payson']['currency'];
      $this->API_UserID = $settings['gateways']['payson']['sid'];
      $this->API_Password = $settings['gateways']['payson']['secret_word'];
      $this->SandboxFlag  = $settings['gateways']['payson']['mode'];
      $this->localeCode  = $settings['gateways']['payson']['locale'];
      $this->API_Email = $settings['gateways']['payson']['email'];
      $this->memo = $settings['gateways']['payson']['memo'];
      $this->guarantee = $settings['gateways']['payson']['guarantee'];
      $this->feesPayer = $settings['gateways']['payson']['feesPayer'];
      $this->useInvoice = $settings['gateways']['payson']['invoice'] == 'YES';
      $this->skip_form = !$this->useInvoice;
    }
  }

  /**
   * Return fields you need to add to the top of the payment screen, like your credit card info fields
   *
   * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function payment_form($cart, $shipping_info) {
    global $mp;
    $content = '<h2>'.__('Select payment by invoice or card/bank transfer.', 'dpgse-marketpress-payson').'</h2>';
    $content .= '<input type="radio" name="payment_type" value="creditcard"> '.__('Credit card/bank transfer', 'dpgse-marketpress-payson').'<br>';
    $content .= '<input type="radio" name="payment_type" value="invoice"> '.__('Invoice', 'dpgse-marketpress-payson').'<br>';
	return $content;
  }
  
  /**
   * Use this to process any fields you added. Use the $_REQUEST global,
   *  and be sure to save it to both the $_SESSION and usermeta if logged in.
   *  DO NOT save credit card details to usermeta as it's not PCI compliant.
   *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
   *  it will redirect to the next step.
   *
   * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function process_payment_form($cart, $shipping_info) {
    global $mp;
    
    $mp->generate_order_id();
  }
  
  /**
   * Return the chosen payment details here for final confirmation. You probably don't need
   *  to post anything in the form as it should be in your $_SESSION var already.
   *
   * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function confirm_payment_form($cart, $shipping_info) {
    global $mp;
  }

  /**
   * Use this to do the final payment. Create the order then process the payment. If
   *  you know the payment is successful right away go ahead and change the order status
   *  as well.
   *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
   *  it will redirect to the next step.
   *
   * @param array $cart. Contains the cart contents for the current blog, global cart if $mp->global_cart is true
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function process_payment($cart, $shipping_info) {
    global $mp, $current_user;;
    
    $timestamp = time();
    $settings = get_option('mp_settings');

    if ($this->SandboxFlag == 'live' || !$this->SandboxFlag == 'sandbox') {
    	$post_url = "https://api.payson.se/1.0/Pay/";
    	$redirect_url = "https://www.payson.se/paySecure/?token=";	    
    } else {
    	$post_url = "https://test-api.payson.se/1.0/Pay/";
    	$redirect_url = "https://test-www.payson.se/paysecure/?token=";	    	    
    }
  
    $order_id = $mp->generate_order_id();
 		   
    $params = array();
       
	$params['returnUrl']=mp_checkout_step_url('confirmation');
	$params['cancelUrl']= mp_checkout_step_url('checkout');
	$params['memo']=$this->memo;
	$params['ipnNotificationUrl']= $this->ipn_url;
	$params['localeCode']= $this->localeCode;
	$params['currencyCode']= $this->currencyCode;
    $params['trackingId'] = $order_id;
    $params['guaranteeOffered'] = $this->guarantee;
   	$params['feesPayer'] = $this->feesPayer;     
	
	$names = explode(' ',$shipping_info['name'],2);

	$params['senderEmail']=$shipping_info['email'];
	$params['senderFirstName']=$names[0];
	$params['senderLastName']=$names[1];
	
	// Use invoice
	if($this->useInvoice) {
			$params['fundingList.fundingConstraint(0).constraint'] = 'INVOICE';
			$params['feesPayer'] = 'PRIMARYRECEIVER';
	}
	
   

    
    $totals = array();
    $counter = 0;
    
  //  $params["id_type"] = 1;
    
    foreach ($cart as $product_id => $variations) {
      foreach ($variations as $variation => $data) {
	      $totals[] = $mp->before_tax_price($data['price']) * $data['quantity'];

	      $suffix = "{$counter}";

	      $sku = empty($data['SKU']) ? $product_id : $data['SKU'];
	
		$params['orderItemList.orderItem('.$suffix.').description']=$data['name'];
		$params['orderItemList.orderItem('.$suffix.').sku']=$sku;
		$params['orderItemList.orderItem('.$suffix.').quantity']=$data['quantity'];
		$params['orderItemList.orderItem('.$suffix.').unitPrice']=$mp->before_tax_price($data['price']);
		$params['orderItemList.orderItem('.$suffix.').taxPercentage']=$settings['tax']['rate'];

		      $counter++;
      }
    }
    
    $total = array_sum($totals);
    
    if ( $coupon = $mp->coupon_value($mp->get_coupon_code(), $total) ) {
      $total = $coupon['new_total'];
    }

    //shipping line
    if ( ($shipping_price = $mp->shipping_price()) !== false ) {
      $total = $total + $shipping_price;
			$params['sh_cost'] = $shipping_price;
			$suffix = "{$counter}";
		$params['orderItemList.orderItem('.$suffix.').description']='Frakt';
		$params['orderItemList.orderItem('.$suffix.').sku']='FRAKT';
		$params['orderItemList.orderItem('.$suffix.').quantity']=1;
		if($settings['tax']['tax_shipping'])
		{
			$params['orderItemList.orderItem('.$suffix.').taxPercentage']=$settings['tax']['rate'];	
			$params['orderItemList.orderItem('.$suffix.').unitPrice']=$mp->before_tax_price($shipping_price);
		}
		else 
		{
			$params['orderItemList.orderItem('.$suffix.').taxPercentage']=0;	
			$params['orderItemList.orderItem('.$suffix.').unitPrice']=$shipping_price;	
		}

    }
    
    //tax line
    if ( ($tax_price = $mp->tax_price()) !== false ) {
      $total = $total + $tax_price;
    }
    
    
    $params['receiverList.receiver(0).email']=$this->API_Email;
    $params['receiverList.receiver(0).amount']=$total;
   

    
    
    $param_list = array();
    
    foreach ($params as $k => $v) {
      $param_list[] = "{$k}=".rawurlencode($v);
    }
    
    $param_str = implode('&', $param_list);
    
    //setup transients for ipn in case checkout doesn't redirect (ipn should come within 12 hrs!)
	set_transient('mp_order_'. $order_id . '_cart', $cart, 60*60*12);
	set_transient('mp_order_'. $order_id . '_shipping', $shipping_info, 60*60*12);
	set_transient('mp_order_'. $order_id . '_userid', $current_user->ID, 60*60*12);
    
    $response = $this->payson_api_request($param_str, $post_url);
   
   	$redirect_url .= $response['TOKEN'];

    if ($this->SandboxFlag == 'sandbox' || $this->SandboxFlag == 'test-api') {
    print "<h2>Data sent to Payson</h2><PRE>";
    print_r($params);
    print "</PRE><h3>Serialised as POST body</h3>";
    print $param_str;
    print "<h2>Response from Payson</h2><PRE>";
    print_r($response);
    print "</PRE>";
    print "<p>Would like to redirect to <a href=\"$redirect_url\">$redirect_url</a></p>";
	print "<h2>Settings</h2><PRE>";
	print_r($settings);
	print "</PRE>";
    }
	else {
	if($response['responseEnvelope_ack']=='SUCCESS')
    	wp_redirect($redirect_url);	
    else
    	print "Error occurred.";
	} 
    
    exit(0);
  }
  
  /**
   * Filters the order confirmation email message body. You may want to append something to
   *  the message. Optional
   *
   * Don't forget to return!
   */
  function order_confirmation_email($msg, $order) {
    return $msg;
  }
  
  /**
   * Return any html you want to show on the confirmation screen after checkout. This
   *  should be a payment details box and message.
   *
   * Don't forget to return!
   */
  function order_confirmation_msg($content, $order) {
    global $mp;
    if ($order->post_status == 'order_received') {
      $content .= '<p>' . sprintf(__('Your payment via Payson for this order totaling %s is not yet complete. Here is the latest status:', 'dpgse-marketpress-payson'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
      $statuses = $order->mp_payment_info['status'];
      
      krsort($statuses); //sort with latest status at the top
      $status = reset($statuses);
      $timestamp = key($statuses);
      $content .= '<p><strong>' . date(get_option('date_format') . ' - ' . get_option('time_format'), $timestamp) . ':</strong> ' . htmlentities($status) . '</p>';
    } else {
      $content .= '<p>' . sprintf(__('Your payment via Payson for this order totaling %s is complete. The transaction number is <strong>%s</strong>.', 'dpgse-marketpress-payson'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total']), $order->mp_payment_info['transaction_id']) . '</p>';
    }
    return $content;
  }
  
  /**
   * Runs before page load incase you need to run any scripts before loading the success message page
   */
	function order_confirmation($order) {
		global $mp;
	   	$token = $_REQUEST['TOKEN'];
	   	 if ($this->SandboxFlag == 'sandbox' || $this->SandboxFlag == 'live') {
	   	$response = $this->payson_api_request("token=".$_GET['TOKEN'], 'https://api.payson.se/1.0/PaymentDetails/');
	   	} else {
			   	$response = $this->payson_api_request("token=".$_GET['TOKEN'], 'https://test-api.payson.se/1.0/PaymentDetails/');   	
	   	}
	   	
		$timestamp = time();
		$status = __('The order has been received', 'dpgse-marketpress-payson');
      switch ($response['status']) {

					case 'CREATED':
	          $status = __('Created - The payment request was received and has been created in Payson\'s system. Funds will be transferred once approval is received', 'dpgse-marketpress-payson');
	          $paid = false;
						break;

					case 'PENDING':
						$status = __('Pending - The sender has a pending transaction. A guarantee payment in progress has status pending. Please check guaranteeStatus for further details.', 'dpgse-marketpress-payson');
						if($response['invoiceStatus']=='ORDERCREATED') {
							$status = __('Order created - Payson will send invoice once your order has been shipped.', 'dpgse-marketpress-payson');	
						}
	          $paid = false;
						break;

					case 'PROCESSING':
						$status = __('Processing - The payment is in progress, check again later.', 'dpgse-marketpress-payson');
	          $paid = false;
						break;

					case 'COMPLETED':
						$status = __('Completed - The sender\'s transaction has completed.', 'dpgse-marketpress-payson');
	          $paid = true;
	          $payment_info['gateway_public_name'] = $this->public_name;
      	$payment_info['transaction_id'] = $_POST['purchaseId'];  
      	$payment_info['method'] = $_POST['type'];
        $payment_info['total'] = $_POST['receiverList_receiver(0)_amount'];
        $payment_info['currency'] = $_POST['currencyCode'];
						break;

					case 'CREDITED':
						$status = __('Credited - The sender\'s transaction has been credited..', 'dpgse-marketpress-payson');
	          $paid = false;
						break;
						
					case 'INCOMPLETE':
	          $status = __('Incomplete - Some transfers succeeded and some failed for a parallel payment.', 'dpgse-marketpress-payson');
	          $mp->cart_checkout_error( __('Incomplete - Some transfers succeeded and some failed for a parallel payment.', 'mp') . $error );
	          $paid = false;
						break;
						
				case 'ERROR':
	          $status = __('Error - The payment failed and all attempted transfers failed or all completed transfers were successfully reversed.', 'dpgse-marketpress-payson');
	          $mp->cart_checkout_error( __('Error - The payment failed and all attempted transfers failed or all completed transfers were successfully reversed.', 'mp') . $error );
	          $paid = false;
						break;
						
			case 'EXPIRED':
	          $status = __('Expired - A payment requiring approval was not executed within 3 hours.', 'dpgse-marketpress-payson');
	          $mp->cart_checkout_error( __('Expired - A payment requiring approval was not executed within 3 hours.', 'mp') . $error );
	          $paid = false;
						break;
						
			case 'REVERSALERROR':
	          $status = __('Reversal error - One or more transfers failed when attempting to reverse a payment.', 'dpgse-marketpress-payson');
	          $mp->cart_checkout_error( __('Reversal error - One or more transfers failed when attempting to reverse a payment.', 'mp') . $error );
	          $paid = false;
						break;
						
			case 'ABORTED':
	          $status = __('Aborted - The payment was aborted before any money were transferred.', 'dpgse-marketpress-payson');
	          $mp->cart_checkout_error( __('Aborted - The payment was aborted before any money were transferred.', 'mp') . $error );
	          $paid = false;
						break;

					default:
						// case: various error cases
						$status = $payment_status;
						$paid = false;
						}

      
		$payment_info['gateway_public_name'] = $this->public_name;
		$payment_info['gateway_private_name'] = $this->admin_name;
		
      	$payment_info['status'][$timestamp] = $status;
      			
      	$payment_info['total'] = $response['receiverList_receiver(0)_amount'];
      	$payment_info['currency'] = $this->currencyCode;
      	$payment_info['transaction_id'] = $response['purchaseId'];  
      	$payment_info['method'] = $response['type'];
      	$payment_info['token'] = $token;
    
 if ($this->SandboxFlag == 'sandbox' || $this->SandboxFlag == 'test-api') {
 	print "<h2>Get from Payson</h2><pre>";
    print_r($_REQUEST);
    print "</pre><h2>API request to Payson returned info</h2><pre>";
    print_r($response);
    print "</pre><h2>MP Shipping info</h2><pre>";
    print_r($_SESSION['mp_shipping_info']);
    print "</pre><h2>Cart contents</h2><pre>";
    print_r($mp->get_cart_contents());
    print "</pre><h2>Calculated payment info</h2><pre>";
    print_r($payment_info);
    print "</pre><h2>Paid</h2><pre>";
    print_r($paid);
    print "</pre>";
    
    //die("Argh");
    }
     if ($mp->get_order($response['trackingId'])) {
        $order = $mp->get_order($response['trackingId']);
	    update_post_meta($order->ID, 'mp_payment_info', $payment_info);
    	/*
    	$order->mp_payment_info['gateway_public_name'];
		$order->mp_payment_info['method'];
		$order->mp_payment_info['transaction_id'];
		$order->mp_payment_info['total']
		$order->mp_payment_info['currency'];
 		*/
   
	    $mp->update_order_payment_status($order_id, $response['status'], $paid);
	} else {
      	$mp->create_order($response['trackingId'], $mp->get_cart_contents(), $_SESSION['mp_shipping_info'], $payment_info, $paid);
	}


	}
    
  
  /**
   * Echo a settings meta box with whatever settings you need for you gateway.
   *  Form field names should be prefixed with mp[gateways][plugin_name], like "mp[gateways][plugin_name][mysetting]".
   *  You can access saved settings via $settings array.
   */
  function gateway_settings_box($settings) {
    global $mp;
    
    $settings = get_option('mp_settings');
    
    ?>
    <div id="mp_payson" class="postbox">
      <h3 class='handle'><span><?php _e('Payson Settings', 'dpgse-marketpress-payson'); ?></span></h3>
      <div class="inside">
        <span class="description"><?php _e('Resell your inventory via Payson.se.', 'dpgse-marketpress-payson') ?></span>
        <table class="form-table">
				  <tr>
				    <th scope="row"><?php _e('Mode', 'dpgse-marketpress-payson') ?></th>
				    <td>
			        <p>
			          <select name="mp[gateways][payson][mode]">
			            <option value="test-api" <?php selected($settings['gateways']['payson']['mode'], 'test-api') ?>><?php _e('Test API', 'dpgse-marketpress-payson') ?></option>
			            <option value="sandbox" <?php selected($settings['gateways']['payson']['mode'], 'sandbox') ?>><?php _e('Sandbox', 'dpgse-marketpress-payson') ?></option>
			            <option value="live" <?php selected($settings['gateways']['payson']['mode'], 'live') ?>><?php _e('Live', 'dpgse-marketpress-payson') ?></option>
			          </select>
			        </p>
				    </td>
				  </tr>
  <tr>
				    <th scope="row"><?php _e('Payson Recipient', 'dpgse-marketpress-payson') ?></th>
				    <td>
			        				      <p>
								<label><?php _e('Payson user (E-mail)', 'dpgse-marketpress-payson') ?><br />
								  <input value="<?php echo esc_attr($settings['gateways']['payson']['email']); ?>" size="30" name="mp[gateways][payson][email]" type="text" />
								</label>
				      </p>
						
				    </td>
				  </tr>				  
				  <tr>
				    <th scope="row"><?php _e('Payson API Credentials', 'dpgse-marketpress-payson') ?></th>
				    <td>
			        <span class="description"><?php print sprintf(__('You must login to Payson vendor dashboard to obtain the agent ID and password. <a target="_blank" href="%s">Instructions &raquo;</a>', 'dpgse-marketpress-payson'), "https://www.payson.se/account/agent/"); ?></span>
				      <p>
								<label><?php _e('API User ID', 'dpgse-marketpress-payson') ?><br />
								  <input value="<?php echo esc_attr($settings['gateways']['payson']['sid']); ?>" size="30" name="mp[gateways][payson][sid]" type="text" />
								</label>
				      </p>
				      <p>
								<label><?php _e('API Password', 'dpgse-marketpress-payson') ?><br />
								  <input value="<?php echo esc_attr($settings['gateways']['payson']['secret_word']); ?>" size="30" name="mp[gateways][payson][secret_word]" type="text" />
								</label>
				      </p>
						
				    </td>
				  </tr>
				    <tr>
				    <th scope="row"><?php _e('Memo', 'dpgse-marketpress-payson') ?></th>
				    <td>
			        				      <p>
								<label><?php _e('Description of items the customer is purchasing. Maximum 200 characters.', 'dpgse-marketpress-payson') ?><br />
								  <input value="<?php echo esc_attr($settings['gateways']['payson']['memo']); ?>" size="30" name="mp[gateways][payson][memo]" type="text" />
								</label>
				      </p>
						
				    </td>
				  </tr>	
          <tr valign="top">
	        <th scope="row"><?php _e('Payson Currency', 'dpgse-marketpress-payson') ?></th>
	        <td>
	          <span class="description"><?php _e('Selecting a currency other than that used for your store may cause problems at checkout.', 'dpgse-marketpress-payson'); ?></span><br />
          	<select name="mp[gateways][payson][currency]">
	          <?php
	          $sel_currency = ($settings['gateways']['payson']['currency']) ? $settings['gateways']['payson']['currency'] : $settings['currency'];
	          $currencies = array(
							"EUR" => 'EUR - Euro',
							"SEK" => 'SEK - Swedish Krona'
								          );

	          foreach ($currencies as $k => $v) {
	              echo '		<option value="' . $k . '"' . ($k == $sel_currency ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
	          }
	          ?>
	          </select>
	        </td>
	        </tr>

	                  <tr valign="top">
	        <th scope="row"><?php _e('Offer Payson Guarantee', 'dpgse-marketpress-payson') ?></th>
	        <td>
          	<select name="mp[gateways][payson][guarantee]">
	          <?php
	          $sel_guarantee = ($settings['gateways']['payson']['guarantee']) ? $settings['gateways']['payson']['guarantee'] : $settings['guarantee'];
	          $guarantee_options = array(
							"OPTIONAL" => 'Optional',
							"REQUIRED" => 'Required',
							"NO" => 'No'
								          );

	          foreach ($guarantee_options as $k => $v) {
	              echo '		<option value="' . $k . '"' . ($k == $sel_guarantee ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
	          }
	          ?>
	          </select>
	        </td>
	        </tr>
	        
	        	                  <tr valign="top">
	        <th scope="row"><?php _e('Offer invoice as payment method', 'dpgse-marketpress-payson') ?></th>
	        <td>
          	<select name="mp[gateways][payson][invoice]">
	          <?php
	          $sel_invoice = ($settings['gateways']['payson']['invoice']) ? $settings['gateways']['payson']['invoice'] : $settings['invoice'];
	          $invoice_options = array(
							"YES" => 'Yes',
							"NO" => 'No'
								          );

	          foreach ($invoice_options as $k => $v) {
	              echo '		<option value="' . $k . '"' . ($k == $sel_invoice ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
	          }
	          ?>
	          </select>
	        </td>
	        </tr>
	        
	        	                  <tr valign="top">
	        <th scope="row"><?php _e('Payson fee payed by', 'dpgse-marketpress-payson') ?></th>
	        <td>
          	<select name="mp[gateways][payson][feesPayer]">
	          <?php
	          $sel_feesPayer = ($settings['gateways']['payson']['feesPayer']) ? $settings['gateways']['payson']['feesPayer'] : $settings['feesPayer'];
	          $feesPayer_options = array(
							"EACHRECEIVER" => 'Receiver (Merchant)',
							"SENDER" => 'Sender (Customer)'
								          );

	          foreach ($feesPayer_options as $k => $v) {
	              echo '		<option value="' . $k . '"' . ($k == $sel_feesPayer ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
	          }
	          ?>
	          </select>
	        </td>
	        </tr>

	                  <tr valign="top">
	        <th scope="row"><?php _e('Language used', 'dpgse-marketpress-payson') ?></th>
	        <td>
          	<select name="mp[gateways][payson][locale]">
	          <?php
	          $sel_locale = ($settings['gateways']['payson']['locale']) ? $settings['gateways']['payson']['locale'] : $settings['locale'];
	          $locales = array(
							"SV" => 'Swedish',
							"EN" => 'English',
							"FI" => 'Finnish'
								          );

	          foreach ($locales as $k => $v) {
	              echo '		<option value="' . $k . '"' . ($k == $sel_locale ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
	          }
	          ?>
	          </select>
	        </td>
	        </tr>
        </table>
      </div>
    </div>
    <?php
  }
  
  /**
   * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
   *  array. Don't forget to return!
   */
  function process_gateway_settings($settings) {
    return $settings;
  }
  
  function payson_api_request($param_str, $url) {
		global $mp;


		$args['user-agent'] = "MarketPress/{$mp->version}: http://premium.wpmudev.org/project/e-commerce | Payson Payment Plugin/{$mp->version}";
		$args['body'] = $param_str;
		$args['timeout'] = 30;
		$args['sslverify'] = false;
    
    $args['headers']['PAYSON-SECURITY-USERID'] = $this->API_UserID;
    $args['headers']['PAYSON-SECURITY-PASSWORD'] = $this->API_Password;
    //$args['headers']['PAYSON-APPLICATION-ID'] = '';	   
		    

		    
    //use built in WP http class to work with most server setups
    $response = wp_remote_post($url, $args);

		if (is_array($response) && isset($response['body'])) {
      parse_str($response['body'], $final_response);
      return $final_response;
    } else {
			return false;
    }
	}

  
  /**
   * IPN and payment return
   */
   
  function process_ipn_return() {
    global $mp;
    $settings = get_option('mp_settings');

    if (isset($_POST['status'])) {

	    $url = 'https://api.payson.se/1.0/Validate/';
	         if ($this->SandboxFlag == 'test-api') {
	    $url = 'https://test-api.payson.se/1.0/Validate/';
	    }
	     $param_list = array();
    
    foreach ($_POST as $k => $v) {
      $param_list[] = "{$k}=".rawurlencode($v);
    }
    
    $param_str = implode('&', $param_list);
    
			$response = $this->payson_api_request($param_str, $url);

			if (!$response=='VERIFIED') {
				header('HTTP/1.0 403 Forbidden');
				exit('We were unable to authenticate the request');
      }


		  $timestamp = time();
      $order_id = $_POST['trackingId'];
			$payment_status = $_POST['status'];

			if ($payment_status) {
			
	      //setup status
	      switch ($payment_status) {

					case 'CREATED':
	          $status = __('Created - The payment request was received and has been created in Payson\'s system. Funds will be transferred once approval is received', 'dpgse-marketpress-payson');
	          $paid = false;
						break;

					case 'PENDING':
						$status = __('Pending - The sender has a pending transaction. A guarantee payment in progress has status pending. Please check guaranteeStatus for further details.', 'dpgse-marketpress-payson');
	          $paid = false;
						break;

					case 'PROCESSING':
						$status = __('Processing - The payment is in progress, check again later.', 'dpgse-marketpress-payson');
	          $paid = false;
						break;

					case 'COMPLETED':
						$status = __('Completed - The sender\'s transaction has completed.', 'dpgse-marketpress-payson');
	          $paid = true;
	          $payment_info['gateway_public_name'] = $this->public_name;
      	$payment_info['transaction_id'] = $_POST['purchaseId'];  
      	$payment_info['method'] = $_POST['type'];
        $payment_info['total'] = $_POST['receiverList_receiver(0)_amount'];
        $payment_info['currency'] = $_POST['currencyCode'];
						break;

					case 'CREDITED':
						$status = __('Credited - The sender\'s transaction has been credited..', 'dpgse-marketpress-payson');
	          $paid = false;
						break;
						
					case 'INCOMPLETE':
	          $status = __('Incomplete - Some transfers succeeded and some failed for a parallel payment.', 'dpgse-marketpress-payson');
	          $paid = false;
						break;
						
				case 'ERROR':
	          $status = __('Error - The payment failed and all attempted transfers failed or all completed transfers were successfully reversed.', 'dpgse-marketpress-payson');
	          $paid = false;
						break;
						
			case 'EXPIRED':
	          $status = __('Expired - A payment requiring approval was not executed within 3 hours.', 'dpgse-marketpress-payson');
	          $paid = false;
						break;
						
			case 'REVERSALERROR':
	          $status = __('Reversal error - One or more transfers failed when attempting to reverse a payment.', 'dpgse-marketpress-payson');
	          $paid = false;
						break;
						
			case 'ABORTED':
	          $status = __('Aborted - The payment was aborted before any money were transferred.', 'dpgse-marketpress-payson');
	          $paid = false;
						break;

					default:
						// case: various error cases
						$status = $payment_status;
						$paid = false;
				}

	      //status's are stored as an array with unix timestamp as key
			  $payment_info['status'][$timestamp] = $status;
			  

	      if ($mp->get_order($order_id)) {
	        $mp->update_order_payment_status($order_id, $status, $paid);
	      } else {
	        $cart = get_transient('mp_order_' . $order_id . '_cart');
		  	$shipping_info = get_transient('mp_order_' . $order_id . '_shipping');
			$user_id = get_transient('mp_order_' . $order_id . '_userid');
				  
	        $success = $mp->create_order($order_id, $cart, $shipping_info, $payment_info, $paid, $user_id);

			//if successful delete transients
	        if ($success) {
	        	delete_transient('mp_order_' . $order_id . '_cart');
        		delete_transient('mp_order_' . $order_id . '_shipping');
				delete_transient('mp_order_' . $order_id . '_userid');
	        }

	      } 
	      
			}
			
      //if we get this far return success so ipns don't get resent
      header('HTTP/1.0 200 OK');
			die('RECEIVED');
    } else {
      header('HTTP/1.0 403 Forbidden');
			exit('Invalid request');
		}
  }
}

mp_register_gateway_plugin( 'MP_Gateway_Payson', 'payson', __('Payson', 'dpgse-marketpress-payson') );

}

?>