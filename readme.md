=== Payment Gateway for KNET ===
Contributors: alnazer
Tags: K-Net, knet, knetv2, payment, kuwait, woocommerce, ecommerce, payment, gateway
Requires at least: 5.6
Tested up to: 6.3.1
Tested in WooCommerce : 8.0.3
Requires PHP: 7.0
Stable tag: 2.10.0
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
or
From merchant’s WordPress admin

1. Go to plugin section-> Add new
2. Search for “Payment Gateway for KNET”
3. Click on Install Now
4. Click on Activate

## Usage

go to woocommerce setting in side menu and select tab payment and active knet v2 from list

## Test Cards

Captured (Approved)
Card Number : 8888880000000001
Expiry : 09/2025
Pin : Any Pin

Not Captured (Declined)
Card Number : 8888880000000001
Expiry : Any Expiry
Pin : Any Pin

## خطوات التسجيل في خدمة الدفع اون لاين (KNET)

[KNET WEBSITE](https://www.knet.com.kw/)
> **ملحوظة **
> للمشتركين في خدمة الكي نت بعد 4/9/2023 الرجاء تفعيل خاصية Enable KNET REDIRECT page

### التسجيل والاختبار

> يتم تفعيل خدمة الكي نت للدفع اون لاين من خلال التوجة الي البنك المسجلة فية حسابك البنكي

1. عليك التوجة الي البنك التابع له حسابك البنكي (مؤسسات)
2. بعد الاتفاق وتوقيع العقود ستصلك رسالة علي البريد الالكتروني المسجل في العقد تحتوي علي نموذج بطلب بعض البيانات
3. ياتي النموذج ملف ورد قم بتعبئة البيانات (عبارة عن بيانات خاصة بالبرمجة المستخدمة وبيانات التواصل الخاصة بالمحاسب ومطور الموقع)
4. سيتم الرد علي بريد المطور ببيانات التست (الاختبارية)
5. قم بضافتها في البلج ان وفعل خاصية test mode
6. قم بتجربة الدفع بالبيانات الاختبارية الموجودة في دليل الاستخدام هذا تحت بند CARDS
7. بعد نجاح عملية الربط تواصل مع فريق الدعم الفني بالكي نت لطلب بيانات التفعيل (live)

> بريد المطور هو البريد الوحيد المعتمد في المراسلات بالنسبة لفريق التطوير في الكي نت

> احرص دائما علي استخدام بريد رسمي لمؤسستك وليس بريد شخصي

### التفعيل

1.سيقوم فريق الدعم الفني للكي نت بالرد علي بريدك بطلب بعض البيانات للتاكد من الربط بصورة سليمة قم بتجهيزها وارفاقها في رد علي نفس البريد

> قم بضغطهم في ملف واحرص علي ترتيبهم بشكل صحيح

وهم
1. لقطة لشاشة صفحة عرض المنتج 
2. لقطة لشاشة سلة المشتريات (ان وجدت) 
3. لقطة لشاشة صفحة تاكيد الدفع 
4. لقطة لشاشة نتيجة عملية الدفع (في حالة النجاح والفشل) 
5. لقطة لشاشة البريد الالكتروني المرسل للمشتري (في حالة النجاح والفشل) 
6. سجل العمليات (قم بتصدير ملف اكسل لعمليات الكي نت)
 اذا تمت الربط بصورة صحيحة سيقومفريق الدعم الفني بالرد عليك برسالة بها بيانات الربط الفعلية للدفع اون لاين 
  قم باستبدال بيانات الربط الجديدة بالبيانات القديمة (الاختبارية)
  لاتنسي الغاء خاصية test mode 
   ابدا حملتك التسويقية وابدا في جنى الأرباح
    لاتنسونا من دعائكم

## Changelog

== Changelog ==

=== 2.10.0 ===

1. add new option (REDIRECT response page) for new KNET update

=== 2.9.0 ===

1. now you can choose which state of order when customer start call payment gateway


=== 2.8.0 ===

1. add order meta data (payment_id, track_id, transaction_id, refrance_id)
2. update order status when payment fail or NOT CAPTURED to failed

=== 2.7.1 ===

1. fixed error in checkout page

=== 2.7.0 ===

1. passing user billing information to KNET gateway in field udf1:5 if customer bill information empty and user login passing user info else passing empty value

=== 2.6.0 ===

1. add customer name,email and mobile to report and export file excel&csv

=== 2.5.0 ===

1. add commission
2. Display commission to payment method description

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

## kfast

To apply KFAST you must contact to KNET support to approved KFAST to your account

## support

contact info email: hassanaliksa@gmail.com
mobile : +96590033807

## License

[MIT](https://choosealicense.com/licenses/mit/)
