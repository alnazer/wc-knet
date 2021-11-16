<?php
defined( 'ABSPATH' ) || exit;
/**
 * create transactions table
 */
if(!function_exists("create_transactions_db_table")){
    function create_transactions_db_table(){

        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix.WC_PAYMENT_KNET_TABLE;
        if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $sql = "CREATE TABLE  $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            order_id int(11) NOT NULL,
            payment_id  varchar(100) NOT NULL,
            track_id varchar(100) NOT NULL,
            amount DECIMAL(20,3) DEFAULT 0.000 NOT NULL,
            tran_id varchar(100)  NULL,
            ref_id varchar(100)  NULL,
            status varchar(100) DEFAULT '".WC_PAYMENT_STATUS_FAIL."' NOT NULL,
            result varchar(100) DEFAULT '".WC_PAYMENT_STATUS_NEW."' NOT NULL,
            info text  NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            INDEX (id, order_id, payment_id, result)
        ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
            add_option( 'wc_knet_db_version', WC_PAYMENT_KNET_DV_VERSION);
        }
    }

}

/**
 * create new transation record
 * @param $data
 * @return bool|false|int
 */
add_action("wc_knet_create_new_transation","fun_wc_knet_create_new_transation", 10, 2);
if(!function_exists("fun_wc_knet_create_new_transation")){
    function fun_wc_knet_create_new_transation($order,$transation_data){
        global $wpdb;
        $table_name = $wpdb->prefix.WC_PAYMENT_KNET_TABLE;
        try {
            if(!wc_is_transation_exsite($transation_data["payment_id"])){
                return $wpdb->insert(
                    $table_name,
                    [
                        'order_id' => $order->get_id(),
                        'payment_id' => $transation_data["payment_id"],
                        'track_id' => $transation_data["track_id"],
                        'tran_id' => $transation_data["tran_id"],
                        'ref_id' => $transation_data["ref_id"],
                        'status' => $transation_data["status"],
                        'result' => $transation_data["result"],
                        'amount'=>$transation_data["amount"],
                        'info' => json_encode($transation_data),
                        'created_at' =>  date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 )),
                    ]
                );
            }
            return false;
        }catch (Exception $e){
            return false;
        }
    }

}

if(!function_exists("wc_is_transation_exsite")){
    function wc_is_transation_exsite($payment_id){
        global $wpdb;
        $table_name = $wpdb->prefix.WC_PAYMENT_KNET_TABLE;
        return $wpdb->get_var("SELECT `payment_id` FROM `$table_name` WHERE `payment_id`='$payment_id' ");
    }
}

if(!function_exists("wc_get_transation_by_orderid")){
    function wc_get_transation_by_orderid($order_id){
        global $wpdb;
        $table_name = $wpdb->prefix.WC_PAYMENT_KNET_TABLE;
        return $wpdb->get_row("SELECT * FROM `$table_name` WHERE `order_id`='$order_id' ORDER BY `id` DESC  LIMIT 1");
    }
}

