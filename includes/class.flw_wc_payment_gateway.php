<?php

  if( ! defined( 'ABSPATH' ) ) { exit; }
  
  define("BASEPATH", 1);
  
  require_once( FLW_WC_DIR_PATH . 'flutterwave-rave-php-sdk/lib/rave.php' );
  require_once( FLW_WC_DIR_PATH . 'includes/eventHandler.php' );
      
  use Flutterwave\Rave;

  /**
   * Main Rave Gateway Class
   */
  class FLW_WC_Payment_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {

      $this->base_url = 'https://api.ravepay.co';
      $this->id = 'rave';
      $this->icon = plugins_url('assets/img/rave.png', FLW_WC_PLUGIN_FILE);
      $this->has_fields         = false;
      $this->method_title       = __( 'Rave', 'flw-payments' );
      $this->method_description = __( 'Rave allows you to accept payment from cards and bank accounts in multiple currencies. You can also accept payment offline via USSD and POS.', 'flw-payments' );
      $this->supports = array(
        'products',
      );

      $this->init_form_fields();
      $this->init_settings();

      $this->title        = $this->get_option( 'title' );
      $this->description  = $this->get_option( 'description' );
      $this->enabled      = $this->get_option( 'enabled' );
      $this->test_public_key   = $this->get_option( 'test_public_key' );
      $this->test_secret_key   = $this->get_option( 'test_secret_key' );
      $this->live_public_key   = $this->get_option( 'live_public_key' );
      $this->live_secret_key   = $this->get_option( 'live_secret_key' );
      $this->go_live      = $this->get_option( 'go_live' );
      $this->payment_options = $this->get_option( 'payment_options' );
      $this->payment_style = $this->get_option( 'payment_style' );
      // $this->country = $this->get_option( 'country' );
      // $this->modal_logo = $this->get_option( 'modal_logo' );

      // enable saved cards
      // $this->saved_cards = $this->get_option( 'saved_cards' ) === 'yes' ? true : false;

      // declare support for Woocommerce subscription
      $this->supports = array(
        'products',
        'tokenization',
        'subscriptions',
        'subscription_cancellation', 
        'subscription_suspension', 
        'subscription_reactivation',
        'subscription_amount_changes',
        'subscription_date_changes',
        'subscription_payment_method_change',
        'subscription_payment_method_change_customer',
        'subscription_payment_method_change_admin',
        'multiple_subscriptions',
      );

      add_action( 'admin_notices', array( $this, 'admin_notices' ) );
      add_action( 'woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
      add_action( 'woocommerce_api_flw_wc_payment_gateway', array($this, 'flw_verify_payment'));

      // Webhook listener/API hook
      add_action( 'woocommerce_api_flw_wc_payment_webhook', array($this, 'flw_rave_webhooks'));
      
      if ( is_admin() ) {
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      }

      $this->public_key   = $this->test_public_key;
      $this->secret_key   = $this->test_secret_key;
     

      if ( 'yes' === $this->go_live ) {
        // $this->base_url = 'https://api.ravepay.co';
        $this->public_key   = $this->live_public_key;
        $this->secret_key   = $this->live_secret_key;
     
      }

      $this->load_scripts();

    }

    /**
     * Initial gateway settings form fields
     *
     * @return void
     */
    public function init_form_fields() {

      $this->form_fields = array(

        'enabled' => array(
          'title'       => __( 'Enable/Disable', 'flw-payments' ),
          'label'       => __( 'Enable Rave Payment Gateway', 'flw-payments' ),
          'type'        => 'checkbox',
          'description' => __( 'Enable Rave Payment Gateway as a payment option on the checkout page', 'flw-payments' ),
          'default'     => 'no',
          'desc_tip'    => true
        ),
        'go_live' => array(
          'title'       => __( 'Mode', 'flw-payments' ),
          'label'       => __( 'Live mode', 'flw-payments' ),
          'type'        => 'checkbox',
          'description' => __( 'Check this box if you\'re using your live keys.', 'flw-payments' ),
          'default'     => 'no',
          'desc_tip'    => true
        ),
        'webhook' => array(
          'title'       => __( 'Webhook Instruction', 'flw-payments' ),
          'type'        => 'hidden',
          'description' => __( 'Please copy this webhook URL and paste on the webhook section on your dashboard <strong style="color: red"><pre><code>'.WC()->api_request_url('Flw_WC_Payment_Webhook').'</code></pre></strong> (<a href="https://rave.flutterwave.com/dashboard/settings/webhooks" target="_blank">Rave Account</a>)', 'flw-payments' ),
        ),
        'secret_hash' => array(
          'title'       => __( 'Enter Secret Hash', 'flw-payments' ),
          'type'        => 'text',
          'description' => __( 'Ensure that <b>SECRET HASH</b> is the same with the one on your Rave dashboard', 'flw-payments' ),
          'default'     => 'Rave-Secret-Hash'
        ),
        'title' => array(
          'title'       => __( 'Payment method title', 'flw-payments' ),
          'type'        => 'text',
          'description' => __( 'Optional', 'flw-payments' ),
          'default'     => 'Rave'
        ),
        'description' => array(
          'title'       => __( 'Payment method description', 'flw-payments' ),
          'type'        => 'text',
          'description' => __( 'Optional', 'flw-payments' ),
          'default'     => 'Powered by Flutterwave: Accepts Mastercard, Visa, Verve, Discover, AMEX, Diners Club and Union Pay.'
        ),
        'test_public_key' => array(
          'title'       => __( 'Rave Test Public Key', 'flw-payments' ),
          'type'        => 'text',
          // 'description' => __( 'Required! Enter your Rave test public key here', 'flw-payments' ),
          'default'     => ''
        ),
        'test_secret_key' => array(
          'title'       => __( 'Rave Test Secret Key', 'flw-payments' ),
          'type'        => 'text',
          // 'description' => __( 'Required! Enter your Rave test secret key here', 'flw-payments' ),
          'default'     => ''
        ),
        'live_public_key' => array(
          'title'       => __( 'Rave Live Public Key', 'flw-payments' ),
          'type'        => 'text',
          // 'description' => __( 'Required! Enter your Rave live public key here', 'flw-payments' ),
          'default'     => ''
        ),
        'live_secret_key' => array(
          'title'       => __( 'Rave Live Secret Key', 'flw-payments' ),
          'type'        => 'text',
          // 'description' => __( 'Required! Enter your Rave live secret key here', 'flw-payments' ),
          'default'     => ''
        ),
        'payment_style' => array(
          'title'       => __( 'Payment Style on checkout', 'flw-payments' ),
          'type'        => 'select',
          'description' => __( 'Optional - Choice of payment style to use. Either inline or redirect. (Default: inline)', 'flw-payments' ),
          'options'     => array(
            'inline' => esc_html_x( 'Popup(Keep payment experience on the website)', 'payment_style', 'flw-payments' ),
            'redirect'  => esc_html_x( 'Redirect',  'payment_style', 'flw-payments' ),
          ),
          'default'     => 'inline'
        ),
        'payment_options' => array(
          'title'       => __( 'Payment Options', 'flw-payments' ),
          'type'        => 'select',
          'description' => __( 'Optional - Choice of payment method to use. Card, Account etc.', 'flw-payments' ),
          'options'     => array(
            '' => esc_html_x( 'Default', 'payment_options', 'flw-payments' ),
            'card'  => esc_html_x( 'Card Only',  'payment_options', 'flw-payments' ),
            'account'  => esc_html_x( 'Account Only',  'payment_options', 'flw-payments' ),
            'ussd'  => esc_html_x( 'USSD Only',  'payment_options', 'flw-payments' ),
            'qr'  => esc_html_x( 'QR Only',  'payment_options', 'flw-payments' ),
            'mpesa'  => esc_html_x( 'Mpesa Only',  'payment_options', 'flw-payments' ),
            'mobilemoneyghana'  => esc_html_x( 'Ghana MM Only',  'payment_options', 'flw-payments' ),
          ),
          'default'     => ''
        ),

      );

    }

    /**
     * Process payment at checkout
     *
     * @return int $order_id
     */
    public function process_payment( $order_id ) {

      $order = wc_get_order( $order_id );
  
      return array(
        'result'   => 'success',
        'redirect' => $order->get_checkout_payment_url( true )
      );

    }

    
    /**
     * Handles admin notices
     *
     * @return void
     */
    public function admin_notices() {

      if ( 'no' == $this->enabled ) {
        return;
      }

      /**
       * Check if public key is provided
       */
      if ( ! $this->public_key || ! $this->secret_key ) {
        $mode = ('yes' === $this->go_live) ? 'live' : 'test';
        echo '<div class="error"><p>';
        echo sprintf(
          'Provide your '.$mode .' public key and secret key <a href="%s">here</a> to be able to use the Rave Payment Gateway plugin. If you don\'t have one, kindly sign up at <a href="https://rave.flutterwave.com" target="_blank>https://rave.flutterwave.com</a>, navigate to the settings page and click on API.',
           admin_url( 'admin.php?page=wc-settings&tab=checkout&section=rave' )
         );
        echo '</p></div>';
        return;
      }

    }

    /**
     * Checkout receipt page
     *
     * @return void
     */
    public function receipt_page( $order ) {

      $order = wc_get_order( $order );
      
      echo '<p>'.__( 'Thank you for your order, please click the <b>Make Payment</b> button below to make payment. You will be redirected to a secure page where you can enter you card details or bank account details. <b>Please, do not close your browser at any point in this process.</b>', 'flw-payments' ).'</p>';
      echo '<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">';
      echo __( 'Cancel order &amp; restore cart', 'flw-payments' ) . '</a> ';
      echo '<button class="button alt  wc-forward" id="flw-pay-now-button">Make Payment</button> ';
      

    }

    /**
     * Loads (enqueue) static files (js & css) for the checkout page
     *
     * @return void
     */
    public function load_scripts() {

      if ( ! is_checkout_pay_page() ) return;
      $p_key = $this->public_key;
      $payment_options = $this->payment_options;
       
      if( $this->payment_style == 'inline'){
        wp_enqueue_script( 'flwpbf_inline_js', $this->base_url . '/flwv3-pug/getpaidx/api/flwpbf-inline.js', array(), '1.0.0', true );
      }

      wp_enqueue_script( 'flw_js', plugins_url( 'assets/js/flw.js', FLW_WC_PLUGIN_FILE ), array( 'jquery' ), '1.0.0', true );

      if ( get_query_var( 'order-pay' ) ) {
        
        $order_key = urldecode( $_REQUEST['key'] );
        $order_id  = absint( get_query_var( 'order-pay' ) );
        $cb_url = WC()->api_request_url( 'FLW_WC_Payment_Gateway' ).'?rave_id='.$order_id;
       
        if( $this->payment_style == 'inline'){
          wp_enqueue_script( 'flwpbf_inline_js', $this->base_url . '/flwv3-pug/getpaidx/api/flwpbf-inline.js', array(), '1.0.0', true );
          $cb_url = WC()->api_request_url('FLW_WC_Payment_Gateway');
        }

        $order     = wc_get_order( $order_id );
        
        $txnref    = "WOOC_" . $order_id . '_' . time();
        $txnref    = filter_var($txnref, FILTER_SANITIZE_STRING);//sanitizr=e this field
       
        if (version_compare(WOOCOMMERCE_VERSION, '2.7.0', '>=')){
              $amount    = $order->get_total();
              $email     = $order->get_billing_email();
              $currency     = $order->get_currency();
              $main_order_key = $order->get_order_key();
        }else{
            $args = array(
                'name'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email'   => $order->get_billing_email(),
                'contact' => $order->get_billing_phone(),
            );
            $amount    = $order->get_total();
            $main_order_key = $order->get_order_key();
            $email     = $order->get_billing_email();
            $currency     = $order->get_order_currency();
        }
        
        // $amount    = $order->order_total;
        // $email     = $order->billing_email;
        // $currency     = $order->get_order_currency();
        
        //set the currency to route to their countries
        switch ($currency) {
            case 'KES':
              $this->country = 'KE';
              break;
            case 'GHS':
              $this->country = 'GH';
              break;
            case 'ZAR':
              $this->country = 'ZA';
              break;
            case 'TZS':
              $this->country = 'TZ';
              break;
            
            default:
              $this->country = 'NG';
              break;
        }
        
        $country  = $this->country;
        $payment_style  = $this->payment_style;

        if ( $main_order_key == $order_key ) {

          $payment_args = compact( 'amount', 'email', 'txnref', 'p_key', 'currency', 'country', 'payment_options','cb_url','payment_style');
          $payment_args['desc']   = filter_var($this->description, FILTER_SANITIZE_STRING);
          $payment_args['title']  = filter_var($this->title, FILTER_SANITIZE_STRING);
          // $payment_args['logo'] = filter_var($this->modal_logo, FILTER_SANITIZE_URL);
          $payment_args['firstname'] = $order->get_billing_first_name();
          $payment_args['lastname'] = $order->get_billing_last_name();
        }

        update_post_meta( $order_id, '_flw_payment_txn_ref', $txnref );

      }

      wp_localize_script( 'flw_js', 'flw_payment_args', $payment_args );


    }

    /**
     * Verify payment made on the checkout page
     *
     * @return void
     */
    public function flw_verify_payment() {
           
        $publicKey = $this->public_key; 
        $secretKey = $this->secret_key; 

        // if($this->go_live === 'yes'){
        //   $env = 'live';
        // }else{
        //   $env = 'staging';
        // }
        $overrideRef = true;
          
       if(isset($_GET['rave_id']) && urldecode( $_GET['rave_id'] )){
          $order_id = urldecode( $_GET['rave_id'] );
          
          if(!$order_id){
            $order_id = urldecode( $_GET['order_id'] );
          }
          $order = wc_get_order( $order_id );
          
          $redirectURL =  WC()->api_request_url( 'FLW_WC_Payment_Gateway' ).'?order_id='.$order_id;
         
          $ref = uniqid("WOOC_". $order_id."_".time()."_");
         
          $payment = new Rave($publicKey, $secretKey, $ref, $overrideRef);
          
          // if($this->modal_logo){
          //   $rave_m_logo = $this->modal_logo;
          // }

          //set variables
          $modal_desc = $this->description != '' ? filter_var($this->description, FILTER_SANITIZE_STRING) : "Payment for Order ID: $order_id on ". get_bloginfo('name');
          $modal_title = $this->title != '' ? filter_var($this->title, FILTER_SANITIZE_STRING) : get_bloginfo('name');
          
          // Make payment
          $payment
          ->eventHandler(new myEventHandler($order))
          ->setAmount($order->get_total())
          ->setPaymentOptions($this->payment_options) // value can be card, account or both
          ->setDescription($modal_desc)
          // ->setLogo($rave_m_logo)
          ->setTitle($modal_title)
          ->setCountry($this->country)
          ->setCurrency($order->get_order_currency())
          ->setEmail($order->get_billing_email())
          ->setFirstname($order->get_billing_first_name())
          ->setLastname($order->get_billing_last_name())
          ->setPhoneNumber($order->get_billing_phone())
          // ->setPayButtonText($postData['pay_button_text'])
          ->setRedirectUrl($redirectURL)
          // ->setMetaData(array('metaname' => 'SomeDataName', 'metavalue' => 'SomeValue')) // can be called multiple times. Uncomment this to add meta datas
          // ->setMetaData(array('metaname' => 'SomeOtherDataName', 'metavalue' => 'SomeOtherValue')) // can be called multiple times. Uncomment this to add meta datas
          ->initialize(); 
          die();
        }else{
          if(isset($_GET['cancelled']) && isset($_GET['order_id'])){
            if(!$order_id){
              $order_id = urldecode( $_GET['order_id'] );
            }
            $order = wc_get_order( $order_id );
            $redirectURL = $order->get_checkout_payment_url( true );
            header("Location: ".$redirectURL);
            die(); 
          }
         
          if ( isset( $_POST['txRef'] ) || isset($_GET['txref']) ) {
              $txn_ref = isset($_POST['txRef']) ? $_POST['txRef'] : urldecode($_GET['txref']);
              $o = explode('_', $txn_ref);
              $order_id = intval( $o[1] );
              $order = wc_get_order( $order_id );
              $payment = new Rave($publicKey, $secretKey, $txn_ref, $overrideRef);
          
              $payment->logger->notice('Payment completed. Now requerying payment.');
              
              $payment->eventHandler(new myEventHandler($order))->requeryTransaction(urldecode($txn_ref));
              
              $redirect_url = $this->get_return_url( $order );
              header("Location: ".$redirect_url);
              die(); 
          }else{
            $payment = new Rave($publicKey, $secretKey, $txn_ref, $overrideRef);
          
            $payment->logger->notice('Error with requerying payment.');
            
            $payment->eventHandler(new myEventHandler($order))->doNothing();
              die();
          }
      }
    }

    /**
	 * Process Webhook
	 */
    public function flw_rave_webhooks() {

      // Retrieve the request's body
      $body = @file_get_contents("php://input");

      // retrieve the signature sent in the request header's.
      $signature = (isset($_SERVER['HTTP_VERIF_HASH']) ? $_SERVER['HTTP_VERIF_HASH'] : '');

      /* It is a good idea to log all events received. Add code *
      * here to log the signature and body to db or file       */

      if (!$signature) {
          // only a post with rave signature header gets our attention
          exit();
      }

      // Store the same signature on your server as an env variable and check against what was sent in the headers
      $local_signature = $this->get_option('secret_hash');

      // confirm the event's signature
      if( $signature !== $local_signature ){
        // silently forget this ever happened
        exit();
      }
      sleep(10);

      http_response_code(200); // PHP 5.4 or greater
      // parse event (which is json string) as object
      // Give value to your customer but don't give any output
      // Remember that this is a call from rave's servers and 
      // Your customer is not seeing the response here at all
      $response = json_decode($body);
      if ($response->status == 'successful') {

        $getOrderId = explode('_', $response->txRef);
        $orderId = $getOrderId[1];
        // $order = wc_get_order( $orderId );
        $order = new WC_Order($orderId);

        if ($order->status == 'pending') {
          $order->update_status('processing');
          $order->add_order_note('Payment was successful on Rave and verified via webhook');
          $customer_note  = 'Thank you for your order.<br>';

          $order->add_order_note( $customer_note, 1 );
      
          wc_add_notice( $customer_note, 'notice' );
        }

        
        // $order->payment_complete($order->id);
        // $order->add_order_note('Payment was successful on Rave and verified via webhook');
        // $order->add_order_note('Flutterwave transaction reference: '.$response->flwRef); 
        // $customer_note  = 'Thank you for your order.<br>';
        // $customer_note .= 'Your payment was successful, we are now <strong>processing</strong> your order.';

        // $order->add_order_note( $customer_note, 1 );
    
        // wc_add_notice( $customer_note, 'notice' );
        // $this->flw_verify_payment();
      }
      exit();    

    }

    /**
	 * Save Customer Card Details
	 */
    public static function save_card_details( $rave_response, $user_id, $order_id ) {

      if ( isset( $rave_response->card->card_tokens[0]->embedtoken ) ) {
        $token_code = $rave_response->card->card_tokens[0]->embedtoken;
      } else {
        $token_code = '';
      }

      // save payment token to the order
      self::save_subscription_payment_token( $order_id, $token_code );
      // $save_card = get_post_meta( $order_id, '_wc_rave_save_card', true );

      // if ( isset( $rave_response->data->card ) && $user_id && self::saved_cards && $save_card && ! empty( $token_code ) ) {

      //   $last4 = $rave_response->data->card->last4digits;

      //   if ( 4 !== strlen( $rave_response->data->card->expiryyear ) ) {
      //     $exp_year 	= substr( date( 'Y' ), 0, 2 ) . $rave_response->data->card->expiryyear;
      //   } else {
      //     $exp_year 	= $rave_response->data->card->expiryyear;
      //   }

      //   $brand 		= $rave_response->data->card->brand;
      //   $exp_month 	= $rave_response->data->card->expirymonth;
      //   $token = new WC_Payment_Token_CC();
      //   $token->set_token( $token_code );
      //   $token->set_gateway_id( 'rave' );
      //   $token->set_card_type( $brand );
      //   $token->set_last4( $last4 );
      //   $token->set_expiry_month( $exp_month  );
      //   $token->set_expiry_year( $exp_year );
      //   $token->set_user_id( $user_id );
      //   $token->save();

      // }

      // delete_post_meta( $order_id, '_wc_rave_save_card' );
    }

    /**
	  * Save payment token to the order for automatic renewal for further subscription payment
	  */
    public function save_subscription_payment_token( $order_id, $payment_token ) {

      if ( ! function_exists ( 'wcs_order_contains_subscription' ) ) {
        return;
      }

      if ( WC_Subscriptions_Order::order_contains_subscription( $order_id ) && ! empty( $payment_token ) ) {

        // Also store it on the subscriptions being purchased or paid for in the order
        if ( function_exists( 'wcs_order_contains_subscription' ) && wcs_order_contains_subscription( $order_id ) ) {

          $subscriptions = wcs_get_subscriptions_for_order( $order_id );

        } elseif ( function_exists( 'wcs_order_contains_renewal' ) && wcs_order_contains_renewal( $order_id ) ) {

          $subscriptions = wcs_get_subscriptions_for_renewal_order( $order_id );

        } else {

          $subscriptions = array();

        }

        foreach ( $subscriptions as $subscription ) {

          $subscription_id = $subscription->get_id();

          update_post_meta( $subscription_id, '_rave_wc_token', $payment_token );

        }
      }
    }

  }
  
?>
