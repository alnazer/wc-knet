<?php
/*
*Plugin Name: Payment Gateway for knet on WooCommerce
*Plugin URI: https://github.com/alnazer/woocommerce-payment-kent-v2
*Description: The new update of the K-Net payment gateway via woocommerce paymemt.
*Author: Hassan hassanaliksa@gmail.com +96590033807
*Version: 2.1.0
*Author URI: https://github.com/alnazer
*Text Domain: wc_knet
* Domain Path: /languages
*/
/**
 * @package wc knet woocommerce
*/
defined( 'ABSPATH' ) || exit;
define("WC_KNET_TABLE","wc_knet_transactions");
define("WC_KNET_DV_VERSION","1.0");
define("STATUS_SUCCESS","success");
define("STATUS_FAIL","fail");
define("STATUS_NEW","new");
    // include transactions table
    require_once plugin_dir_path(__FILE__)."transactions.php";
    require_once plugin_dir_path(__FILE__)."wc_knet_trans_grid.php";
    require_once plugin_dir_path(__FILE__)."classes/SimpleXLSXGen.php";
    // initialization payment class when plugin load
    $WC_KNET_CLASS_NAME = "WC_Gateway_Knet";
    add_action( 'plugins_loaded', 'init_wc_knet',0);

    function init_wc_knet()
    {

        if ( !class_exists( 'WC_Payment_Gateway' ) ) {return;}
        WC_KNET_Plugin::get_instance();
        /**
         *  Knet Gateway.
         *
         * Provides a VISA Payment Gateway.
         *
         * @class       WC_Gateway_Knet
         * @extends     WC_Payment_Gateway
         * @version     2.2.0
         * @package     WooCommerce/Classes/Payment
     */
        class WC_Gateway_Knet extends WC_Payment_Gateway {

            private $tranportal_id;
            private $password;
            private $resource_key;
            private $GatewayUrl='https://kpaytest.com.kw/';
            private $paymentUrl = 'id={id}&password={password}&action=1&langid=AR&currencycode=414&amt={amt}&responseURL={responseURL}&errorURL={errorURL}&trackid={trackid}&udf1={udf1}&udf2={udf2}&udf3={udf3}&udf4={udf4}&udf5={udf5}';
            private $name = "";
            private $email = "";
            private $mobile = "";
            private $trackId;
            private $responseURL;
            private $errorURL;
            public $is_test;
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
                $this->tranportal_id = $this->get_option('tranportal_id');
                $this->password = $this->get_option('password');
                $this->resource_key = $this->get_option('resource_key');
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

                // add routers
                //add_action('init', [$this,'wc_knet_rewrite_tag_rule'], 10, 0);
            }

            public function wc_knet_details($order){
                $knet_details = wc_get_transation_by_orderid($order->get_id());

                if(!$knet_details){
                    return;
                }
                $output = $this->format_email($order,$knet_details,"knet-details.html");
                echo $output;

            }
            public function wc_knet_email_details($order,$is_admin,$text_plan){
                $knet_details = wc_get_transation_by_orderid($order->get_id());
                if(!$knet_details){
                    return;
                }
                if($text_plan){
                    $output = $this->format_email($order,$knet_details,"emails/knet-text-details.html");
                }else{
                    $output = $this->format_email($order,$knet_details,"emails/knet-html-details.html");
                }
                echo $output;
            }

            private function format_email($order,$knet_detials,$template="knet-details.html")
            {
                $template = file_get_contents(plugin_dir_path(__FILE__).$template);
                $replace = [
                    "{icon}"=> plugin_dir_url(__FILE__)."assets/knet-logo.png",
                    "{title}" => __("Knet details","wc_knet"),
                    "{payment_id}" => ($knet_detials->payment_id) ? $knet_detials->payment_id : "---",
                    "{track_id}" => ($knet_detials->track_id) ? $knet_detials->track_id : "---",
                    "{amount}" => ($knet_detials->amount) ? $knet_detials->amount : "---",
                    "{tran_id}" => ($knet_detials->tran_id) ? $knet_detials->tran_id : "---",
                    "{ref_id}" => ($knet_detials->ref_id) ? $knet_detials->ref_id : "---",
                    "{created_at}" => ($knet_detials->created_at) ? $knet_detials->created_at : "---",
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_detials->result),
                ];
                $replace_lang = [
                    "_lang(result)" => __("Result","wc_knet"),
                    "_lang(payment_id)" => __("Payment id","wc_knet"),
                    "_lang(trnac_id)" => __("Transaction id","wc_knet"),
                    "_lang(track_id)" => __("Tracking id","wc_knet"),
                    "_lang(amount)" => __("Amount","wc_knet"),
                    "_lang(ref_id)" => __("Refrance id","wc_knet"),
                    "_lang(created_at)" => __('Created at', "wc_knet"),
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_detials->result),
                ];
                $replace = array_merge($replace, $replace_lang);
                return str_replace(array_keys($replace), array_values($replace), $template);
            }
            public function wc_woo_change_order_received_text($str) {
	            global  $id;
	            $order = $this->get_order_in_recived_page($id,true);
                $order_status = $order->get_status();
	            return  sprintf("%s <b><span style=\"color:%s\">%s</span></b>.",__("Thank you. Your order has been","wc_knet"),$this->get_status_color($order_status),__(ucfirst($order_status),"woocommerce"));
            }

	        public function wc_thank_you_title( $old_title){
            	global  $id;
		        $order_status = $this->get_order_in_recived_page($id);

		        if ( isset ( $order_status ) ) {
			       return  sprintf( "%s , <b><span style=\"color:%s\">%s</span></b>",__('Order',"wc_knet"),$this->get_status_color($order_status), esc_html( __(ucfirst($order_status),"woocommerce")) );
		        }
		        return $old_title;
	        }

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

            /**
             * define knet route like knetresponce/success
             */
            /*public function wc_knet_rewrite_tag_rule() {
                add_rewrite_rule( '^knetresponce/([^/]*)/?', 'index.php?knetresponce=$matches[1]','top' );
            }*/
            /**
             * initialization gateway call default data
             * like id,icon 
             */
            public function init_gateway()
            {
                $this->id                 = 'wc_knet';
                $this->icon               =  plugins_url( 'assets/knet-logo.png' , __FILE__ );
                $this->method_title       = __('Knet', 'wc_knet');
                $this->method_description = __( 'intgration with knet php raw.', 'woocommerce' );
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
                        'label' => __( 'Enable Knet Payment', 'woocommerce' ),
                        'default' => 'yes'
                    ),
                    'is_test' => array(
                        'title'       => 'Test mode',
                        'label'       => 'Enable Test Mode',
                        'type'        => 'checkbox',
                        'description' => 'Place the payment gateway in test mode using test API keys.',
                        'default'     => 'no',
                        'desc_tip'    => true,
                    ),
                    'title' => array(
                        'title' => __( 'Title', 'woocommerce' ),
                        'type' => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default' => __( 'knet', 'woocommerce' ),
                        'desc_tip'      => true,
                    ),
                    'description' => array(
                            'title' => __( 'Description', 'woocommerce' ),
                            'type' => 'textarea',
                            'default' => ''
                    ),
                    'tranportal_id' => array(
                        'title' => __( 'Tranportal Id', 'wc_knet' ),
                        'type' => 'text',
                        'label' => __( 'Necessary data requested from the bank ', 'wc_knet' ),
                        'default' => ''
                    ),
                    'password' => array(
                        'title' => __( 'Transportal Password', 'wc_knet' ),
                        'type' => 'password',
                        'description' => __( 'Necessary data requested from the bank ', 'wc_knet' ),
                        'default' => '',
                        'desc_tip'      => true,
                    ),
                    'resource_key' => array(
                        'title' => __( 'Terminal Resource Key', 'wc_knet' ),
                        'type' => 'password',
                        'description' => __( 'Necessary data requested from the bank', 'wc_knet' ),
                        'default' => '',
                        'desc_tip'      => true,
                    ),
                    
                );        
            }
            /**
             * Admin Panel Options
             * - Options for bits like 'title', 'description', 'alias'
             **/
            public function admin_options(){
                echo '<h3>'.__('Knet', 'wc_knet').'</h3>';
                echo '<p>'.__('Knet', 'wc_knet').'</p>';
                echo '<table class="form-table">';
                $this->generate_settings_html();
                echo '</table>';

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
                    wc_add_notice( __("Order not found", "wc_knet"), 'error' );
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
                    $order->add_order_note( __('Payment error:', 'woothemes') .  __("Knet can't get data", 'wc_knet'),'error' );
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
                $replace_array['{amt}'] = $order->get_total();
                $replace_array['{trackid}'] = $this->trackId;
                $replace_array['{responseURL}'] = $this->responseURL;
                $replace_array['{errorURL}'] = $this->errorURL;

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
                        'status' => ($result == "CAPTURED") ? STATUS_SUCCESS : STATUS_FAIL,
                        "amount" => $resnopseData["ammount"],
                        "data" => $resnopseData["data"],
                        'error'=>$ErrorText,
                    ];

                    if(!$order->get_id())
                    {
                        wc_add_notice( __("Order not found", "wc_knet"), 'error' );
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
                            $knetInfomation.= __('Result', 'wc_knet')."           : $result\n";
                            $knetInfomation.= __('Payment id', 'wc_knet')."       : $paymentid\n";
                            $knetInfomation.= __('track id', 'wc_knet')."         : $trackid\n";
                            $knetInfomation.= __('Transaction id', 'wc_knet')."   : $tranid\n";
                            $knetInfomation.= __('Refrance id', 'wc_knet')."      : $ref\n";
                            $order->add_order_note($knetInfomation);

                    }
                    elseif(isset($status) && $status == "error")
                    {
                            // insert transation
                            do_action("wc_knet_create_new_transation",$order,$transation_data);
                            $knetInfomation = "";
                            $knetInfomation.= __('Result', 'wc_knet')."           : $result\n";
                            $knetInfomation.= __('Payment id', 'wc_knet')."       : $paymentid\n";
                            $knetInfomation.= __('track id', 'wc_knet')."         : $trackid\n";
                            $knetInfomation.= __('Transaction id', 'wc_knet')."   : $tranid\n";
                            $knetInfomation.= __('Refrance id', 'wc_knet')."      : $ref\n";
                            $knetInfomation.= __('Error', 'wc_knet')."            : $Error\n";
                            $knetInfomation.= __('Error Message', 'wc_knet')."    : $ErrorText\n";
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
            /** ======== Payment Encrypt Functions Started ======
             * this functions created by knet devolper don't change any thing
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

            public function pkcs5_pad ($text)
            {
                $blocksize = 16;
                $pad = $blocksize - (strlen($text) % $blocksize);
                return $text . str_repeat(chr($pad), $pad);
            }
            public function byteArray2Hex($byteArray)
            {
                $chars = array_map("chr", $byteArray);
                $bin = join($chars);
                return bin2hex($bin);
            }

            public function decrypt($code,$key)
            { 
                $code =  $this->hex2ByteArray(trim($code));
                $code=$this->byteArray2String($code);
                $iv = $key; 
                $code = base64_encode($code);
                $decrypted = openssl_decrypt($code, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $iv);
                return $this->pkcs5_unpad($decrypted);
            }

            public function hex2ByteArray($hexString)
            {
                $string = hex2bin($hexString);
                return unpack('C*', $string);
            }


            public function byteArray2String($byteArray)
            {
                $chars = array_map("chr", $byteArray);
                return join($chars);
            }

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
     **/
    function woocommerce_add_wc_knet_gateway($methods) {
        global $WC_KNET_CLASS_NAME;
        $methods[] = $WC_KNET_CLASS_NAME;
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_wc_knet_gateway' );

    /**
     * load plugin language
     * @param $mofile
     * @param $domain
     * @return string
     */
    function wc_knet_load_textdomain() {
        return load_plugin_textdomain( 'wc_knet', false, basename( dirname( __FILE__ ) ) . '/languages/' );
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
            $WC_Gateway_Knet = new WC_Gateway_Knet();
            $url = $WC_Gateway_Knet->updateOrder();
            
            if ( wp_redirect( $url ) )
            {
                exit;
            }
        }

    });

    add_action("admin_init",function (){
        $action = esc_attr($_GET["wc_knet_export"] ?? "");
        if(is_admin()){
            if(sanitize_text_field($action) == "excel"){
                $rows = wc_knet_trans_grid::get_transations(1000);
                $list[] =[__('Order', "wc_knet"), __('Status', "wc_knet"), __('Result', "wc_knet"), __('Amount', "wc_knet"), __('Payment id', "wc_knet"), __('Tracking id', "wc_knet"), __('Transaction id', "wc_knet"), __('Refrance id', "wc_knet"), __('Created at', "wc_knet") ];
                if($rows){
                    foreach ($rows as $row){
                        $list[] = [$row['order_id'],__($row['status'],"wc_kent"),$row['result'],$row['amount'],$row['payment_id'],$row['track_id'],$row['tran_id'],$row['ref_id'],$row['created_at']];
                    }
                }
                $xlsx = SimpleXLSXGen::fromArray( $list );
                $xlsx->downloadAs(date("YmdHis").'.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx
                exit();
            }elseif (sanitize_text_field($action) == "csv"){

                $rows = wc_knet_trans_grid::get_transations(1000);
                if($rows){
                    $filename =  date('YmdHis') . ".csv";
                    $f = fopen('php://memory', 'w');
                    $delimiter = ",";
                    $head = [__('Order', "wc_knet"), __('Status', "wc_knet"), __('Result', "wc_knet"), __('Amount', "wc_knet"), __('Payment id', "wc_knet"), __('Tracking id', "wc_knet"), __('Transaction id', "wc_knet"), __('Refrance id', "wc_knet"), __('Created at', "wc_knet") ];
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
    function wc_knet_is_curnancy_not_kwd(){
        $currency = get_option('woocommerce_currency');
        if(isset($currency) && $currency != "KWD"){
            echo '<div class="notice notice-warning is-dismissible">
             <p>'.__("currency must be KWD when using this knet payment","wc_knet").'</p>
         </div>';
        }
    }



?>