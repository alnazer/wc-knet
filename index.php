<?php
/*
*Plugin Name: Payment Gateway for KNET
*Plugin URI: https://github.com/alnazer/woocommerce-payment-kent-v2
*Description: The new update of the K-Net payment gateway via woocommerce paymemt.
*Author: alnazer
*Version: 2.3.2
*Author URI: https://github.com/alnazer
*Text Domain: wc-knet
* Domain Path: /languages
*/
/**
 * @package wc KNET woocommerce
*/
defined( 'ABSPATH' ) || exit;
define("WC_PAYMENT_KNET_TABLE","wc_knet_transactions");
define("WC_PAYMENT_KNET_DV_VERSION","1.1");
define("WC_PAYMENT_STATUS_SUCCESS","success");
define("WC_PAYMENT_STATUS_FAIL","fail");
define("WC_PAYMENT_STATUS_NEW","new");

    // include transactions table
    require_once plugin_dir_path(__FILE__)."wc_knet_payment_transactions.php";
    require_once plugin_dir_path(__FILE__)."wc_knet_payment_trans_grid.php";
    require_once plugin_dir_path(__FILE__)."classes/SimpleXLSXGen.php";
    // initialization payment class when plugin load
    $WC_Payment_KNET_CLASS_NAME = "WC_Payment_Gateway_KNET";
    add_action( 'plugins_loaded', 'init_wc_knet_payment',0);
 
    function init_wc_knet_payment()
    {
        

        if ( !class_exists( 'WC_Payment_Gateway' ) ) {return;}
        WC_KNET_PAYMENT_Plugin::get_instance();
        // create table in data base
        if ( get_site_option('wc_knet_db_version') != WC_PAYMENT_KNET_DV_VERSION ) {
           
            create_transactions_db_table();
        }
        /**
         *  KNET Gateway.
         *
         * Provides a VISA Payment Gateway.
         *
         * @class       WC_Gateway_KNET
         * @extends     WC_Payment_Gateway
         * @version     2.2.0
         * @package     WooCommerce/Classes/Payment
     */
        class WC_Payment_Gateway_KNET extends WC_Payment_Gateway {


            private $exchange;
            private $currency;
            private $tranportal_id;
            private $password;
            private $resource_key;
            private $GatewayUrl='https://kpaytest.com.kw/';
            private $paymentUrl = 'id={id}&password={password}&action=1&langid={lang}&currencycode=414&amt={amt}&responseURL={responseURL}&errorURL={errorURL}&trackid={trackid}&udf1={udf1}&udf2={udf2}&udf3={udf3}&udf4={udf4}&udf5={udf5}';
            private $name = "";
            private $email = "";
            private $mobile = "";
            private $trackId;
            private $responseURL;
            private $errorURL;
            public $is_test;
            public $lang = "AR";
            private $html_allow = array( 'h2' => array("class"=>array(),"style"=>array()),"span"=>array("style"=>array()), 'table' => array("class"=>array()),'tr' => array(), 'th' => array(), 'td' => array(),'b' => array(),'br' => array(), 'img' => array(
                "src"=>array(),
                "width"=>array(),
                "alt"=>array()
            ) );
            /**
             * @var string
             */


            function __construct()
            {

                $this->init_gateway();
                $this->init_form_fields();
                $this->init_settings();
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');
                $this->currency = get_option('woocommerce_currency');
                $this->exchange = $this->get_option('exchange');
                $this->tranportal_id = $this->get_option('tranportal_id');
                $this->password = $this->get_option('password');
                $this->resource_key = $this->get_option('resource_key');
                $this->lang = $this->get_option('lang');
                $this->is_test = $this->get_option('is_test');
                if($this->is_test == "no")
                {
                    $this->GatewayUrl = "https://kpay.com.kw/";
                }
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	            add_filter('woocommerce_thankyou_order_received_text', [$this,'wc_woo_change_order_received_text'] );
	            add_filter( 'woocommerce_endpoint_order-received_title', [$this,'wc_thank_you_title']);

                // add details to tahnkyou page
	            add_action("woocommerce_order_details_before_order_table", [$this,'wc_knet_details'],10,1);
	            // add details to email
                add_action("woocommerce_email_after_order_table", [$this,'wc_knet_email_details'],10,3);
                add_filter('woocommerce_available_payment_gateways', [$this,'wc_conditional_payment_gateways'], 10, 1);

            }
            /**
             * initialization gateway call default data
             * like id,icon
             */
            public function init_gateway()
            {
                $this->id                 = 'wc_knet';
                $this->icon               =  plugins_url( 'assets/knet-logo.png' , __FILE__ );
                $this->method_title       = __('KNET', 'wc-knet');
                $this->method_description = __( 'intgration with KNET php raw.', 'woocommerce' );
                $this->has_fields         = true;
            }
            /**
             * Define Form Option fields
             * - Options for payment like 'title', 'description', 'tranportal_id', 'password', 'resource_key'
             **/
            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __( 'Enable/Disable', 'woocommerce' ),
                        'type' => 'checkbox',
                        'label' => __( 'Enable KNET Payment', 'woocommerce' ),
                        'default' => 'yes'
                    ),
                    'is_test' => array(
                        'title'       => 'Test mode',
                        'label'       => 'Enable Test Mode',
                        'type'        => 'checkbox',
                        'description' => __("Place the payment gateway in test mode using test. only this user roles [Shop manager,Administrator] can test payment","wc-knet"),
                        'default'     => 'no',
                        'desc_tip'    => false,
                    ),
                    'title' => array(
                        'title' => __( 'Title', 'woocommerce' ),
                        'type' => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default' => __( 'KNET', 'woocommerce' ),
                        'desc_tip'      => true,
                    ),
                    'description' => array(
                        'title' => __( 'Description', 'woocommerce' ),
                        'type' => 'textarea',
                        'default' => ''
                    ),
                    'exchange' => [
                        'title' => __('Currency exchange rate ', 'wc-knet'),
                        'type' => 'number',
                        'custom_attributes' => array( 'step' => 'any', 'min' => '0' ),
                        'description' => __('It is the rate of multiplying the currency account in the event that the base currency of the store is not the Kuwaiti dinar', 'wc-knet')." ".__('KWD = exchange rate * amount(USD)', 'wc-knet'),
                        'default' => 1,
                        'desc_tip' => false,
                    ],
                    'tranportal_id' => array(
                        'title' => __( 'Tranportal Id', 'wc-knet' ),
                        'type' => 'text',
                        'label' => __( 'Necessary data requested from the bank ', 'wc-knet' ),
                        'default' => ''
                    ),
                    'password' => array(
                        'title' => __( 'Transportal Password', 'wc-knet' ),
                        'type' => 'password',
                        'description' => __( 'Necessary data requested from the bank ', 'wc-knet' ),
                        'default' => '',
                        'desc_tip'      => false,
                    ),
                    'resource_key' => array(
                        'title' => __( 'Terminal Resource Key', 'wc-knet' ),
                        'type' => 'password',
                        'description' => __( 'Necessary data requested from the bank', 'wc-knet' ),
                        'default' => '',
                        'desc_tip'      => false,
                    ),
                    'lang' => [
                        'title' => __('Language', 'cbk_knet'),
                        'type' => 'select',
                        'description' => __('payment page lang', 'cbk_knet'),
                        'default' => 'AR',
                        'options' => [
                            'AR' => __('Arabic'),
                            'EN' => __('English'),
                        ],
                        'desc_tip' => false,
                    ],

                );
            }
            /**
             * Admin Panel Options
             * - Options for bits like 'title', 'description', 'alias'
             **/
            public function admin_options(){
                echo wp_kses('<h3>'.__('KNET', 'wc-knet').'</h3>',["h3"=>[]]);
                echo wp_kses('<p>'.__('KNET', 'wc-knet').'</p>',["p"=>[]]);
                echo wp_kses('<table class="form-table">',["table"=>["class"=>[]]]);
                $this->generate_settings_html();
                echo wp_kses('</table>',["table"=>[]]);

            }
            /**
             * Process payment
             * return array
             * status,pay url
             * 1- get request data (pay url)
             * 2- Mark as on-hold (we're awaiting the cheque)
             * 3- Remove cart
             * 4- Return thankyou redirect
             * 5- or failed pay
             * @param $order_id
             * @return array
             */
            function process_payment( $order_id )
            {

                global $woocommerce;
                $order = new WC_Order( $order_id );
                if(!$order->get_id())
                {
                    wc_add_notice( __("Order not found", "wc-knet"), 'error' );
                    return array(
                        'result'    =>   'error',
                        'redirect'  =>   $this->get_return_url( $order )
                    );
                }
                //get request data (pay url)
                $request = $this->request($order);

                // Mark as on-hold (we're awaiting the cheque)
                $order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));

                // Remove cart
                //$woocommerce->cart->empty_cart();

                if(!empty($request))
                {
                    // Return thankyou redirect
                    return array(
                        'result' => 'success',
                        'redirect' => $request['url']
                    );
                }
                else
                {
                    $order->add_order_note( __('Payment error:', 'woothemes') .  __("Knet can't get data", 'wc-knet'),'error' );
                    $order->update_status('failed');
                    return array(
                        'result' => 'error',
                        'redirect' => $this->get_return_url( $order )
                    );
                }
            }

            /**
             * return pay url to rediredt to knet gateway web site
             * return array
             * @param $order
             * @return array
             */
            public function request($order)
            {
                $this->formatUrlParames($order);
                $param =    $this->encryptAES($this->paymentUrl,$this->resource_key)."&tranportalId=".$this->tranportal_id."&responseURL=".$this->responseURL."&errorURL=".$this->errorURL;
                $payURL=    $this->GatewayUrl."kpg/PaymentHTTP.htm?param=paymentInit"."&trandata=".$param;
                return [
                    'status' => 'success',
                    'url' =>$payURL,
                    'payment_id' => $this->trackId
                ];
            }

            /**
             * prepare pay url parames to kent
             * this update pay url var
             * @param $order
             */
            private function formatUrlParames($order)
            {
                $user_id = $order->get_user_id();
                if($user_id)
                {
                    $user_info = $order->get_user();
                    $this->name = $user_info->user_login;
                    $this->email = $user_info->user_email;
                    $this->mobile = $user_info->user_phone;
                }
                $this->errorURL = get_site_url()."/index.php?knetresponce=success";
                $this->responseURL = get_site_url()."/index.php?knetresponce=success";

                $this->trackId = time().mt_rand(1000,100000);
                $replace_array = array();
                $replace_array['{id}'] = $this->tranportal_id;
                $replace_array['{password}'] = $this->password;
                $replace_array['{amt}'] = $this->getTotalAmount($order);
                $replace_array['{trackid}'] = $this->trackId;
                $replace_array['{responseURL}'] = $this->responseURL;
                $replace_array['{errorURL}'] = $this->errorURL;
                $replace_array['{lang}'] = $this->lang;
                $replace_array['{udf1}'] = $order->get_id();
                $replace_array['{udf2}'] = $this->name;
                $replace_array['{udf3}'] =$this->email;
                $replace_array['{udf4}'] = $this->mobile;
                $replace_array['{udf5}'] = '';
                $this->paymentUrl = str_replace(array_keys($replace_array),array_values($replace_array),$this->paymentUrl);
            }

            /**
             * update order after responce Done from knet
             * return string
             * url for order view
             */
            public function updateOrder()
            {
                // defince rexpoce data
                $resnopseData = $this->responce();
                
                if($resnopseData)
                {
                    
                    $order_id =  $resnopseData["udf1"];
                    $status = $resnopseData["status"];
                    $tranid = $resnopseData["tranid"];
                    $ref = $resnopseData["ref"];
                    $paymentid = $resnopseData["paymentid"];
                    $trackid = $resnopseData["trackid"];
                    $result = $resnopseData["result"];
                    $ErrorText = $resnopseData["ErrorText"];
                    $Error = $resnopseData["Error"];
                    $order = new WC_Order( $order_id );
                    $transation_data = [
                        "payment_id"=> $paymentid,
                        "track_id"=>$trackid,
                        "tran_id"=>$tranid,
                        "ref_id"=>$ref,
                        "result"=>$result,
                        'status' => ($result == "CAPTURED") ? WC_PAYMENT_STATUS_SUCCESS : WC_PAYMENT_STATUS_FAIL,
                        "amount" => $resnopseData["ammount"],
                        "data" => $resnopseData["data"],
                        'error'=>$ErrorText,
                    ];

                    if(!$order->get_id())
                    {
                        wc_add_notice( __("Order not found", "wc-knet"), 'error' );
                        return $order->get_view_order_url();
                    }
                    elseif(isset($status) && $status == "success")
                    {
                            // insert transation
                            do_action("wc_knet_create_new_transation",$order,$transation_data);
                            switch ($result) {
                                case 'CAPTURED':
                                    $order->payment_complete();
                                    $order->update_status('completed');
                                    break;
                                case 'NOT CAPTURED':
                                    $order->update_status('refunded');
                                    break;
                                case 'CANCELED':
                                    $order->update_status('cancelled');
                                    break;                     
                                default:
                                    $order->update_status('refunded');
                                    break;
                            }
                            $knetInfomation = "";
                            $knetInfomation.= __('Result', 'wc-knet')."           : $result\n";
                            $knetInfomation.= __('Payment id', 'wc-knet')."       : $paymentid\n";
                            $knetInfomation.= __('track id', 'wc-knet')."         : $trackid\n";
                            $knetInfomation.= __('Transaction id', 'wc-knet')."   : $tranid\n";
                            $knetInfomation.= __('Refrance id', 'wc-knet')."      : $ref\n";
                            $order->add_order_note($knetInfomation);

                    }
                    elseif(isset($status) && $status == "error")
                    {
                            // insert transation
                            do_action("wc_knet_create_new_transation",$order,$transation_data);
                            $knetInfomation = "";
                            $knetInfomation.= __('Result', 'wc-knet')."           : $result\n";
                            $knetInfomation.= __('Payment id', 'wc-knet')."       : $paymentid\n";
                            $knetInfomation.= __('track id', 'wc-knet')."         : $trackid\n";
                            $knetInfomation.= __('Transaction id', 'wc-knet')."   : $tranid\n";
                            $knetInfomation.= __('Refrance id', 'wc-knet')."      : $ref\n";
                            $knetInfomation.= __('Error', 'wc-knet')."            : $Error\n";
                            $knetInfomation.= __('Error Message', 'wc-knet')."    : $ErrorText\n";
                            $order->add_order_note($knetInfomation);
                            $order->update_status('refunded');


                    }
                }
                return $this->get_return_url($order);
            }
            /**
             * get responce came from kney payment 
             * return array()
             */
            private function responce()
            {
                $ResErrorText   =   (isset($_REQUEST['ErrorText'])) ? sanitize_text_field($_REQUEST['ErrorText']) : null; 	  	//Error Text/message
                $ResPaymentId   =   (isset($_REQUEST['paymentid'])) ? sanitize_text_field($_REQUEST['paymentid']) : null; 		//Payment Id
                $ResTrackID     =   (isset($_REQUEST['trackid']))   ? sanitize_text_field($_REQUEST['trackid']) : null;       	//Merchant Track ID
                $ResErrorNo     =   (isset($_REQUEST['Error']))     ? sanitize_text_field($_REQUEST['Error']) : null;           //Error Number
                //$ResResult      =   (isset($_REQUEST['result']))    ? sanitize_text_field($_REQUEST['result']) : null;           //Transaction Result
                $ResPosdate     =   (isset($_REQUEST['postdate']))  ? sanitize_text_field($_REQUEST['postdate']) : null;         //Postdate
                $ResTranId      =   (isset($_REQUEST['tranid']))    ? sanitize_text_field($_REQUEST['tranid']) : null;         //Transaction ID
                $ResAuth        =   (isset($_REQUEST['auth']))  ? sanitize_text_field($_REQUEST['auth']) : null;               //Auth Code		
                $ResAVR         =   (isset($_REQUEST['avr']))   ? sanitize_text_field($_REQUEST['avr']) : null;                //TRANSACTION avr					
                $ResRef         =   (isset($_REQUEST['ref']))   ? sanitize_text_field($_REQUEST['ref']) : null;                //Reference Number also called Seq Number
                $ResAmount      =   (isset($_REQUEST['amt']))   ? sanitize_text_field($_REQUEST['amt']) : null;             //Transaction Amount
                $Resudf1        =   (isset($_REQUEST['udf1']))  ? sanitize_text_field($_REQUEST['udf1']) : null;              //UDF1
                $Resudf2        =   (isset($_REQUEST['udf2']))  ? sanitize_text_field($_REQUEST['udf2']) : null;               //UDF2
                $Resudf3        =   (isset($_REQUEST['udf3']))  ? sanitize_text_field($_REQUEST['udf3']) : null;                //UDF3
                $Resudf4        =   (isset($_REQUEST['udf4']))  ? sanitize_text_field($_REQUEST['udf4']) : null;    //UDF4
                $Resudf5        =   (isset($_REQUEST['udf5']))  ? sanitize_text_field($_REQUEST['udf5']) : null;    //UDF5
                if($ResErrorText==null && $ResErrorNo==null && $ResPaymentId != null)
                {
                    // success
                    $ResTranData= (isset($_REQUEST['trandata'])) ? sanitize_text_field($_REQUEST['trandata']) : null;
                    $decrytedData=$this->decrypt($ResTranData,$this->resource_key);
                    parse_str($decrytedData, $output);
                
                    if($ResTranData !=null)
                    {
                        $result['status'] = 'success';
                        $result['paymentid'] = $ResPaymentId;
                        $result['trackid'] = $ResTrackID;
                        $result['tranid'] = $output['tranid'];
                        $result['ref'] = $output['ref'];
                        $result['result'] = $output['result'];
                        $result['postdate'] = $output['postdate'];
                        $result['auth'] = $output['auth'];
                        $result['avr'] = $output['avr'];                 //TRANSACTION avr					
                        $result['ammount'] = $output['amt'];              //Transaction Amount
                        $result['udf1'] = $output['udf1'];               //UDF1
                        $result['udf2'] = $output['udf2'];               //UDF2
                        $result['udf3'] = $output['udf3'];               //UDF3
                        $result['udf4'] = $output['udf4'];               //UDF4
                        $result['udf5'] = $output['udf5']; 
                        //Decryption logice starts
                        $result['data']=$decrytedData;
                        $result['ErrorText']= $ResErrorText; 	  	//Error 
                        $result['Error'] = $ResErrorNo;  
                    }else{
                        $result['status'] = 'error';
                        $result['paymentid'] = $ResPaymentId;
                        $result['trackid'] = $ResTrackID;
                        $result['tranid'] = $ResTranId;
                        $result['ref'] = $ResRef;
                        $result['result'] =  'error';
                        $result['data']= sanitize_text_field(http_build_query($_REQUEST));
                        $result['postdate'] = $ResPosdate;
                        $result['auth'] = $ResAuth;
                        $result['avr'] = $ResAVR;                 //TRANSACTION avr					
                        $result['ammount'] = $ResAmount;              //Transaction Amount
                        $result['udf1'] = $Resudf1;               //UDF1
                        $result['udf2'] = $Resudf2;               //UDF2
                        $result['udf3'] = $Resudf3;               //UDF3
                        $result['udf4'] = $Resudf4;               //UDF4
                        $result['udf5'] = $Resudf5;
                        $result['ErrorText']= $ResErrorText; 	  	//Error 
                        $result['Error'] = $ResErrorNo;  
                    }
                    
                }
                else
                {
                    // error
                    $result['status'] = 'error';
                    $result['paymentid'] = $ResPaymentId;
                    $result['trackid'] = $ResTrackID;
                    $result['tranid'] = $ResTranId;
                    $result['ref'] = $ResRef;
                    $result['result'] = 'error';
                    $result['data']= sanitize_text_field(http_build_query($_REQUEST));
                    $result['ErrorText']= $ResErrorText; 	  	//Error 
                    $result['Error'] = $ResErrorNo;           //Error Number
                    $result['postdate'] = $ResPosdate ;        //Postdate
                    $result['auth'] = $ResAuth;               //Auth Code		
                    $result['avr'] = $ResAVR;                 //TRANSACTION avr					
                    $result['ammount'] = $ResAmount;              //Transaction Amount
                    $result['udf1'] = $Resudf1;               //UDF1
                    $result['udf2'] = $Resudf2;               //UDF2
                    $result['udf3'] = $Resudf3;               //UDF3
                    $result['udf4'] = $Resudf4;               //UDF4
                    $result['udf5'] = $Resudf5; 
                }

                        //UDF5
                return  $result;
            }

            /** ====================== Order functions =======
             * tis all function modfide order
             */

            /** get ammount after exchange
             * @param $order
             * @return float|int
             */
            private function getTotalAmount($order){
                if($this->currency == "KWD"){
                    return $order->get_total();
                }elseif(!empty($this->exchange) && $this->exchange > 0){
                    return $order->get_total()*$this->exchange;
                }
                return $order->get_total();

            }

            /**
             * hide gateways in test mode
             * @param $available_gateways
             * @return mixed
             */
            public  function  wc_conditional_payment_gateways($available_gateways){

                if(is_admin()){
                    return $available_gateways;
                }
                if($this->is_test == "yes"){
                    $available_gateways[$this->id]->title= $available_gateways[$this->id]->title. " <b style=\"color:red\">" .__("Test Mode","wc-knet")."</b>";
                    $wp_get_current_user = wp_get_current_user();
                    if(isset($wp_get_current_user)){
                        if(!in_array("shop_manager",$wp_get_current_user->roles) && !in_array("administrator",$wp_get_current_user->roles)){
                            unset($available_gateways[$this->id]);
                        }
                    }
                }
                return $available_gateways;
            }

            /**
             * display html table KNET details in received order page
             * @param $order
             */
            public function wc_knet_details($order){

                if($order->get_payment_method() != $this->id) {
                    return;
                }
                $knet_details = wc_get_transation_by_orderid($order->get_id());

                if(!$knet_details){
                    return;
                }
                $output = $this->format_email($order,$knet_details,"knet-details.html");

                echo wp_kses($output,$this->html_allow);

            }

            /**
             * display html table KNET details in email message
             * @param $order
             * @param $is_admin
             * @param $text_plan
             */
            public function wc_knet_email_details($order,$is_admin,$text_plan){
                if($order->get_payment_method() != $this->id) {
                    return;
                }
                $knet_details = wc_get_transation_by_orderid($order->get_id());
                if(!$knet_details){
                    return;
                }
                if($text_plan){
                    $output = $this->format_email($order,$knet_details,"emails/knet-text-details.html");
                }else{
                    $output = $this->format_email($order,$knet_details,"emails/knet-html-details.html");
                }
                echo wp_kses($output, $this->html_allow);
            }

            /**
             * format email knet details html table
             * @param $order
             * @param $knet_detials
             * @param string $template
             * @return mixed
             */
            private function format_email($order,$knet_detials,$template="knet-details.html")
            {
                $template = file_get_contents(plugin_dir_path(__FILE__).$template);
                $replace = [
                    "{icon}"=> plugin_dir_url(__FILE__)."assets/knet-logo.png",
                    "{title}" => __("Knet details","wc-knet"),
                    "{payment_id}" => ($knet_detials->payment_id) ? $knet_detials->payment_id : "---",
                    "{track_id}" => ($knet_detials->track_id) ? $knet_detials->track_id : "---",
                    "{amount}" => ($knet_detials->amount) ? $knet_detials->amount : "---",
                    "{tran_id}" => ($knet_detials->tran_id) ? $knet_detials->tran_id : "---",
                    "{ref_id}" => ($knet_detials->ref_id) ? $knet_detials->ref_id : "---",
                    "{created_at}" => ($knet_detials->created_at) ? wp_date("F j, Y", strtotime($knet_detials->created_at) ) : "---",
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_detials->result),
                ];
                $replace_lang = [
                    "_lang(result)" => __("Result","wc-knet"),
                    "_lang(payment_id)" => __("Payment id","wc-knet"),
                    "_lang(trnac_id)" => __("Transaction id","wc-knet"),
                    "_lang(track_id)" => __("Tracking id","wc-knet"),
                    "_lang(amount)" => __("Amount","wc-knet"),
                    "_lang(ref_id)" => __("Refrance id","wc-knet"),
                    "_lang(created_at)" => __('Created at', "wc-knet"),
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_detials->result),
                ];
                $replace = array_merge($replace, $replace_lang);
                return str_replace(array_keys($replace), array_values($replace), $template);
            }

            /**
             * add colored order status in received page
             * @param $str
             * @return string
             */
            public function wc_woo_change_order_received_text($str) {
                global  $id;
                $order = $this->get_order_in_recived_page($id,true);
                $order_status = $order->get_status();
                return  sprintf("%s <b><span style=\"color:%s\">%s</span></b>.",__("Thank you. Your order has been","wc-knet"),$this->get_status_color($order_status),__(ucfirst($order_status),"woocommerce"));
            }

            /**
             * add colored order status in received page
             * @param $old_title
             * @return string
             */
            public function wc_thank_you_title( $old_title){
                global  $id;
                $order_status = $this->get_order_in_recived_page($id);

                if ( isset ( $order_status ) ) {
                    return  sprintf( "%s , <b><span style=\"color:%s\">%s</span></b>",__('Order',"wc-knet"),$this->get_status_color($order_status), esc_html( __(ucfirst($order_status),"woocommerce")) );
                }
                return $old_title;
            }

            /**
             * get order details in received page
             * @param $page_id
             * @param bool $return_order
             * @return bool|string|WC_Order
             */
            private function get_order_in_recived_page($page_id,$return_order= false){
                global $wp;
                if ( is_order_received_page() && get_the_ID() === $page_id ) {
                    $order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $wp->query_vars['order-received'] ) );
                    $order_key = apply_filters( 'woocommerce_thankyou_order_key', empty( $_GET['key'] ) ? '' : wc_clean( $_GET['key'] ) );
                    if ( $order_id > 0 ) {
                        $order = new WC_Order( $order_id );

                        if ( $order->get_order_key() != $order_key ) {
                            $order = false;
                        }
                        if($return_order){
                            return $order;
                        }
                        return $order->get_status();
                    }
                }
                return false;
            }

            /**
             * set status color
             * @param $status
             * @return string
             */
            private function get_status_color($status){
                switch ($status){
                    case "pending":
                        return "#0470fb";
                    case "processing":
                        return "#fbbd04";
                    case "on-hold":
                        return "#04c1fb";
                    case "completed":
                        return "green";
                    default:
                        return "#fb0404";
                }
            }


            /** ======== Payment Encrypt Functions Started ======
             * this functions created by knet devolper don't change any thing
            */
            /**
             * @param $str
             * @param $key
             * @return string
             */
            public function encryptAES($str,$key)
            {
                $str = $this->pkcs5_pad($str); 
                $encrypted = openssl_encrypt($str, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $key);
                $encrypted = base64_decode($encrypted);
                $encrypted=unpack('C*', ($encrypted));
                $encrypted=$this->byteArray2Hex($encrypted);
                $encrypted = urlencode($encrypted);
                return $encrypted;
            }

            /**
             * @param $text
             * @return string
             */
            public function pkcs5_pad ($text)
            {
                $blocksize = 16;
                $pad = $blocksize - (strlen($text) % $blocksize);
                return $text . str_repeat(chr($pad), $pad);
            }

            /**
             * @param $byteArray
             * @return string
             */
            public function byteArray2Hex($byteArray)
            {
                $chars = array_map("chr", $byteArray);
                $bin = join($chars);
                return bin2hex($bin);
            }

            /**
             * @param $code
             * @param $key
             * @return bool|string
             */
            public function decrypt($code,$key)
            { 
                $code =  $this->hex2ByteArray(trim($code));
                $code=$this->byteArray2String($code);
                $iv = $key; 
                $code = base64_encode($code);
                $decrypted = openssl_decrypt($code, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $iv);
                return $this->pkcs5_unpad($decrypted);
            }

            /**
             * @param $hexString
             * @return array
             */
            public function hex2ByteArray($hexString)
            {
                $string = hex2bin($hexString);
                return unpack('C*', $string);
            }

            /**
             * @param $byteArray
             * @return string
             */
            public function byteArray2String($byteArray)
            {
                $chars = array_map("chr", $byteArray);
                return join($chars);
            }

            /**
             * @param $text
             * @return bool|string
             */
            public function pkcs5_unpad($text)
            {
                $pad = ord($text{strlen($text)-1});
                if ($pad > strlen($text)) {
                    return false;	
                }
                if (strspn($text, chr($pad), strlen($text) - $pad) != $pad) {
                    return false;
                }
                return substr($text, 0, -1 * $pad);
            }
            /** ======== Payment Encrypt Functions Ended ====== */
          
        } 

    }


    /**
     * Add the Gateway to WooCommerce
     * @param $methods
     * @return mixed
     */
    if(!function_exists("woocommerce_add_wc_knet_gateway")){
        function woocommerce_add_wc_knet_gateway($methods) {
            global $WC_Payment_KNET_CLASS_NAME;

            $methods[] = $WC_Payment_KNET_CLASS_NAME;

            return $methods;
        }
    }

    add_filter('woocommerce_payment_gateways', 'woocommerce_add_wc_knet_gateway' );

    /**
     * load plugin language
     * @param $mofile
     * @param $domain
     * @return string
     */
    if(!function_exists("wc_knet_load_textdomain")){
        function wc_knet_load_textdomain() {
            return load_plugin_textdomain( 'wc-knet', false, basename( dirname( __FILE__ ) ) . '/languages/' );
        }
    }

    add_action( 'plugins_loaded', 'wc_knet_load_textdomain' );


    /**
     * add knet responce query var
     */
    add_filter( 'query_vars', function( $query_vars ) {
        $query_vars[] = 'knetresponce';
        $query_vars[] = 'wc_knet_export';
        return $query_vars;
    } );
    /**
     * define knet responce
     */
    add_action("wp",function($request)
    {

        if( isset($request->query_vars['knetresponce']) && null !== sanitize_text_field($request->query_vars['knetresponce']) && sanitize_text_field($request->query_vars['knetresponce']) == "success")
        {
            $WC_Gateway_Knet = new WC_Payment_Gateway_Knet();
            $url = $WC_Gateway_Knet->updateOrder();
            
            if ( wp_redirect( $url ) )
            {
                exit;
            }
        }

    });

    /**
     * export files
     */
    add_action("admin_init",function (){
        $action = esc_attr($_GET["wc_knet_export"] ?? "");
        if(is_admin()){
            if(sanitize_text_field($action) == "excel"){
                $rows = wc_knet_payment_trans_grid::get_transations(1000);
                $list[] =[__('Order', "wc-knet"), __('Status', "wc-knet"), __('Result', "wc-knet"), __('Amount', "wc-knet"), __('Payment id', "wc-knet"), __('Tracking id', "wc-knet"), __('Transaction id', "wc-knet"), __('Refrance id', "wc-knet"), __('Created at', "wc-knet") ];
                if($rows){
                    foreach ($rows as $row){
                        $list[] = [$row['order_id'],__($row['status'],"wc_kent"),$row['result'],$row['amount'],$row['payment_id'],$row['track_id'],$row['tran_id'],$row['ref_id'],$row['created_at']];
                    }
                }
                $xlsx = SimpleXLSXGen::fromArray( $list );
                $xlsx->downloadAs(date("YmdHis").'.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx
                exit();
            }elseif (sanitize_text_field($action) == "csv"){

                $rows = wc_knet_payment_trans_grid::get_transations(1000);
                if($rows){
                    $filename =  date('YmdHis') . ".csv";
                    $f = fopen('php://memory', 'w');
                    $delimiter = ",";
                    $head = [__('Order', "wc-knet"), __('Status', "wc-knet"), __('Result', "wc-knet"), __('Amount', "wc-knet"), __('Payment id', "wc-knet"), __('Tracking id', "wc-knet"), __('Transaction id', "wc-knet"), __('Refrance id', "wc-knet"), __('Created at', "wc-knet") ];
                    fputcsv($f, $head,$delimiter);
                    foreach ($rows as $row){
                        $listData = [$row['order_id'],__($row['status'],"wc_kent"),$row['result'],$row['amount'],$row['payment_id'],$row['track_id'],$row['tran_id'],$row['ref_id'],$row['created_at']];
                        fputcsv($f, $listData, $delimiter);
                    }
                    fseek($f, 0);
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $filename . '";');
                    fpassthru($f);
                    exit();
                }
            }
        }

    });
    // call to install data
    register_activation_hook( __FILE__, 'create_transactions_db_table');

    /**
     * notify is currency not KWD
     */
    add_action('admin_notices', 'wc_knet_is_curnancy_not_kwd');
    if(!function_exists("wc_knet_is_curnancy_not_kwd")){
        function wc_knet_is_curnancy_not_kwd(){
            $currency = get_option('woocommerce_currency');
            if(isset($currency) && $currency != "KWD"){
                echo '<div class="notice notice-warning is-dismissible">
             <p>'.__("currency must be KWD when using this knet payment","wc-knet").'</p>
         </div>';
            }
        }
    }




?>