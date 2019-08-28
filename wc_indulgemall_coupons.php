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

    //    add_action('init', array($this,'set_location_trading_hour_days')); //sets the default trading hour days (used by the content type)
    //    add_action('init', array($this,'register_location_content_type')); //register location content type
    //    add_action('add_meta_boxes', array($this,'add_location_meta_boxes')); //add meta boxes
    //    add_action('save_post_wp_locations', array($this,'save_location')); //save location
    //    add_action('admin_enqueue_scripts', array($this,'enqueue_admin_scripts_and_styles')); //admin scripts and styles
    //    add_action('wp_enqueue_scripts', array($this,'enqueue_public_scripts_and_styles')); //public scripts and styles
    //    add_filter('the_content', array($this,'prepend_location_meta_to_content')); //gets our meta data and dispayed it before the content
  //    error_log( print_r( "About to run add_action for admin_menu", true ) );
      if ( is_admin() ){ // admin actions
        add_action( 'plugins_loaded', array($this, 'wcimc_register_indulge_coupon_type') );
        add_filter( 'product_type_selector', array($this, 'wcimc_add_indulge_coupon_type') );
        add_action('admin_menu', array($this, 'register_indulge_api_settings_submenu_page')); // Place admin sub menu
        add_action('admin_init', array($this, 'register_indulgemall_settings')); // Whitelist options settings

        add_filter( 'product_type_options', array($this, 'add_indulge_coupon_product_option'));  // Add "Indulge Coupon" checkbox, similar to "Virtual" on products
        add_action( 'woocommerce_admin_process_product_object', array($this, 'save_indulge_coupon_product_option')); // Save value of "Indulge Coupon" checkbox
        add_action( 'woocommerce_product_data_panels', 'add_indulge_coupon_tab_options' );
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
         * Add a bit of style.
         */
        function wcpp_custom_style() {
          ?><style>
            #woocommerce-product-data ul.wc-tabs li.indulge_mall_tab a:before { font-family: WooCommerce; content: '\e600'; }
          </style><?php
        }
        add_action( 'admin_head', 'wcpp_custom_style' );

        add_action( 'woocommerce_admin_process_product_object', 'save_indulge_coupon_panel_options', 10, 1 );
        /**
         * Save the indulge coupon product option.
         */
        function save_indulge_coupon_panel_options( $product ) {
          if ( isset( $_POST['_indulge_sku'] ) ) {
            $product->update_meta_data( '_indulge_sku', $_POST['_indulge_sku'] );
          }
        }
        add_filter('woocommerce_product_data_tabs', 'indulge_sku_setting_tab' );
        function indulge_sku_setting_tab( $tabs ){
          $tabs['indulge_mall'] = array(
            'label'    => 'Indulge Mall',
            'target'   => 'indulge_sku_tab',
            'class'    => array('show_if_indulge_coupon'),
            'priority' => 21,
          );
          return $tabs;
        }
      } else {
        // non-admin enqueues, actions, and filters
      }

    }

    //triggered on activation of the plugin (called only once)
    public static function plugin_activate(){  
        //call our custom content type function
      //    $this->register_location_content_type();
        //flush permalinks
       //   flush_rewrite_rules();
    }

    //trigered on deactivation of the plugin (called only once)
    public static function plugin_deactivate(){
      //flush permalinks
      flush_rewrite_rules();
    }

    public function register_indulge_api_settings_submenu_page() {
      add_submenu_page( 'woocommerce', 'Indulge API Settings', 'Indulge API Settings', 'manage_woocommerce', 'indulge-api-settings', array($this, 'indulge_api_settings_options_page_callback') );
    }

    public function indulge_api_settings_options_page_callback() {
      if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
      }
      echo '<div class="wrap">';
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
       *   staging_api_key, staging_account_id, staging_password
       *   live_api_key, live_accound_id, live_password
       *   boolean_live
       */
      register_setting( 'indulgemall-option-group', 'api_settings' ); //, array($this, 'api_settings_validate'));

      add_settings_section('staging_settings', 'Staging Settings', array($this, 'staging_settings_html_callback'), 'indulge-api-settings');
      add_settings_section('live_settings', 'Live Settings', array($this, 'live_settings_html_callback'), 'indulge-api-settings');
      add_settings_section('select_staging', 'Activate Staging', array($this, 'activate_staging_html_callback'), 'indulge-api-settings');

      add_settings_field('staging_api_key', 'Staging API Key', array($this, 'staging_apikey_input_callback'), 'indulge-api-settings', 'staging_settings');
      add_settings_field('staging_account_id', 'Staging Account ID', array($this, 'staging_accountid_input_callback'), 'indulge-api-settings', 'staging_settings');
      add_settings_field('staging_password', 'Staging Password', array($this, 'staging_password_input_callback'), 'indulge-api-settings', 'staging_settings');

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
    function activate_staging_html_callback() {
      echo '<p>Activate Staging?</p>';
    }

    function staging_apikey_input_callback() {
      $options = get_option('api_settings');
      echo "<input id='staging_api_key' name='api_settings[staging_api_key]' size='40' type='text' value='{$options['staging_api_key']}' />";
    }

    function staging_accountid_input_callback() {
      $options = get_option('api_settings');
      echo "<input id='staging_account_id' name='api_settings[staging_account_id]' size='40' type='text' value='{$options['staging_account_id']}' />";
    }

    function staging_password_input_callback() {
      $options = get_option('api_settings');
      echo "<input id='staging_password' name='api_settings[staging_password]' size='40' type='password' value='{$options['staging_password']}' />";
    }

    function live_apikey_input_callback() {
      $options = get_option('api_settings');
      echo "<input id='live_api_key' name='api_settings[live_api_key]' size='40' type='text' value='{$options['live_api_key']}' />";
    }

    function live_accountid_input_callback() {
      $options = get_option('api_settings');
      echo "<input id='live_account_id' name='api_settings[live_account_id]' size='40' type='text' value='{$options['live_account_id']}' />";
    }

    function live_password_input_callback() {
      $options = get_option('api_settings');
      echo "<input id='live_password' name='api_settings[live_password]' size='40' type='password' value='{$options['live_password']}' />";
    }

    function select_input_callback() {
      $options = get_option('api_settings');
      echo "<input id='select_credentials' name='api_settings[select_credentials]' size='40' type='checkbox' value='{$options['select_credentials']}' />";
    }

    /**
     * Add 'Indulge Coupon' product option
     */
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

    /**
     * Save the indulge coupon product option.
     */
    function save_indulge_coupon_product_option( $product ) {
      $enable_indulge_coupon = isset( $_POST['_enable_indulge_coupon'] ) ? 'yes' : 'no';
      $product->update_meta_data( '_enable_indulge_coupon', $enable_indulge_coupon );
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

  }

  include(plugin_dir_path(__FILE__) . 'inc/wc-product-indulge-coupon.php');

  //error_log( print_r( "About to create WC_Indulgemall_Coupons", true ) );
  new WC_Indulgemall_Coupons();
}

register_activation_hook(__FILE__, array('WC_Indulgemall_Coupons','plugin_activate')); //activate hook
register_deactivation_hook(__FILE__, array('WC_Indulgemall_Coupons','plugin_deactivate')); //deactivate hook

