=== Payment Gateway for KNET ===
Contributors: alnazer
Tags: K-Net, knet, knetv2, payment, kuwait, woocommerce, ecommerce, payment, gateway
Requires at least: 5.6
Tested up to: 5.9
Tested in WooCommerce : 6.3.1
Requires PHP: 7.0
Stable tag: 2.4.0
License: MIT
License URI: https://choosealicense.com/licenses/mit/



نساعدك في تطوير اعمالك الخاصه بتقديم الاضافة الجديد
الخاصة بالدفع عن طريق بوابة الكي نت بعد تحديثها
وسع دائرة عملائك باتاحة امكانية الدفع عن طريق الكي نت
==========
We help you to develop your business by introducing the new add-on
For payment through the K-Net portal, after it has been updated
Expand your customers' circle by making the payment available via Knet

## Installation

download and unzip to plugins folder
<br/>
or
From merchant’s WordPress admin

1. Go to plugin section-> Add new
2. Search for “Payment Gateway for KNET”
3. Click on Install Now
4. Click on Activate

## Usage

go to woocommerce setting in side menu and select tab payment and active knet v2 from list

## TEST
Cards
```
- Captured (Approved) Card Number: 8888880000000001
    Expiry: 09/2025
    Pin: Any Pin

- Not Captured (Declined) Card Number: 8888880000000001
    Expiry: Any Expiry
    Pin: Any Pin
```

## KFAST

To apply KFAST you must contact to KNET support to approved KFAST to your account

## Changelog

== Changelog ==
= 2.4.0 =

- add KFAST feature

= 2.3.3 =

-fixed error in php 8 

= 2.3.1 =

- add language payment page in plugin options
- only user has role [Shop manager,Administrator] allowed test payment other client cannot use gateway 
- add exchange currency rate
- transactions order by newest 
 
= 2.2.1 =

- fixed database create table error

= 2.2.0 =

- add knet transations database tables when update plugin

= 2.1.0 =

- add knet details email html&text templates
- add knet payment date
- display knet details in email

= 2.0.0 =

- add knet transations list for order
- export transations to csv,excel
- add knet payment details to thank you page
- add knet payment details to email
- add html template for knet payment details
- change licenses to mit

= 1.1.0 =

- add order status in received page.
- colored status

* ![#0470fb](https://via.placeholder.com/15/0470fb/000000?text=+) `pending`
* ![#fbbd04](https://via.placeholder.com/15/fbbd04/000000?text=+) `processing`
* ![#04c1fb](https://via.placeholder.com/15/0470fb/000000?text=+) `on-hold`
* ![#green](https://via.placeholder.com/15/green/000000?text=+) `completed`
* ![#fb0404](https://via.placeholder.com/15/fb0404/000000?text=+) `cancelled,refunded,failed`
## support
contact info email: hassanaliksa@gmail.com
 mobile : +96590033807

## License

[MIT](https://choosealicense.com/licenses/mit/)
