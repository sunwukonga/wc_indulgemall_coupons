<?php
defined( 'ABSPATH' ) or die( 'Nope, not accessing this' );
/*
Plugin Name: WooCommerce Indulge Mall Coupons
Plugin URI:
Description: Fetches Indulge Mall coupons on payment success and updates order
Version:     1.0.0
Author:      Paul Desmond Parker
Author URI:  https://github.com/sunwukonga
WC requires at least: 3.0.0
WC tested up to: 3.7.0
License:     GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Glossary: wcimc = WooCommerce Indulge Mall Coupons
*/

if ( ! class_exists( 'WC_Indulgemall_Coupons' ) ) {
  class WC_Indulgemall_Coupons {

    //magic function (triggered on initialization)
    public function __construct(){
    //    add_action('admin_enqueue_scripts', array($this,'enqueue_admin_scripts_and_styles')); //admin scripts and styles
    //    add_action('wp_enqueue_scripts', array($this,'enqueue_public_scripts_and_styles')); //public scripts and styles
      add_action( 'plugins_loaded', 'wcimc_register_indulge_coupon_type' ); // Included with inc/wc-product-indulge-coupon.php
      add_filter( 'product_type_selector', array($this, 'wcimc_add_indulge_coupon_type') );
//      add_action( 'woocommerce_payment_complete', array($this, 'fetch_coupon_and_process'));
      add_action( 'woocommerce_order_status_completed', array($this, 'fetch_coupon_and_process'));
      add_action( 'woocommerce_single_product_summary', array($this, 'indulge_coupon_template'), 60 );

      if ( is_admin() ){ // admin actions
        add_action( 'woocommerce_product_options_general_product_data', function(){
            echo '<div class="options_group show_if_indulge_coupon clear"></div>';
        } );
        add_filter( 'woocommerce_product_data_tabs', array($this, 'hide_shipping_tab'));
        add_action( 'admin_footer', array($this, 'enable_js_on_wc_product'));
        add_action( 'admin_menu', array($this, 'register_indulge_api_settings_submenu_page')); // Place admin sub menu
        add_action( 'admin_init', array($this, 'register_indulgemall_settings')); // Whitelist options settings

//        add_filter( 'product_type_options', array($this, 'add_indulge_coupon_product_option'));  // Add "Indulge Coupon" checkbox, similar to "Virtual" on products
        add_action( 'woocommerce_admin_process_product_object', array($this, 'save_indulge_coupon_product_option')); // Save value of "Indulge Coupon" checkbox
        add_action( 'woocommerce_product_data_panels', array($this, 'add_indulge_coupon_tab_options') );
        add_action( 'admin_head', array($this, 'wcpp_custom_style'));

        add_filter( 'woocommerce_product_data_tabs', array($this, 'indulge_sku_settings_tab'));
        add_action( 'woocommerce_admin_process_product_object', array($this, 'save_indulge_coupon_tab_options'), 10, 1);
        add_filter( 'woocommerce_hidden_order_itemmeta', array($this, 'add_indulge_coupon_code_to_hidden_order_itemmeta'), 10, 1 );
      } else {
        // non-admin enqueues, actions, and filters
        add_action( 'woocommerce_order_item_meta_end', array($this, 'order_item_meta_end'), 10, 4 );
        //add_filter( 'woocommerce_locate_template', array($this, 'override_wc_locate_template'), 10, 3 );  // Override wc_locate_template result from WC so we can override WC templates
      }

    }

    //triggered on activation of the plugin (called only once)
    public static function plugin_activate() {
      // Did this default value elsewhere
      /*
      $options = get_option('indulge_api_settings');
      if ( isset($options['last_request_id']) ) {
          //error_log( "checking is_int" );
        if ( ! is_int( $options['last_request_id'] ) ) {
          $options['last_request_id'] = 0;
          update_option('indulge_api_settings', $options);
        }
      } else {
        error_log( print_r( "Got problem with plugin_activate, indulge_api_settings not registered yet.", true ) );
      }
       */
      //    $this->register_location_content_type();
        //flush permalinks
       //   flush_rewrite_rules();
    }

    //trigered on deactivation of the plugin (called only once)
    public static function plugin_deactivate(){
      //flush permalinks
      /*
      unregister_setting( 'indulgemall-option-group', 'indulge_api_settings' );
      delete_option( 'indulge_api_settings' ); // This works! Deletes all settings under 'indulge_api_settings'
      flush_rewrite_rules();
       */
    }

    public static function plugin_uninstall(){
      // WARNING: Destructive. All settings will be lost, even if re-installed. Edit: Not true
      unregister_setting( 'indulgemall-option-group', 'indulge_api_settings' );
      // TODO: examine if there are other things that need to be cleaned up here
      //   i.e. Custom Type "Indulge Coupon"
    }

    public function register_indulge_api_settings_submenu_page() {
      add_submenu_page( 'woocommerce', 'Indulge API Settings', 'Indulge API Settings', 'manage_woocommerce', 'indulge-api-settings', array($this, 'indulge_api_settings_options_page_callback') );
    }

    public function indulge_api_settings_options_page_callback() {
      if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
      }
      echo '<div class="wrap">';
      echo '<img src="' . plugin_dir_url(__FILE__) . 'assets/IMall-logo.png">';
      echo '<h3>Indulge Mall API Settings</h3>';
      echo '<form method="post" action="options.php">';
        settings_fields( 'indulgemall-option-group' );
        do_settings_sections( 'indulge-api-settings' );
        submit_button();
      echo '</form>';
      echo '</div>';
    }

    function register_indulgemall_settings() { // whitelist options
      /*
       * api_settings holds the following:
       *   staging_endpoint, staging_api_key, staging_account_id, staging_password
       *   live_endpoint, live_api_key, live_accound_id, live_password
       *   boolean_staging
       */
      register_setting( 'indulgemall-option-group', 'indulge_api_settings' ); //, array($this, 'api_settings_validate'));

      add_settings_section('general_settings', 'General Settings', array($this, 'general_settings_html_callback'), 'indulge-api-settings');
      add_settings_section('staging_settings', 'Staging Settings', array($this, 'staging_settings_html_callback'), 'indulge-api-settings');
      add_settings_section('live_settings', 'Live Settings', array($this, 'live_settings_html_callback'), 'indulge-api-settings');
      add_settings_section('select_staging', 'Activate Staging', array($this, 'activate_staging_html_callback'), 'indulge-api-settings');

      add_settings_field('request_id_prefix', 'Request ID Prefix', array($this, 'request_id_prefix_input_callback'), 'indulge-api-settings', 'general_settings');
      add_settings_field('last_request_id', 'Last Request ID No.', array($this, 'last_request_id_input_callback'), 'indulge-api-settings', 'general_settings');

      add_settings_field('staging_endpoint', 'Staging Endpoint (URL)', array($this, 'staging_endpoint_input_callback'), 'indulge-api-settings', 'staging_settings');
      add_settings_field('staging_api_key', 'Staging API Key', array($this, 'staging_apikey_input_callback'), 'indulge-api-settings', 'staging_settings');
      add_settings_field('staging_account_id', 'Staging Account ID', array($this, 'staging_accountid_input_callback'), 'indulge-api-settings', 'staging_settings');
      add_settings_field('staging_password', 'Staging Password', array($this, 'staging_password_input_callback'), 'indulge-api-settings', 'staging_settings');

      add_settings_field('live_endpoint', 'Live Endpoint (URL)', array($this, 'live_endpoint_input_callback'), 'indulge-api-settings', 'live_settings');
      add_settings_field('live_api_key', 'Live API Key', array($this, 'live_apikey_input_callback'), 'indulge-api-settings', 'live_settings');
      add_settings_field('live_account_id', 'Live Account ID', array($this, 'live_accountid_input_callback'), 'indulge-api-settings', 'live_settings');
      add_settings_field('live_password', 'Live Password', array($this, 'live_password_input_callback'), 'indulge-api-settings', 'live_settings');

      add_settings_field('select_credentials', 'Enable', array($this, 'select_input_callback'), 'indulge-api-settings', 'select_staging');
    }

    function staging_settings_html_callback() {
      echo '<p>Staging Settings</p>';
    }
    function live_settings_html_callback() {
      echo '<p>Live Settings</p>';
    }
    function general_settings_html_callback() {
      $options = get_option('indulge_api_settings');
      if ( isset($options['last_request_id']) ) {
        if ( ! is_int( $options['last_request_id'] ) ) {
          $options['last_request_id'] = 0;
          update_option('indulge_api_settings', $options);
        }
      }
      echo '<p>Last Request ID No.: ' . $options['last_request_id'] . '</p>';
    }
    function activate_staging_html_callback() {
      echo '<p>Activate Staging?</p>';
    }

    function request_id_prefix_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<input id='request_id_prefix' name='indulge_api_settings[request_id_prefix]' size='5' type='text' value='{$options['request_id_prefix']}' />";
      echo "<small> Supplied by Indulge Mall</small>";
    }

    function last_request_id_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<label for='indulge_api_settings[last_request_id]'>" . $options['last_request_id'] . "</label>";
      echo "<input id='last_request_id' name='indulge_api_settings[last_request_id]' type='hidden' value='{$options['last_request_id']}' />";
    }

    function staging_endpoint_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<input id='staging_endpoint' name='indulge_api_settings[staging_endpoint]' size='60' type='text' value='{$options['staging_endpoint']}' />";
    }

    function staging_apikey_input_callback() {
      $options = get_option('indulge_api_settings');
      //error_log( "Test: " . print_r( $options, true ) );
      echo "<input id='staging_api_key' name='indulge_api_settings[staging_api_key]' size='40' type='text' value='{$options['staging_api_key']}' />";
    }

    function staging_accountid_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<input id='staging_account_id' name='indulge_api_settings[staging_account_id]' size='40' type='text' value='{$options['staging_account_id']}' />";
    }

    function staging_password_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<input id='staging_password' name='indulge_api_settings[staging_password]' size='40' type='password' value='{$options['staging_password']}' />";
    }

    function live_endpoint_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<input id='live_endpoint' name='indulge_api_settings[live_endpoint]' size='60' type='text' value='{$options['live_endpoint']}' />";
    }

    function live_apikey_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<input id='live_api_key' name='indulge_api_settings[live_api_key]' size='40' type='text' value='{$options['live_api_key']}' />";
    }

    function live_accountid_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<input id='live_account_id' name='indulge_api_settings[live_account_id]' size='40' type='text' value='{$options['live_account_id']}' />";
    }

    function live_password_input_callback() {
      $options = get_option('indulge_api_settings');
      echo "<input id='live_password' name='indulge_api_settings[live_password]' size='40' type='password' value='{$options['live_password']}' />";
    }

    function select_input_callback() {
      $options = get_option('indulge_api_settings');
      if ( isset($options['select_credentials'] )) {
        $checked = $options['select_credentials'];
      } else {
        $checked = 0;
      }
        echo "<input id='select_credentials' name='indulge_api_settings[select_credentials]' type='checkbox' value='1' " . checked( $checked, 1, false) . "/>";
    }

    /**
     * Add 'Indulge Coupon' product option
     */
    /*
    function add_indulge_coupon_product_option( $product_type_options ) {
      $product_type_options['enable_indulge_coupon'] = array(
        'id'            => '_enable_indulge_coupon',
        'wrapper_class' => 'show_if_virtual',
        'label'         => __( 'Indulge Coupon', 'woocommerce' ),
        'description'   => __( 'Activates Indulge Mall API to fetch coupon code on payment success.', 'woocommerce' ),
        'default'       => 'no'
      );
      return $product_type_options;
    }
     */

    /**
     * Add Indulge Mall product tab.
     */
    function indulge_sku_settings_tab( $tabs ){
      $tabs['indulge_mall'] = array(
        'label'    => 'Indulge Mall',
        'target'   => 'indulge_sku_tab',
        'class'    => array('show_if_indulge_coupon'),
        'priority' => 21,
      );
      return $tabs;
    }

    /**
     * Add Indulge SKU setting to the Indulge Mall tab
     */
    function add_indulge_coupon_tab_options(){
      echo '<div id="indulge_sku_tab" class="panel woocommerce_options_panel hidden">';
      woocommerce_wp_text_input( array(
        'id'          => '_indulge_sku',
        'label'       => __( 'Indulge SKU', 'woocommerce' ),
        'desc_tip'    => 'true',
        'description' => __( 'Enter the Indulge Mall SKU for this coupon.', 'woocommerce' ),
      ) );
      echo '</div>';
    }

    /**
     * Save the indulge coupon product option.
     */
    function save_indulge_coupon_product_option( $product ) {
      $enable_indulge_coupon = isset( $_POST['_enable_indulge_coupon'] ) ? 'yes' : 'no';
      $product->update_meta_data( '_enable_indulge_coupon', $enable_indulge_coupon );
    }

    /**
     * Save the indulge coupon product option.
     */
    function save_indulge_coupon_tab_options( $product ) {
      if ( isset( $_POST['_indulge_sku'] ) ) {
        $product->update_meta_data( '_indulge_sku', $_POST['_indulge_sku'] );
      }
    }

    /**
     * MAIN FUNCTIONAL PART: POST request for coupon(s), update order,
     * email, and display custom thank you. ONLY if product type
     */
    function fetch_coupon_and_process( $order_id ){
      $options = get_option('indulge_api_settings');
      $order = wc_get_order( $order_id );
      $billingEmail = $order->get_billing_email();
      $order_items = $order->get_items();
      $nextRequestId = $options['last_request_id'] + 1;

      // #################
      // Common Parameters
      // #################
        // Staging or live: from Woocommerce -> Indulge Mall settings
      if ( isset($options['select_credentials']) && $options['select_credentials']) {
        // Staging
        $url = $options['staging_endpoint'];
        $apiKey = $options['staging_api_key'];
        $account = $options['staging_account_id'];
        $password = $options['staging_password'];
      } else {
        // Live
        $url = $options['live_endpoint'];
        $apiKey = $options['live_api_key'];
        $account = $options['live_account_id'];
        $password = $options['live_password'];
      }
      error_log( "URL: " . $url . ";  apiKey: " . $apiKey . ";  account: " . $account . ";  password: " . $password );

      if ( !is_wp_error( $order_items ) ) {
        foreach( $order_items as $item_id => $order_item ) {
          $product = $order_item->get_product();
  //        error_log( "Product: " . print_r($product, true));
          if ($product && "indulge_coupon" === $product->get_type()) {
            $productSku = $product->get_meta( '_indulge_sku', true );
            $productQty = $order_item->get_quantity();
            $requestId = $options['request_id_prefix'] . $nextRequestId;
            $headers = array("X-Api_Key" => $apiKey, "Content-type" => "application/json;charset=UTF-8");
            error_log("SKU: " . $productSku . "  Qty: " . $productQty . "  RequestId: " . $requestId . "  Headers: " . print_r($headers, true));

            $arrayOfParameters = array(
              'account' => $account
            , 'sign' => md5($account . md5($password) . $requestId)
            , 'request_id' => $requestId
            , 'sku' => $productSku
            , 'qty' => $productQty
            );
            // post to the request somehow
            $response = wp_remote_post( $url, array(
              'method' => 'POST',
              'timeout' => 45,
              'redirection' => 5,
              'httpversion' => '1.0',
              'blocking' => true,
              'headers' => array("X-Api_Key" => $apiKey, "Content-type" => "application/json;charset=UTF-8"),
              'body' => json_encode( $arrayOfParameters ),
              'cookies' => array()
              )
            );
//            if ( !is_wp_error( $response ) ) {
              //error_log( "Response: " . print_r($response, true));
 //           }
            // Fake it! As though we successfully fetched coupon
            $note = __("Coupon successfully fetched from Indulge Mall.");
            //$note = __("There was an error: " . $response);
            $order->add_order_note( $note );
            $coupon = 'X43363';
            wc_add_order_item_meta($item_id, '_indulge_coupon_code', $coupon, true ); 
          }
        }
      }
    }

    /**
     * Add content to each 'indulge_coupon' product within email
     * E.g. Coupon code: <b>XXXXXXX</b>
     */
    function order_item_meta_end( $item_id, $item, $order, $plain_text ){
      $order_status = $order->get_status();
      if ($order_status === 'completed') {
        $product = $item->get_product();
        if ($product && "indulge_coupon" === $product->get_type()) {
          $coupon_code = wc_get_order_item_meta( $item_id, '_indulge_coupon_code');
          echo '<p> Coupon code: <b>' . $coupon_code . '</b></p>';
        }
      }
    }

    /*
    function api_settings_validate($input) {
      // Obviously, not QUITE right
      $options = get_option('indulge-api-settings');
      $options['text_string'] = trim($input['text_string']);
      if(!preg_match('/^[a-z0-9]{32}$/i', $options['text_string'])) {
        $options['text_string'] = '';
      }
      return $options;
    }
     */

    /*
     * Add custom product type: Indulge Coupon to product types
     */
    function wcimc_add_indulge_coupon_type ( $type ) {
      // Key should be exactly the same as in the class product_type
      $type[ 'indulge_coupon' ] = __( 'Indulge Coupon' );
      return $type;
    }

    /*
     * Add _indulge_coupon_code meta key to list of hidden items in order itemmeta
     * Admin Edit Order
     */
    function add_indulge_coupon_code_to_hidden_order_itemmeta( $array ) {
        array_push($array, '_indulge_coupon_code');
        return $array; 
    }

    /**
     * Hides the shipping tab for Indulge Coupon products
     */
    public function hide_shipping_tab( $tabs ) {
      $tabs['shipping']['class'][] = 'hide_if_indulge_coupon';
      return $tabs;
    }

    /**
     * Add ticket/coupon icon to Indulge Mall product tab.
     */
    function wcpp_custom_style() {
      ?><style>
        #woocommerce-product-data ul.wc-tabs li.indulge_mall_tab a:before { font-family: WooCommerce; content: '\e600'; }
      </style><?php
    }

    function indulge_coupon_template() {
      global $product;
      if ( 'indulge_coupon' == $product->get_type() ) {
        // Just use simple.php, as 'indulge_coupon' inherits from it.
        wc_get_template( 'single-product/add-to-cart/simple.php' );
      }
    }
    /**
     * Override the result of wc-core-functions.php::wc_locate_template
     * From version 3.3.0
     */
    function override_wc_locate_template( $template, $template_name, $template_path ) {
      $wcimc_plugin_path  = untrailingslashit( plugin_dir_path( __FILE__ ) ) . '/woocommerce/';
      if ( file_exists( $wcimc_plugin_path . $template_name ) ) {
        $template = $wcimc_plugin_path . $template_name;
      }
      return $template;
    }

    public function enable_js_on_wc_product() {
      global $post, $product_object;
      if ( ! $post ) { return; }
      if ( 'product' != $post->post_type ) :
        return;
      endif;
      $is_indulge_coupon = $product_object && 'indulge_coupon' === $product_object->get_type() ? true : false;
      ?>
      <script type='text/javascript'>
        jQuery(document).ready(function () {
          //for Price tab
          jQuery('#general_product_data .pricing').addClass('show_if_indulge_coupon');
          <?php if ( $is_indulge_coupon ) { ?>
            jQuery('#general_product_data .pricing').show();
          <?php } ?>
         });
       </script>
       <?php
     }
  }

  include(plugin_dir_path(__FILE__) . 'inc/wc-product-indulge-coupon.php');
  new WC_Indulgemall_Coupons();
}

register_activation_hook(__FILE__, array('WC_Indulgemall_Coupons','plugin_activate')); //activate hook
register_deactivation_hook(__FILE__, array('WC_Indulgemall_Coupons','plugin_deactivate')); //deactivate hook
register_uninstall_hook(__FILE__, array('WC_Indulgemall_Coupons','plugin_uninstall')); // uninstall hook

