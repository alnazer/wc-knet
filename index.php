<?php
    /*
    *Plugin Name: Payment Gateway for KNET
    *Plugin URI: https://github.com/alnazer/wc-knet
    *Description: The new update of the K-Net payment gateway via woocommerce payment.
    *Author: alnazer
    *Version: 2.11.1
    *Author URI: https://github.com/alnazer
    *Text Domain: wc-knet
    * Domain Path: /languages/
    */
    /**
     * @package WC KNET woocommerce
     */
    
    defined('ABSPATH') || exit;
    const WC_PAYMENT_KNET_TABLE = "wc_knet_transactions";
    const WC_PAYMENT_KNET_DV_VERSION = "1.1";
    const WC_PAYMENT_STATUS_SUCCESS = "success";
    const WC_PAYMENT_STATUS_FAIL = "fail";
    const WC_PAYMENT_STATUS_NEW = "new";
    define("WC_ASSETS_PATH", plugins_url("assets", __FILE__));
    // include transactions table
    require_once plugin_dir_path(__FILE__) . "wc_knet_payment_transactions.php";
    require_once plugin_dir_path(__FILE__) . "wc_knet_payment_trans_grid.php";
    require_once plugin_dir_path(__FILE__) . "classes/SimpleXLSXGen.php";
    // initialization payment class when plugin load
    $WC_Payment_KNET_CLASS_NAME = "WC_Payment_Gateway_KNET";
    add_action('plugins_loaded', 'alnazer_init_wc_knet_payment', 0);
    function alnazer_init_wc_knet_payment(): void
    {
        
        
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        WC_KNET_PAYMENT_Plugin::get_instance();
        // create table in database
        if (get_site_option('wc_knet_db_version') != WC_PAYMENT_KNET_DV_VERSION) {
            alnazer_create_transactions_db_table();
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
        class WC_Payment_Gateway_KNET extends WC_Payment_Gateway
        {
            
            private $commission;
            private $kfast_token_key = "kfast_token";
            private $exchange;
            private $currency;
            private $tranportal_id;
            private $password;
            private $resource_key;
            private $GatewayUrl = 'https://kpaytest.com.kw/';
            private $paymentUrl = 'id={id}&password={password}&action=1&langid={lang}&currencycode=414&amt={amt}&responseURL={responseURL}&errorURL={errorURL}&trackid={trackid}&udf1={udf1}&udf2={udf2}&udf3={udf3}&udf4={udf4}&udf5={udf5}';
            private $name = "";
            private $email = "";
            private $mobile = "";
            private $trackId;
            private $responseURL;
            /**
             * @since 2.10.0
             */
            private $redirectURL;
            public $errorURL;
            public $is_test;
            /**
             * @since 2.10.0
             */
            public $is_redirect_mode;
            /**
            * @since 2.11.0
            */
            public $logo = 'knet-logo.png';
            public $is_kfast;
            public $lang = "AR";
            private $complete_order_status;
            public $pending_order_status = "on-hold";
            private $html_allow = array(
                'h2' => array("class" => array(), "style" => array()),
                "span" => array("style" => array()),
                'table' => array("class" => array()),
                'tr' => array(),
                'th' => array(),
                'td' => array(),
                'b' => array(),
                'br' => array(),
                'img' => array(
                    "src" => array(),
                    "width" => array(),
                    "alt" => array()
                )
            );
            /**
            * @since 2.11.0
            */
            private $upload_dir = 'assets/';
            /**
             *
             */
            public function __construct()
            {
                
                $this->init_gateway();
                $this->logo = $this->get_option('logo');
                $this->getGatewayIcon();
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
                /**
                 * @since 2.10.0
                 */
                $this->is_redirect_mode = $this->get_option('is_redirect_mode');
                $this->is_kfast = $this->get_option('is_kfast');
                $this->commission = $this->get_option("commission");
                $this->complete_order_status = $this->get_option('complete_order_status');
                $this->pending_order_status = $this->get_option('pending_order_status');
                
                if ($this->is_test === "no") {
                    $this->GatewayUrl = "https://kpay.com.kw/";
                }
                $this->errorURL = get_site_url() . "/index.php?knet_response=success";
                $this->responseURL = get_site_url() . "/index.php?knet_response=success";
                /**
                 * @since 2.10.0
                 */
                $this->redirectURL = get_site_url() . "/index.php?knet_response=redirect";
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                    $this,
                    'process_admin_options'
                ));
                add_filter('woocommerce_thankyou_order_received_text', [$this, 'wc_woo_change_order_received_text']);
                add_filter('woocommerce_endpoint_order-received_title', [$this, 'wc_thank_you_title']);
                
                // add details to thank you page
                add_action("woocommerce_order_details_before_order_table", [$this, 'wc_knet_details'], 10, 1);
                // add details to email
                add_action("woocommerce_email_after_order_table", [$this, 'wc_knet_email_details'], 10, 3);
                add_filter('woocommerce_available_payment_gateways', [$this, 'wc_conditional_payment_gateways'], 10, 1);
                
                // change payment title
                add_filter("woocommerce_gateway_title", [$this, 'wc_woocommerce_gateway_title'], 25, 2);
                add_filter("woocommerce_gateway_description", [$this, 'wc_woocommerce_gateway_description'], 25, 2);
                
            }
            
            /**
             * initialization gateway call default data
             * like id,icon
             */
            public function init_gateway()
            {
                $this->id = 'wc_knet';
                $this->icon = $this->getGatewayIcon();
                $this->method_title = __('KNET', 'wc-knet');
                $this->method_description = __('integration with KNET php raw.', 'wc-knet');
                $this->has_fields = true;
            }
            /**
            * @since 2.11.0
            */
            private function getGatewayIcon(){
                   
                    $this->icon = plugins_url($this->upload_dir.'knet-logo.png', __FILE__);
                    if(isset($this->logo) && file_exists(plugin_dir_path( __FILE__ ).$this->upload_dir.$this->logo)){
                        $this->icon = plugins_url($this->upload_dir.$this->logo, __FILE__) ;
                    } 
                }
            /**
             * Define Form Option fields
             * - Options for payment like 'title', 'description', 'tranportal_id', 'password', 'resource_key'
             **/
            public function init_form_fields()
            {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', 'wc-knet'),
                        'type' => 'checkbox',
                        'label' => __('Enable KNET Payment', 'wc-knet'),
                        'default' => 'yes'
                    ),
                    'is_test' => array(
                        'title' => __('Test mode','wc-knet'),
                        'label' => __('Enable Test Mode','wc-knet'),
                        'type' => 'checkbox',
                        'description' => __("Place the payment gateway in test mode using test. only this user roles [Shop manager,Administrator] can test payment", "wc-knet"),
                        'default' => 'no',
                        'desc_tip' => false,
                    ),
                    'is_redirect_mode' => array(
                        'title' => __('KNET REDIRECT page','wc-knet'),
                        'label' =>  __('Enable KNET REDIRECT page','wc-knet'),
                        'type' => 'checkbox',
                        'description' => __("If you were a subscriber to K-Net after 4/9/2023 the date on which this feature was activated", "wc-knet"),
                        'default' => 'no',
                        'desc_tip' => false,
                    ),
                    
                    'is_kfast' => array(
                        'title' =>  __('KFAST','wc-knet'),
                        'label' =>  __('Enable KFAST','wc-knet'),
                        'type' => 'checkbox',
                        'description' => __("KFAST is a new feature, affiliated with KNET Payment Gateway, by which the customer can save their card(s) details, enabling them to carry out any future transactions with the same merchant, in a speedy manner, by only having to enter their PIN when prompted.", "wc-knet"),
                        'default' => 'no',
                        'desc_tip' => true,
                    ),
                    
                    'title' => array(
                        'title' => __('Title', 'wc-knet'),
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'wc-knet'),
                        'default' => __('KNET', 'wc-knet'),
                        'desc_tip' => true,
                    ),
                    'description' => array(
                        'title' => __('Description', 'wc-knet'),
                        'type' => 'textarea',
                        'default' => ''
                    ),
                    'logo' => array(
                            'title'       => __( 'Payment gateway icon', 'wc-knet' ),
                            'type'        => 'file',
                            'desc_tip'    => false,
                            'description' => sprintf('<img src="%s" alt="%s" title="%s"  width="50"/>', $this->icon, __( 'Please upload the icon For Payment.', 'wc-cbk' ), __( 'Please upload the icon For Payment.', 'wc-cbk' )),
                            'default'     => $this->icon,
                        ),
                    'complete_order_status' => array(
                        'title' => __('Complete Order Status', 'wc-knet'),
                        'description' => __('The state to which the request is transferred upon successful', 'wc-knet'),
                        'type' => 'select',
                        'default' => "completed",
                        'options' => $this->getOrderStatusList()
                    ),
                    'pending_order_status' => array(
                        'title' => __('Pending Payment Order Status', 'wc-knet'),
                        'description' => __('The state to which the application is transferred when moving to the payment gateway', 'wc-knet'),
                        'type' => 'select',
                        'default' => "on-hold",
                        'options' => $this->getOrderStatusList(true)
                    ),
                    
                    'exchange' => [
                        'title' => __('Currency exchange rate ', 'wc-knet'),
                        'type' => 'number',
                        'custom_attributes' => array('step' => 'any', 'min' => '0'),
                        'description' => __('It is the rate of multiplying the currency account in the event that the base currency of the store is not the Kuwaiti dinar', 'wc-knet') . " " . __('KWD = exchange rate * amount(USD)', 'wc-knet'),
                        'default' => 1,
                        'desc_tip' => false,
                    ],
                    'commission' => [
                        'title' => __('Payment commission', 'wc-knet'),
                        'type' => 'number',
                        'custom_attributes' => array('step' => 0.100, 'min' => 0),
                        'description' => __('Charge the transfer commission to the customer. If you want to bear it, leave a zero value', 'wc-knet'),
                        'default' => 0,
                        'desc_tip' => false,
                    ],
                    'tranportal_id' => array(
                        'title' => __('Tranportal Id', 'wc-knet'),
                        'type' => 'text',
                        'label' => __('Necessary data requested from the bank ', 'wc-knet'),
                        'default' => ''
                    ),
                    'password' => array(
                        'title' => __('Transportal Password', 'wc-knet'),
                        'type' => 'password',
                        'description' => __('Necessary data requested from the bank ', 'wc-knet'),
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    'resource_key' => array(
                        'title' => __('Terminal Resource Key', 'wc-knet'),
                        'type' => 'password',
                        'description' => __('Necessary data requested from the bank', 'wc-knet'),
                        'default' => '',
                        'desc_tip' => false,
                    ),
                    'lang' => [
                        'title' => __('Language', 'wc-knet'),
                        'type' => 'select',
                        'description' => __('payment page lang', 'wc-knet'),
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
            * @since 2.11.0
            */
            public function process_admin_options() {
                $this->upload_key_files();
                $saved = parent::process_admin_options();
                return $saved;
            }
            /**
            * @since 2.11.0
            */
            private function upload_key_files() {
        
                $file = $_FILES['woocommerce_'.$this->id.'_logo'] ?? null;
            
                if(!empty($file) && $file['name'] != ''){
                    $upload_dir = plugin_dir_path( __FILE__ ).$this->upload_dir;
                    if (!empty($upload_dir) ) {
                        $user_dirname = $upload_dir;
                        if (!file_exists( $user_dirname ) ) {
                            wp_mkdir_p( $user_dirname );
                        }
                        $filename = wp_unique_filename( $user_dirname, str_replace([' '],"_",$file['name']));
                        if(move_uploaded_file($file['tmp_name'], $user_dirname .DIRECTORY_SEPARATOR. $filename)){
                            $_POST['woocommerce_'.$this->id.'_logo'] = $filename;
                            if($this->logo !== 'knet-logo.png'){
                                @unlink($user_dirname .DIRECTORY_SEPARATOR .$this->logo);
                            }
                        }
                    }
                }else{
                   $_POST['woocommerce_'.$this->id.'_logo'] = $this->logo; 
                }
            }
            /**
             * Admin Panel Options
             * - Options for bits like 'title', 'description', 'alias'
             **/
            public function admin_options()
            {
                echo wp_kses('<h3>' . __('KNET', 'wc-knet') . '</h3>', ["h3" => []]);
                echo wp_kses('<p>' . __('KNET', 'wc-knet') . '</p>', ["p" => []]);
                echo wp_kses('<table class="form-table">', ["table" => ["class" => []]]);
                $this->generate_settings_html();
                echo wp_kses('</table>', ["table" => []]);
                
            }
            
            /**
             * Process payment
             * return array
             * status,pay url
             * 1- get request data (pay url)
             * 2- Mark as $this->pending_order_status (we're awaiting the cheque)
             * 3- Remove cart
             * 4- Return thankyou redirect
             * 5- or failed pay
             * @param $order_id
             * @return array
             */
            public function process_payment($order_id)
            {
                
                $order = new WC_Order($order_id);
                if (!$order->get_id()) {
                    wc_add_notice(__("Order not found", "wc-knet"), 'error');
                    
                    return array(
                        'result' => 'error',
                        'redirect' => $this->get_return_url($order)
                    );
                }
                //get request data (pay url)
                $request = $this->request($order);
                
                // Mark as $this->pending_order_status (we're awaiting the cheque)
                $order->update_status($this->pending_order_status, __('Awaiting cheque payment', 'wc-knet'));
                
                // Remove cart
                //$woocommerce->cart->empty_cart();
                
                if (!empty($request)) {
                    // Return thankyou redirect
                    return array(
                        'result' => 'success',
                        'redirect' => $request['url']
                    );
                } else {
                    $order->add_order_note(__('Payment error:', 'wc-knet') . __("Knet can't get data", 'wc-knet'), 'error');
                    $order->update_status('failed');
                    return array(
                        'result' => 'error',
                        'redirect' => $this->get_return_url($order)
                    );
                }
                
            }
            
            /**
             * return pay url to redirect to knet gateway website
             * return array
             *
             * @param $order
             *
             * @return array
             */
            public function request($order)
            {
                $this->formatUrlParams($order);
                
                $param = $this->encryptAES($this->paymentUrl, $this->resource_key) . "&tranportalId=" . $this->tranportal_id . "&responseURL=" . $this->ste_response_url() . "&errorURL=" . $this->errorURL;
                $payURL = $this->GatewayUrl . "kpg/PaymentHTTP.htm?param=paymentInit" . "&trandata=" . $param;
                
                return [
                    'status' => 'success',
                    'url' => $payURL,
                    'payment_id' => $this->trackId
                ];
            }
            
            /**
             * @return string
             * @since 2.10.0
             */
            private function ste_response_url(): string
            {
                return ($this->is_redirect_mode === "yes") ? $this->redirectURL : $this->responseURL;
            }
            /**
             * prepare pay url parames to kent
             * this update pay url var
             *
             * @param $order
             */
            private function formatUrlParams($order)
            {
                $user_id = $order->get_user_id();
                $user_info = $order->get_user();
                
                $get_billing_first_name = $order->get_billing_first_name();
                $this->name = (!empty($get_billing_first_name)) ? trim($get_billing_first_name) : (($user_id && $user_info) ? trim($user_info->user_login) : "");
                $this->name = (!preg_match('/[^A-Za-z0-9]/', $this->name))? $this->name : "";
                
                $billing_email = $order->get_billing_email();
                $this->email = (!empty($billing_email)) ? $billing_email : (($user_id && $user_info) ? $user_info->user_email : "");
                
                $billing_phone = $order->get_billing_phone();
                $this->mobile = (!empty($billing_phone)) ? $billing_phone : (($user_id && $user_info) ? $user_info->user_phone : "");
                $this->mobile = (int)$this->mobile;
                
                $this->trackId = time() . mt_rand(1000, 100000);
                $replace_array = array();
                $replace_array['{id}'] = $this->tranportal_id;
                $replace_array['{password}'] = $this->password;
                $replace_array['{amt}'] = $this->getTotalAmount($order);
                $replace_array['{trackid}'] = $this->trackId;
                $replace_array['{responseURL}'] = $this->ste_response_url();
                $replace_array['{errorURL}'] = $this->errorURL;
                $replace_array['{lang}'] = $this->lang;
                $replace_array['{udf1}'] = $order->get_id();
                $replace_array['{udf2}'] = $this->name;
                $replace_array['{udf3}'] = $this->mobile;
                if (isset($this->is_kfast) && $this->is_kfast == 'yes' && isset($user_id) && !empty($user_id) && strlen($user_id) <= 8) {
                    $replace_array['{udf3}'] = $this->getUserKfastToken($user_id);
                }
                $replace_array['{udf4}'] = $this->mobile;
                $replace_array['{udf5}'] = $this->email;
                @WC()->session->set('alnazer_knet_payment_order_id', $order->get_id());
                $this->paymentUrl = str_replace(array_keys($replace_array), array_values($replace_array), $this->paymentUrl);
            }
            
            /**
             * update order after responce Done from knet
             * return string
             * url for order view
             */
            public function updateOrder()
            {
                // define response data
                $responseData = $this->responce();
               
                if ($responseData) {
                   
                    $order_id = $responseData["udf1"];
                   
                    $status = $responseData["status"];
                    $tranid = $responseData["tranid"];
                    $ref = $responseData["ref"];
                    $paymentid = $responseData["paymentid"];
                    $trackid = $responseData["trackid"];
                    $result = $responseData["result"];
                    $ErrorText = $responseData["ErrorText"];
                    $Error = $responseData["Error"];
                    try {
                        $order = new WC_Order($order_id);
                    }catch (\Exception $e){
                        wc_add_notice(__($e->getMessage(), "wc-knet"), 'error');
                        return wc_get_cart_url();
                    }

                    $transation_data = [
                        "payment_id" => $paymentid,
                        "track_id" => $trackid,
                        "tran_id" => $tranid,
                        "ref_id" => $ref,
                        "result" => $result,
                        'status' => ($result == "CAPTURED") ? WC_PAYMENT_STATUS_SUCCESS : WC_PAYMENT_STATUS_FAIL,
                        "amount" => $responseData["ammount"],
                        "data" => $responseData["data"],
                        'error' => $ErrorText,
                    ];
                  
                    
                    if (!$order->get_id()) {
                        wc_add_notice(__("Order not found", "wc-knet"), 'error');
                        return wc_get_cart_url();
                    } else {
                        //add order meta
                        $order->update_meta_data('payment_id', $paymentid);
                        $order->update_meta_data('track_id', $trackid);
                        $order->update_meta_data('transaction_id', $tranid);
                        $order->update_meta_data('refrance_id', $ref);
                    }
                    $knetInformation = __('Result', 'wc-knet') . "           : $result\n";
                    $knetInformation .= __('Payment id', 'wc-knet') . "       : $paymentid\n";
                    $knetInformation .= __('track id', 'wc-knet') . "         : $trackid\n";
                    $knetInformation .= __('Transaction id', 'wc-knet') . "   : $tranid\n";
                    $knetInformation .= __('Reference id', 'wc-knet') . "      : $ref\n";
			// insert transation
                    do_action("alnazer_wc_knet_create_new_transaction", $order, $transation_data);
                    if (isset($status) && $status == "success") {
                        switch ($result) {
                            case 'CAPTURED':
                                $order->update_status($this->complete_order_status);
                                if ($this->complete_order_status == 'completed') {
                                    $order->payment_complete();
                                }
                                break;
                            case 'CANCELED':
                                $order->update_status('cancelled');
                                break;
                            default:
                                $order->update_status('failed');
                                break;
                        }
                        
                    } elseif (isset($status) && $status == "error") {
                        $knetInformation .= __('Error', 'wc-knet') . "            : $Error\n";
                        $knetInformation .= __('Error Message', 'wc-knet') . "    : $ErrorText\n";
                        $order->update_status('failed');
                    }
                    $order->add_order_note($knetInformation);
                   
                    return $this->get_return_url($order);
                }
                return  "";
            }
            /**
             * @since 2.10.0
             */
            public function decryptTransData($text){
               
                $decrytedData = $this->decrypt($text, $this->resource_key);
                parse_str($decrytedData, $output);
                return $output;
            }
            /**
             * get responce came from kney payment
             * return array()
             */
            private function responce()
            {
                
                $ResErrorText = (isset($_REQUEST['ErrorText'])) ? sanitize_text_field($_REQUEST['ErrorText']) : null;        //Error Text/message
                $ResPaymentId = (isset($_REQUEST['paymentid'])) ? sanitize_text_field($_REQUEST['paymentid']) : null;        //Payment Id
                $ResTrackID = (isset($_REQUEST['trackid'])) ? sanitize_text_field($_REQUEST['trackid']) : null;        //Merchant Track ID
                $ResErrorNo = (isset($_REQUEST['Error'])) ? sanitize_text_field($_REQUEST['Error']) : null;           //Error Number
                //$ResResult      =   (isset($_REQUEST['result']))    ? sanitize_text_field($_REQUEST['result']) : null;           //Transaction Result
                $ResPosdate = (isset($_REQUEST['postdate'])) ? sanitize_text_field($_REQUEST['postdate']) : null;         //Postdate
                $ResTranId = (isset($_REQUEST['tranid'])) ? sanitize_text_field($_REQUEST['tranid']) : null;         //Transaction ID
                $ResAuth = (isset($_REQUEST['auth'])) ? sanitize_text_field($_REQUEST['auth']) : null;               //Auth Code
                $ResAVR = (isset($_REQUEST['avr'])) ? sanitize_text_field($_REQUEST['avr']) : null;                //TRANSACTION avr
                $ResRef = (isset($_REQUEST['ref'])) ? sanitize_text_field($_REQUEST['ref']) : null;                //Reference Number also called Seq Number
                $ResAmount = (isset($_REQUEST['amt'])) ? sanitize_text_field($_REQUEST['amt']) : null;             //Transaction Amount
                $Resudf1 = (isset($_REQUEST['udf1'])) ? sanitize_text_field($_REQUEST['udf1']) : null;              //UDF1
                $Resudf2 = (isset($_REQUEST['udf2'])) ? sanitize_text_field($_REQUEST['udf2']) : null;               //UDF2
                $Resudf3 = (isset($_REQUEST['udf3'])) ? sanitize_text_field($_REQUEST['udf3']) : null;                //UDF3
                $Resudf4 = (isset($_REQUEST['udf4'])) ? sanitize_text_field($_REQUEST['udf4']) : null;    //UDF4
                $Resudf5 = (isset($_REQUEST['udf5'])) ? sanitize_text_field($_REQUEST['udf5']) : null;    //UDF5
                if ($ResErrorText == null && $ResErrorNo == null && $ResPaymentId != null) {
                    // success
                    $ResTranData = (isset($_REQUEST['trandata'])) ? sanitize_text_field($_REQUEST['trandata']) : null;
                    $output = $this->decryptTransData($ResTranData);
                    
                    if ($ResTranData != null) {
                        $result['status'] = 'success';
                        $result['paymentid'] = $output['paymentid'];
                        $result['trackid'] = $output['trackid'];
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
                        $result['data'] = sanitize_text_field(http_build_query($output));
                        $result['ErrorText'] = $ResErrorText;        //Error
                        $result['Error'] = $ResErrorNo;
                    } else {
                        $result['status'] = 'error';
                        $result['paymentid'] = $ResPaymentId;
                        $result['trackid'] = $ResTrackID;
                        $result['tranid'] = $ResTranId;
                        $result['ref'] = $ResRef;
                        $result['result'] = 'error';
                        $result['data'] = sanitize_text_field(http_build_query($_REQUEST));
                        $result['postdate'] = $ResPosdate;
                        $result['auth'] = $ResAuth;
                        $result['avr'] = $ResAVR;                 //TRANSACTION avr
                        $result['ammount'] = $ResAmount;              //Transaction Amount
                        $result['udf1'] = $Resudf1 ?? @WC()->session->get('alnazer_knet_payment_order_id');             //UDF1
                        $result['udf2'] = $Resudf2;               //UDF2
                        $result['udf3'] = $Resudf3;               //UDF3
                        $result['udf4'] = $Resudf4;               //UDF4
                        $result['udf5'] = $Resudf5;
                        $result['ErrorText'] = $ResErrorText;        //Error
                        $result['Error'] = $ResErrorNo;
                    }
                    
                } else {
                    // error
                    $result['status'] = 'error';
                    $result['paymentid'] = $ResPaymentId;
                    $result['trackid'] = $ResTrackID;
                    $result['tranid'] = $ResTranId;
                    $result['ref'] = $ResRef;
                    $result['result'] = 'error';
                    $result['data'] = sanitize_text_field(http_build_query($_REQUEST));
                    $result['ErrorText'] = $ResErrorText;        //Error
                    $result['Error'] = $ResErrorNo;           //Error Number
                    $result['postdate'] = $ResPosdate;        //Postdate
                    $result['auth'] = $ResAuth;               //Auth Code
                    $result['avr'] = $ResAVR;                 //TRANSACTION avr
                    $result['ammount'] = $ResAmount;              //Transaction Amount
                    $result['udf1'] = $Resudf1 ?? @WC()->session->get('alnazer_knet_payment_order_id');                 //UDF1
                    $result['udf2'] = $Resudf2;               //UDF2
                    $result['udf3'] = $Resudf3;               //UDF3
                    $result['udf4'] = $Resudf4;               //UDF4
                    $result['udf5'] = $Resudf5;
                }
                
                return $result;
            }
            
            /** ====================== Order functions =======
             * tis all function modfide order
             */
            
            /** get ammount after exchange
             *
             * @param $order
             *
             * @return float|int
             */
            private function getTotalAmount($order)
            {
                if ($this->currency == "KWD") {
                    return $order->get_total() + (float)$this->commission;
                } elseif (!empty($this->exchange) && $this->exchange > 0) {
                    return ($order->get_total() + (float)$this->commission) * $this->exchange;
                }
                
                return $order->get_total();
                
            }
            
            /**
             * @return array
             */
            private function getOrderStatusList($is_pending = false)
            {
                $list = wc_get_order_statuses();
                $in_array = ($is_pending) ? ['pending', 'processing', "on-hold"] : ['processing', 'completed'];
                $array = [];
                if ($list) {
                    foreach ($list as $key => $value) {
                        $_key = str_replace("wc-", "", $key);
                        if (in_array($_key, $in_array)) {
                            $array[$_key] = $value;
                        }
                    }
                    return $array;
                }
                return [];
                
            }
            
            /**
             * get is kfast by static method
             * @return string
             */
            public static function get_is_kfast()
            {
                return (new self)->is_kfast;
            }
            
            /**
             * hide gateways in test mode
             *
             * @param $available_gateways
             *
             * @return mixed
             */
            public function wc_conditional_payment_gateways($available_gateways)
            {
                
                if (is_admin()) {
                    return $available_gateways;
                }
                if ($this->is_test == "yes") {
                    $wp_get_current_user = wp_get_current_user();
                    if (isset($wp_get_current_user)) {
                        if (!in_array("shop_manager", $wp_get_current_user->roles) && !in_array("administrator", $wp_get_current_user->roles)) {
                            unset($available_gateways[$this->id]);
                        }
                    }
                }
                
                return $available_gateways;
            }
            
            public function wc_woocommerce_gateway_title($title, $gateway_id)
            {
                if (!is_admin()) {
                    if ($this->is_test === "yes" && $this->id == $gateway_id) {
                        $title = sprintf("%s <span style='color: red'>%s</span>", $title, __("Test Mode", "wc-knet"));
                    }
                }
                
                return $title;
            }
            
            public function wc_woocommerce_gateway_description($description, $gateway_id)
            {
                if (!is_admin()) {
                    if ($this->commission > 0 && $this->id == $gateway_id) {
                        $description = sprintf("%s  <b>%s</b> <br/> %s", __("(+) transfer fee", "wc-knet"), $this->commission, $description);
                    }
                }
                
                return $description;
            }
            
            /**
             * display html table KNET details in received order page
             *
             * @param $order
             */
            public function wc_knet_details($order)
            {
                
                if ($order->get_payment_method() != $this->id) {
                    return;
                }
                $knet_details = alnazer_wc_get_transaction_by_order_id($order->get_id());
                
                if (!$knet_details) {
                    return;
                }
                $output = $this->format_email($order, $knet_details, "knet-details.html");
                
                echo wp_kses($output, $this->html_allow);
                
            }
            
            /**
             * display html table KNET details in email message
             *
             * @param $order
             * @param $is_admin
             * @param $text_plan
             */
            public function wc_knet_email_details($order, $is_admin, $text_plan)
            {
                if ($order->get_payment_method() != $this->id) {
                    return;
                }
                $knet_details = alnazer_wc_get_transaction_by_order_id($order->get_id());
                if (!$knet_details) {
                    return;
                }
                if ($text_plan) {
                    $output = $this->format_email($order, $knet_details, "emails/knet-text-details.html");
                } else {
                    $output = $this->format_email($order, $knet_details, "emails/knet-html-details.html");
                }
                echo wp_kses($output, $this->html_allow);
            }
            
            /**
             * format email knet details html table
             *
             * @param $order
             * @param $knet_detials
             * @param string $template
             *
             * @return mixed
             */
            private function format_email($order, $knet_detials, $template = "knet-details.html")
            {
                $template = file_get_contents(plugin_dir_path(__FILE__) . $template);
                $replace = [
                    "{icon}" => plugin_dir_url(__FILE__) . "assets/knet-logo.png",
                    "{title}" => __("Knet details", "wc-knet"),
                    "{payment_id}" => ($knet_detials->payment_id) ? $knet_detials->payment_id : "---",
                    "{track_id}" => ($knet_detials->track_id) ? $knet_detials->track_id : "---",
                    "{amount}" => ($knet_detials->amount) ? $knet_detials->amount : "---",
                    "{tran_id}" => ($knet_detials->tran_id) ? $knet_detials->tran_id : "---",
                    "{ref_id}" => ($knet_detials->ref_id) ? $knet_detials->ref_id : "---",
                    "{created_at}" => ($knet_detials->created_at) ? wp_date("F j, Y H:i:s A", strtotime($knet_detials->created_at)) : "---",
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_detials->result),
                ];
                $replace_lang = [
                    "_lang(result)" => __("Result", "wc-knet"),
                    "_lang(payment_id)" => __("Payment id", "wc-knet"),
                    "_lang(trnac_id)" => __("Transaction id", "wc-knet"),
                    "_lang(track_id)" => __("Tracking id", "wc-knet"),
                    "_lang(amount)" => __("Amount", "wc-knet"),
                    "_lang(ref_id)" => __("Refrance id", "wc-knet"),
                    "_lang(created_at)" => __('Created at', "wc-knet"),
                    "{result}" => sprintf("<b><span style=\"color:%s\">%s</span></b>", $this->get_status_color($order->get_status()), $knet_detials->result),
                ];
                $replace = array_merge($replace, $replace_lang);
                
                return str_replace(array_keys($replace), array_values($replace), $template);
            }
            
            /**
             * add colored order status in received page
             *
             * @param $str
             *
             * @return string
             */
            public function wc_woo_change_order_received_text($str)
            {
                global $id;
                $order = $this->get_order_in_recived_page($id, true);
                $order_status = $order->get_status();
                
                return sprintf("%s <b><span style=\"color:%s\">%s</span></b>.", __("Thank you. Your order has been", "wc-knet"), $this->get_status_color($order_status), __(ucfirst($order_status), "woocommerce"));
            }
            
            /**
             * add colored order status in received page
             *
             * @param $old_title
             *
             * @return string
             */
            public function wc_thank_you_title($old_title)
            {
                global $id;
                $order_status = $this->get_order_in_recived_page($id);
                
                if (isset ($order_status)) {
                    return sprintf("%s , <b><span style=\"color:%s\">%s</span></b>", __('Order', "wc-knet"), $this->get_status_color($order_status), esc_html(__(ucfirst($order_status), "woocommerce")));
                }
                
                return $old_title;
            }
            
            /**
             * get order details in received page
             *
             * @param $page_id
             * @param bool $return_order
             *
             * @return bool|string|WC_Order
             */
            private function get_order_in_recived_page($page_id, $return_order = false)
            {
                global $wp;
                 if (get_the_ID() === $page_id && !empty($wp->query_vars['order-received'])) {
                    $order_id = apply_filters('woocommerce_thankyou_order_id', absint($wp->query_vars['order-received']));
                    $order_key = apply_filters('woocommerce_thankyou_order_key', empty($_GET['key']) ? '' : wc_clean($_GET['key']));
                    if ($order_id > 0) {
                        $order = new WC_Order($order_id);
                        
                        if ($order->get_order_key() != $order_key) {
                            $order = false;
                        }
                        if ($return_order) {
                            return $order;
                        }
                        
                        return $order->get_status();
                    }
                }
                
                return false;
            }
            
            /**
             * set status color
             *
             * @param $status
             *
             * @return string
             */
            private function get_status_color($status)
            {
                switch ($status) {
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
             * generate kfast token
             */
            private function generateUserKfastToken($length = 8)
            {
                return str_pad(mt_rand(1, 99999999), $length, '0', STR_PAD_LEFT);
            }
            
            private function getUserKfastToken($user_id)
            {
                $userToken = get_user_meta($user_id, $this->kfast_token_key, true);
                if (isset($userToken) && !empty($userToken) && is_numeric($userToken)) {
                    return $userToken;
                }
                $userToken = $this->generateUserKfastToken();
                while ($this->isKfateExiste($userToken)) {
                    $userToken = $this->generateUserKfastToken();
                }
                add_user_meta($user_id, $this->kfast_token_key, $userToken, true);
                
                return $userToken;
            }
            
            private function isKfateExiste($value)
            {
                global $wpdb;
                $table = $wpdb->prefix . 'usermeta';
                
                return $wpdb->get_var("SELECT COUNT(*) FROM `$table` WHERE `meta_key`='$this->kfast_token_key' AND `meta_value`=$value  ");
            }
            
            
            /** ======== Payment Encrypt Functions Started ======
             * this functions created by knet developer don't change anything
             */
            /**
             * @param $str
             * @param $key
             *
             * @return string
             */
            public function encryptAES($str, $key)
            {
                $str = $this->pkcs5_pad($str);
                $encrypted = openssl_encrypt($str, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $key);
                $encrypted = base64_decode($encrypted);
                $encrypted = unpack('C*', ($encrypted));
                $encrypted = $this->byteArray2Hex($encrypted);
                $encrypted = urlencode($encrypted);
                
                return $encrypted;
            }
            
            /**
             * @param $text
             *
             * @return string
             */
            public function pkcs5_pad($text)
            {
                $block_size = 16;
                $pad = $block_size - (strlen($text) % $block_size);
                
                return $text . str_repeat(chr($pad), $pad);
            }
            
            /**
             * @param $byteArray
             *
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
             *
             * @return bool|string
             */
            public function decrypt($code, $key)
            {
                $code = $this->hex2ByteArray(trim($code));
                $code = $this->byteArray2String($code);
                $iv = $key;
                $code = base64_encode($code);
                $decrypted = openssl_decrypt($code, 'AES-128-CBC', $key, OPENSSL_ZERO_PADDING, $iv);
                
                return $this->pkcs5_unpad($decrypted);
            }
            
            /**
             * @param $hexString
             *
             * @return array
             */
            public function hex2ByteArray($hexString)
            {
                $string = hex2bin($hexString);
                
                return unpack('C*', $string);
            }
            
            /**
             * @param $byteArray
             *
             * @return string
             */
            public function byteArray2String($byteArray)
            {
                $chars = array_map("chr", $byteArray);
                
                return join($chars);
            }
            
            /**
             * @param $text
             *
             * @return bool|string
             */
            public function pkcs5_unpad($text)
            {
                
                $index = (strlen($text) - 1);
                $pad = ord($text[$index]);
                
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
     *
     * @param $methods
     *
     * @return mixed
     */
    if (!function_exists("woocommerce_add_wc_knet_gateway")) {
        function woocommerce_add_wc_knet_gateway($methods)
        {
            global $WC_Payment_KNET_CLASS_NAME;
            
            $methods[] = $WC_Payment_KNET_CLASS_NAME;
            
            return $methods;
        }
    }
    
    add_filter('woocommerce_payment_gateways', 'woocommerce_add_wc_knet_gateway');
    
    /**
     * load plugin language
     *
     * @param $mofile
     * @param $domain
     *
     * @return string
     */
    if (!function_exists("wc_knet_load_textdomain")) {
        function wc_knet_load_textdomain()
        {
            return load_plugin_textdomain('wc-knet', false, basename(dirname(__FILE__)) . '/languages/');
        }
    }
    
    add_action('plugins_loaded', 'wc_knet_load_textdomain');
    
    
    /**
     * add knet response query var
     */
    add_filter('query_vars', function ($query_vars) {
        $query_vars[] = 'knet_response';
        $query_vars[] = 'wc_knet_export';
        
        return $query_vars;
    });
    /**
     * define knet response
     */
    add_action("wp", function ($request) {
        
        if (isset($request->query_vars['knet_response']) && null !== sanitize_text_field($request->query_vars['knet_response'])) {
            $parse_url = parse_url($request->query_vars['knet_response']);
            if (is_array($parse_url)) {
                $WC_Gateway_Knet = new WC_Payment_Gateway_Knet();
                if( $parse_url['path'] === "redirect"){
                    if(isset($_POST) && count($_POST) > 0){
                        wc_knet_handel_redirect_send_post_data_order($WC_Gateway_Knet);
                    }
                    // redirect to success url
                    wc_knet_handel_redirect_send_plan_text_data_order($WC_Gateway_Knet);
                    die;
                    
                }elseif($parse_url['path'] === 'success'){
                    wc_knet_handel_redirect_send_post_data_order($WC_Gateway_Knet);
                    die;
                }
                exit;
            }
            
        }
        
    });
    /**
     * @param $WC_Gateway_Knet
     * @return void
     * @senice 2.10.0
     */
    function wc_knet_handel_redirect_send_post_data_order($WC_Gateway_Knet){
        $url = $WC_Gateway_Knet->updateOrder();
        if (wp_redirect($url)) {
            exit;
        }
    }
    
    /**
     * @param $WC_Gateway_Knet
     * @return void
     * @senice 2.10.0
     */
    function wc_knet_handel_redirect_send_plan_text_data_order($WC_Gateway_Knet){

        $response_trans_data = file_get_contents('php://input');
        
        if(empty($response_trans_data)){
            wc_add_notice(__("no data send form KNET", "wc-knet"), 'error');
            echo "REDIRECT=".wc_get_cart_url().'?paymentID=';
            exit;
        }
        
        $trans_data = $WC_Gateway_Knet->decryptTransData($response_trans_data);
        if(is_array($trans_data)){
            foreach($trans_data as $key => $value){
                $_REQUEST[$key] = $value;
            }
            $_REQUEST['trandata'] = $response_trans_data;
        }
        
        if(empty($_REQUEST['paymentid'])){
            wc_add_notice(__("Payment id is required", "wc-knet"), 'error');
            echo "REDIRECT=".wc_get_cart_url().'?paymentID=';
            exit;
        }
        
        $url = $WC_Gateway_Knet->updateOrder();
        echo "REDIRECT=".$url.'&paymentID='.$_REQUEST['paymentid'];
        exit;
    }
    /**
     * export files
     */
    add_action("admin_init", function () {
        $action = esc_attr($_GET["wc_knet_export"] ?? "");
        if (is_admin()) {
            if (sanitize_text_field($action) == "excel") {
                $rows = wc_knet_payment_trans_grid::get_transations(1000);
                $list[] = [
                    __('Order', "wc-knet"),
                    __('Customer Name', "woocommerce"),
                    __('Customer Email', "woocommerce"),
                    __('Customer Mobile', "woocommerce"),
                    __('Status', "wc-knet"),
                    __('Result', "wc-knet"),
                    __('Amount', "wc-knet"),
                    __('Payment id', "wc-knet"),
                    __('Tracking id', "wc-knet"),
                    __('Transaction id', "wc-knet"),
                    __('Reference id', "wc-knet"),
                    __('Created at', "wc-knet")
                ];
                if ($rows) {
                    foreach ($rows as $row) {
                        $order = null;
                        if (isset($row['order_id'])) {
                            $order = wc_get_order($row['order_id']);
                        }
                        $list[] = [
                            $row['order_id'],
                            (!empty($order)) ? $order->get_formatted_billing_full_name() : "---",
                            (!empty($order)) ? $order->get_billing_email() : "---",
                            (!empty($order)) ? $order->get_billing_phone() : "---",
                            __($row['status'], "wc_kent"),
                            $row['result'],
                            $row['amount'],
                            $row['payment_id'],
                            $row['track_id'],
                            $row['tran_id'],
                            $row['ref_id'],
                            $row['created_at']
                        ];
                    }
                }
                $xlsx = SimpleXLSXGen::fromArray($list);
                $xlsx->downloadAs(date("YmdHis") . '.xlsx'); // or downloadAs('books.xlsx') or $xlsx_content = (string) $xlsx
                exit();
            } elseif (sanitize_text_field($action) == "csv") {
                
                $rows = wc_knet_payment_trans_grid::get_transations(1000);
                if ($rows) {
                    $filename = date('YmdHis') . ".csv";
                    $f = fopen('php://memory', 'w');
                    $delimiter = ",";
                    $head = [
                        __('Order', "wc-knet"),
                        __('Customer Name', "woocommerce"),
                        __('Customer Email', "woocommerce"),
                        __('Customer Mobile', "woocommerce"),
                        __('Status', "wc-knet"),
                        __('Result', "wc-knet"),
                        __('Amount', "wc-knet"),
                        __('Payment id', "wc-knet"),
                        __('Tracking id', "wc-knet"),
                        __('Transaction id', "wc-knet"),
                        __('Reference id', "wc-knet"),
                        __('Created at', "wc-knet")
                    ];
                    fputcsv($f, $head, $delimiter);
                    foreach ($rows as $row) {
                        $order = null;
                        if (isset($row['order_id'])) {
                            $order = wc_get_order($row['order_id']);
                        }
                        $listData = [
                            $row['order_id'],
                            (!empty($order)) ? $order->get_formatted_billing_full_name() : "---",
                            (!empty($order)) ? $order->get_billing_email() : "---",
                            (!empty($order)) ? $order->get_billing_phone() : "---",
                            __($row['status'], "wc_kent"),
                            $row['result'],
                            $row['amount'],
                            $row['payment_id'],
                            $row['track_id'],
                            $row['tran_id'],
                            $row['ref_id'],
                            $row['created_at']
                        ];
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
    register_activation_hook(__FILE__, 'alnazer_create_transactions_db_table');
    
    /**
     * notify is currency not KWD
     */
    add_action('admin_notices', 'alnazer_wc_knet_is_currency_not_kwd');
    if (!function_exists("alnazer_wc_knet_is_currency_not_kwd")) {
        function alnazer_wc_knet_is_currency_not_kwd()
        {
            $currency = get_option('woocommerce_currency');
            if (isset($currency) && $currency != "KWD") {
                echo '<div class="notice notice-warning is-dismissible">
				<p>' . __("currency must be KWD when using this knet payment", "wc-knet") . '</p>
			</div>';
            }
        }
    }
    
    /** notify is gust can add checkout */
    add_action('admin_notices', 'alnazer_wc_kfast_guest_can_checkout');
    if (!function_exists("alnazer_wc_kfast_guest_can_checkout")) {
        function alnazer_wc_kfast_guest_can_checkout()
        {
            $guest_checkout = get_option('woocommerce_enable_guest_checkout');
            if (isset($guest_checkout) && $guest_checkout == "yes") {
                if (WC_Payment_Gateway_Knet::get_is_kfast() == "yes") {
                    echo sprintf("<div class=\"notice notice-warning is-dismissible\">
							<p>%s</p>
						</div>", __("The KFAST feature may not work with all customers because you allow non-customers to make payments", "wc-knet"));
                }
                
            }
        }
    }
    
    /**
     *Add style css file
     */
    function alnazer_wc_define_assets()
    {
        wp_enqueue_style('alnazer-wc-style', WC_ASSETS_PATH . "/css/wc.style.css");
        wp_enqueue_script('alnazer-wc-script', WC_ASSETS_PATH . "/js/wc.script.js");
    }
    
    add_action('admin_enqueue_scripts', 'alnazer_wc_define_assets');

