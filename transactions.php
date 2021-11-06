<?php
defined( 'ABSPATH' ) || exit;

/**
 * create transactions table
 */
function create_transactions_db_table(){
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix.WC_KNET_TABLE;
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
          id int(11) NOT NULL AUTO_INCREMENT,
          order_id int(11) NOT NULL,
          payment_id  varchar(255) NOT NULL,
          track_id varchar(255) NOT NULL,
          amount DECIMAL(20,3) DEFAULT 0.000 NOT NULL,
          tran_id varchar(255)  NULL,
          ref_id varchar(255)  NULL,
          status varchar(255) DEFAULT '".STATUS_FAIL."' NOT NULL,
          result varchar(255) DEFAULT '".STATUS_NEW."' NOT NULL,
          info text  NULL,
          created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
          PRIMARY KEY  (id),
          INDEX (id, order_id, payment_id, result)
    ) $charset_collate;";

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    add_option( 'wc_knet_db_version', WC_KNET_DV_VERSION);
}

/**
 * create new transation record
 * @param $data
 * @return bool|false|int
 */
add_action("wc_knet_create_new_transation","fun_wc_knet_create_new_transation", 10, 2);
function fun_wc_knet_create_new_transation($order,$transation_data){
    global $wpdb;
    $table_name = $wpdb->prefix.WC_KNET_TABLE;
    try {
        return $wpdb->insert(
            $table_name,
            [
                'order_id' => $order->id,
                'payment_id' => $transation_data["payment_id"],
                'track_id' => $transation_data["track_id"],
                'tran_id' => $transation_data["tran_id"],
                'ref_id' => $transation_data["ref_id"],
                'status' => $transation_data["status"],
                'result' => $transation_data["result"],
                'amount'=>$transation_data["amount"],
                'info' => json_encode($transation_data),
                'created_at' => date("Y-m-d H:i:s"),
            ]
        );
    }catch (Exception $e){
        return false;
    }
}

