jQuery(document).ready(function () {
    jQuery(".alnazer_knet_pay_fast").click(function (event) {
        event.preventDefault();
        let id = jQuery(this).val();
        let quantity = jQuery(this).closest("form").find("input[name=quantity]").val();
        if (quantity <= 0 || quantity == undefined) {
            quantity = 1;
        }
        jQuery.post(ajax_object.ajax_url, {
            'action': 'alnazer_knet_fast_pay_action',
            'id': id,
            'quantity': quantity
        }, function (response) {
            alert('Got this from the server: ' + response);
        });
        //console.log(id,quantity,ajax_object.ajax_url);
    });



});
