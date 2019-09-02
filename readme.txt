=== Woocommerce Indulge Mall Coupons ===
Contributors: kingsunwukong
Requires at least: 4.0.0
Tested up to: 5.2.2
Requires PHP: 5.5.9
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate Indulge Mall Coupons with Woocommerce

== Description ==
Admin may use custom product type \"indulge_coupon\" to create products tagged for fetching Indulge Mall Coupons on status \'completed\'.

#Plugin setup
Enter settings under `Woocommerce` -> `Indulge API Settings`
  * Staging settings, live settings, and live enabled

#Coupon Creation
Create a new Indulge Mall Coupon product by:
1. Select `Product data --` -> `Product Type` -> \"Indulge Coupon\"
2. Select `Indulge Mall` tab, and
3. Fill `Indulge SKU` field with SKU supplied by Indulge Mall
4. Select `General tab,` and
5. Fill `Regular price ($)` with required pricing

#Process
  * Customer completes purchase
  * Plugin fetches coupon code and associates it with order item
  * Customer can view order (+coupon) in `My Account` -> `Orders` -> order##
  * Email (+coupon) sent to customer on payment complete
  * Admin note added to order
    - On success: Success message!
    - On failure: Error! 

#Testing
## A new installation

### A.
1. Enter staging credentials
2. Create test coupon
3. Run an order with \"Check Payment\"
4. Admin marks order \"complete\"
5. Check Woocommerce order for error notes

### B.
  * Same process above except with `live` credentials and `live enabled`

== Installation ==
* Activate plugin
* Fill in `Woocommerce` -> `Indulge API Settings` (live disabled)
* Create a product of type `Indulge Coupon` and fill `Indulge Mall` -> `Indulge SKU`
* Test on staging
* Enable live
* Test on live
