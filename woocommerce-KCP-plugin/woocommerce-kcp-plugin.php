<?php
 /**
 * Plugin Name: WooCommerce KCP
 * Description: woocommerce KCP 결제모듈
 * Version: 0.1
 * Author: AJin
 **/ 

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'WC_KCP_module' ) ) :

class WC_KCP_module {

    public $version = '0.1';

    private static $instance;
    public $gateway_items = array( 'kcp' );
        
    private function __construct() { /* Do nothing here */ }
        
    public static function getInstance() {
        if( !class_exists( 'Woocommerce' ) ) {
            return null;
        } else if( ! isset( self::$instance ) ) {
            self::$instance = new WC_KCP_module;
            self::$instance->setup_globals();
            self::$instance->includes();
            self::$instance->setup_actions();
        }
        return self::$instance;
    }
    
    private function setup_globals() {
        
        //domain
        $this->domain           = 'wc_kcp';
        //pluugins
        $this->file             = __FILE__;
        $this->plugin_dir       = apply_filters( 'wc_kcp_plugin_dir_path',  plugin_dir_path( $this->file ) );
        $this->plugin_url       = apply_filters( 'wc_kcp_plugin_dir_url',   plugin_dir_url ( $this->file ) );
        
        // Includes
        $this->includes_dir     = apply_filters( 'wc_kcp_includes_dir', trailingslashit( $this->plugin_dir . 'includes'  ) );
        $this->includes_url     = apply_filters( 'wc_kcp_includes_url', trailingslashit( $this->plugin_url . 'includes'  ) );
        
        //gateway
        $this->gateway_dir      = apply_filters( 'wc_kcp_gateway_dir', trailingslashit( $this->plugin_dir . 'gateway'  ) );
        $this->gateway_url      = apply_filters( 'wc_kcp_gateway_url', trailingslashit( $this->plugin_url . 'gateway'  ) );
        
        //Gateway list item
        $this->gateway_items    = apply_filters( 'wc_kcp_gateway', $this->gateway_items );
        
        //shipping
        $this->shipping_dir     = apply_filters( 'wc_kcp_shipping_dir', trailingslashit( $this->plugin_dir . 'shipping'  ) );
        $this->shipping_url     = apply_filters( 'wc_kcp_shipping_url', trailingslashit( $this->plugin_url . 'shipping'  ) );
        
        // Languages
        $this->lang_dir         = apply_filters( 'wc_kcp_lang_dir',     trailingslashit( $this->plugin_dir . 'languages' ) );
        
    }
    
    private function includes() {
        
        require_once( $this->includes_dir . 'functions.php' );
//        require_once( $this->includes_dir . 'options.php' );
        
        //gateway load
        foreach( $this->gateway_items as $gateway_item ) {
            require_once( $this->gateway_dir . $gateway_item .'/'. $gateway_item .'.php' ); 
        }

    }
    
    private function setup_actions() {
        // init action 
        add_action('init', array( $this, 'wc_kcp_init' ) );
        
        //load textdomain
        add_action('wc_kcp_init', array( $this, 'wc_kcp_load_textdomain' ), 5 ); 
//        add_action('wc_kcp_init', array( $this, 'wc_kcp_load_options' ), 10 );
    }
    
    public function wc_kcp_init() {
        do_action('wc_kcp_init');
    }

    public function wc_kcp_load_textdomain() {
        // Traditional WordPress plugin locale filter
        $locale        = apply_filters( 'plugin_locale',  get_locale(), $this->domain );
        $mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

        // Setup paths to current locale file
        $mofile_local  = $this->lang_dir . $mofile;
        $mofile_global = WP_LANG_DIR . $this->domain . $mofile;

        // Look in global /wp-content/languages/bbpress folder
        if ( file_exists( $mofile_global ) ) {
            return load_textdomain( $this->domain, $mofile_global );

        // Look in local /wp-content/plugins/bbpress/bbp-languages/ folder
        } elseif ( file_exists( $mofile_local ) ) {
            return load_textdomain( $this->domain, $mofile_local );
        }

        // Nothing found
        return false;
    }

    /*public function wc_kcp_load_options(){
        $wckorea_pack_options = new WC_KCP_module_options();     
    }*/
        
}

function wc_kcp() {
        return WC_KCP_module::getInstance();
}

add_action( 'plugins_loaded', 'wc_kcp', 0 );
endif;
