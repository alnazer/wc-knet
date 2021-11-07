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
          payment_id  varchar(100) NOT NULL,
          track_id varchar(100) NOT NULL,
          amount DECIMAL(20,3) DEFAULT 0.000 NOT NULL,
          tran_id varchar(100)  NULL,
          ref_id varchar(100)  NULL,
          status varchar(100) DEFAULT '".STATUS_FAIL."' NOT NULL,
          result varchar(100) DEFAULT '".STATUS_NEW."' NOT NULL,
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
                    'created_at' => current_time( 'mysql' ),
                ]
            );
        }
        return false;
    }catch (Exception $e){
        return false;
    }
}

add_action( 'add_meta_boxes', 'wc_knet_details_meta_boxes' );

function wc_knet_details_meta_boxes()
{
    global $post;
    if(wc_is_transation_exsite_by_order_id($post->ID))
    {
        add_meta_box( 'wc_knet_details_fields', __('Knet details','wc_knet'), 'fun_wc_knet_details_meta_boxes', 'shop_order', 'side', 'core' );
    }

}
function fun_wc_knet_details_meta_boxes(){
    global $post;
    $list = wc_get_transation_by_orderid($post->ID);
    if($list){
        $output = "<table class=\"woocommerce_order_items\" cellspacing=\"2\" cellpadding=\"2\" style='width: 100% !important;'>";
        $output .="<tbody>";
        $output .= sprintf("<tr><td style='width: 20%% !important;'><b>%s</b></td><td>%s</td></tr>",__('Result', "wc_knet"),$list->result);
        $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>",__('Payment id', "wc_knet"),$list->payment_id);
        $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>",__('Tracking id', "wc_knet"),$list->track_id);
        $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>",__('Transaction id', "wc_knet"),$list->tran_id);
        $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>",__('Refrance id', "wc_knet"),$list->ref_id);
        $output .= sprintf("<tr><td><b>%s</b></td><td>%s</td></tr>",__('Created at', "wc_knet"),$list->created_at);
        $output .= "</tbody>";
        $output .= "</table>";

        echo $output;
    }
}
function wc_is_transation_exsite($payment_id){
    global $wpdb;
    $table_name = $wpdb->prefix.WC_KNET_TABLE;
    return $wpdb->get_var("SELECT `payment_id` FROM `$table_name` WHERE `payment_id`='$payment_id' ");
}
function wc_is_transation_exsite_by_order_id($order_id){
    global $wpdb;
    $table_name = $wpdb->prefix.WC_KNET_TABLE;
    return $wpdb->get_var("SELECT `order_id` FROM `$table_name` WHERE `order_id`='$order_id' ");
}
function wc_get_transation_by_orderid($order_id){
    global $wpdb;
    $table_name = $wpdb->prefix.WC_KNET_TABLE;
    return $wpdb->get_row("SELECT * FROM `$table_name` WHERE `order_id`='$order_id' ORDER BY `id` DESC  LIMIT 1");
}
