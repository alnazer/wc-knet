<?php
/*
*Plugin Name: Payment Gateway for knet on WooCommerce
*Plugin URI: https://github.com/alnazer/woocommerce-payment-kent-v2
*Description: The new update of the K-Net payment gateway via woocommerce paymemt.
*Author: Hassan
*Version: 1.0
*Author URI: https://github.com/alnazer
*Text Domain: wc_knet
* Domain Path: /languages
*/
/**
 * @package wc knet woocommerce
*/

    // initialization payment class when plugin load
    $WC_KNET_CLASS_NAME = "WC_Gateway_Knet";
    add_action( 'plugins_loaded', 'init_wc_knet',0);

    function init_wc_knet()
    {
        if ( !class_exists( 'WC_Payment_Gateway' ) ) {return;}
        /**
         *  Knet Gateway.
         *
         * Provides a VISA Payment Gateway.
         *
         * @class       WC_Gateway_Knet
         * @extends     WC_Payment_Gateway
         * @version     2.1.0
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
            private $user_id = "";
            private $trackId;
            private $responseURL;
            private $errorURL;

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

                // add routers
                add_action('init', [$this,'wc_knet_rewrite_tag_rule'], 10, 0);
            }
            /**
             * define knet route like knetresponce/success
             */
            public function wc_knet_rewrite_tag_rule() {
                add_rewrite_rule( '^knetresponce/([^/]*)/?', 'index.php?knetresponce=$matches[1]','top' );
            }
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
             */
            private function formatUrlParames($order)
            {
                $this->user_id = $order->get_user_id();
                if($this->user_id)
                {
                    $user_info = $order->get_user();
                    $this->name = $user_info->user_login;
                    $this->email = $user_info->user_email;
                    $this->mobile = $user_info->user_phone;
                }
                $this->errorURL = get_site_url()."/knetresponce/success/";
                $this->responseURL = get_site_url()."/knetresponce/success/";

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
                     
                    if(!$order->get_id())
                    {
                        wc_add_notice( __("Order not found", "wc_knet"), 'error' );
                        return $order->get_view_order_url();
                    }
                    elseif(isset($status) && $status == "success")
                    {
                            
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
                            $knetInfomation.= __('Result', 'woothemes')."           : $result\n";
                            $knetInfomation.= __('Payment id', 'woothemes')."       : $paymentid\n";
                            $knetInfomation.= __('track id', 'woothemes')."         : $trackid\n";
                            $knetInfomation.= __('Transaction id', 'woothemes')."   : $tranid\n";
                            $knetInfomation.= __('Refrance id', 'woothemes')."      : $ref\n";
                            $order->add_order_note($knetInfomation);
                    }
                    elseif(isset($status) && $status == "error")
                    {
                            $knetInfomation = "";
                            $knetInfomation.= __('Result', 'woothemes')."           : $result\n";
                            $knetInfomation.= __('Payment id', 'woothemes')."       : $paymentid\n";
                            $knetInfomation.= __('track id', 'woothemes')."         : $trackid\n";
                            $knetInfomation.= __('Transaction id', 'woothemes')."   : $tranid\n";
                            $knetInfomation.= __('Refrance id', 'woothemes')."      : $ref\n";
                            $knetInfomation.= __('Error', 'woothemes')."            : $Error\n";
                            $knetInfomation.= __('Error Message', 'woothemes')."    : $ErrorText\n";
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
                $ResResult      =   (isset($_REQUEST['result']))    ? sanitize_text_field($_REQUEST['result']) : null;           //Transaction Result
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

    
    function wc_knet_load_languages_textdomain( $mofile, $domain )
    {
        if ( 'wc_knet' === $domain && false !== strpos( $mofile, WP_LANG_DIR . '/plugins/' ) ) {
            $locale = apply_filters( 'plugin_locale', determine_locale(), $domain );
            $mofile = WP_PLUGIN_DIR . '/' . dirname( plugin_basename( __FILE__ ) ) . '/languages/' . $domain . '-' . $locale . '.mo';
        }
        return $mofile;
    }
    add_filter( 'load_textdomain_mofile', 'wc_knet_load_languages_textdomain', 10, 2 );

    /**
     * add knet responce query var
     */
    add_filter( 'query_vars', function( $query_vars ) {
        $query_vars[] = 'knetresponce';
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
   
?>