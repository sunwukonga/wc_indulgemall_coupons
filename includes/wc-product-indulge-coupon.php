<?php

/*
 * Create a new custom product type: Indulge Coupon
 */
function wcimc_register_indulge_coupon_type () {
  class WC_Product_Indulge_Coupon extends WC_Product_Simple {

    public function __construct( $product ) {
      $this->object_type = 'indulge_coupon'; // name of your custom product type
      parent::__construct( $product );
      // add additional functions here
    }

    /**
     * Return the product type
     * @return string
     */
    public function get_type() {
        return 'indulge_coupon';
    }

    /**
     * Indulge Coupons are a "virtual" product. No shipping is involved.
     *
     * @return boolean
     */
    public function is_virtual() {
      return true;
    }
  }
}
