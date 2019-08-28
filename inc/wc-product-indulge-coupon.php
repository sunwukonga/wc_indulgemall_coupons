
/*
 * Create a new custom product type: Indulge Coupon
 */
function wcimc_register_indulge_coupon_type () {
  class WC_Product_Indulge_Coupon extends WC_Product {
    public function __construct( $product ) {
      $this->product_type = 'indulge_coupon'; // name of your custom product type
      parent::__construct( $product );
      // add additional functions here
    }
  }
}
