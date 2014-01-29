<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'WC_Gateway_KCP' ) ) :

define( WCKP_KCP_PLUGIN_DIR,  plugin_dir_path( __FILE__ ) );	
define( WCKP_KCP_PLUGIN_URL,  plugin_dir_url ( __FILE__ ) );
define( WCKP_KCP_TEMPLATES_PATH,  trailingslashit( WCKP_KCP_PLUGIN_DIR.'templates') );

class WC_Gateway_KCP extends WC_Payment_Gateway {
    /*
     * 스크립트 로드 여부
     * */
    public static $is_script_already = false;

	function __construct() {
		global $woocommerce;
		
		$this->has_fields 			= false;
		$this->templates_path 		= WCKP_KCP_TEMPLATES_PATH;

		// Define user set variables
		$this->debug 				= false;
		$this->title 				= $this->get_option('title');
		$this->description 			= $this->get_option('description');
		$this->order_description 	= $this->get_option('order_description');
		
		// load form fields.
		$this->init_form_fields();
		
		// load settings (via WC_Settings_API)
		$this->init_settings();
        
		// Logs
		if ( 'true' == $this->debug )
			$this->log = $woocommerce->logger();

		if ( ! $this->is_valid_for_use() ) $this->enabled = false;

		//add pay script
		add_action('wp_enqueue_scripts', array( $this, 'script' ) );
		
		// Actions
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_receipt_'.$this->id, array( $this, 'receipt_page' ) );
		
		// 결제 모듈 ajax 사용이 가능해질 경우.
		//add_action( 'wp_ajax_wpkp_kcp_response'.$this->id, array( $this, 'process_payment_response' ) );
		
	}

	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'wc_kcp'),
				'type' => 'checkbox',
				'label' => __('Enable KCP standard', 'wc_kcp'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Title', 'wc_kcp_module'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'wc_kcp_module'),
				'default' => __('Card Payment', 'wc_kcp_module'),
				'desc_tip' => true,
			),
			'site_cd' => array(
				'title' => __( 'Site Code', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'Do not Change.', 'woocommerce' ),
				'default' => 'T0000',
				'desc_tip'      => false,
			),
			'site_key' => array(
				'title' => __( 'Site Key', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'Do not Change.', 'woocommerce' ),
				'default' => '3grptw1.zW0GSo4PQdaGvsF__',
				'desc_tip'      => false,
			),
			'site_name' => array(
				'title' => __( 'Site Name', 'woocommerce' ),
				'type' => 'text',
				'description' => __( 'Todo...', 'woocommerce' ),
				'default' => 'KCP TEST SHOP',
				'desc_tip'      => false,
			),
			'description' => array(
				'title' => __('Description', 'wc_kcp'),
				'type' => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'wc_kcp' ),
				'default' => __( 'This controls the description which the user sees during checkout.', 'wc_kcp' ),
			),
			'testing' => array(
				'title' => __( 'Gateway Testing', 'woocommerce' ),
				'type' => 'title',
				'description' => '',
			),
			'testmode' => array(
				'title' => __( 'KCP TestMode', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enable KCP TestMode', 'woocommerce' ),
				'default' => 'yes',
				'description' => sprintf( __( 'KCP sandbox can be used to test payments. Sign up for a developer account <a href="%s">here</a>.', 'woocommerce' ), 'https://developer.KCP.com/' ),
			),
			'debug' => array(
				'title' => __( 'Debug Log', 'woocommerce' ),
				'type' => 'checkbox',
				'label' => __( 'Enable logging', 'woocommerce' ),
				'default' => 'no',
				'description' => sprintf( __( 'Log KCP events, such as IPN requests, inside <code>woocommerce/logs/KCP-%s.txt</code>', 'woocommerce' ), sanitize_file_name( wp_hash( 'KCP' ) ) ),
			)
			
		);
	}

	/* *
	 * 	script
	 * */

	public function script() {	    
	    if( WC_Gateway_KCP::$is_script_already === true ) return;
        
		if ($this->enabled == 'yes' && is_page( woocommerce_get_page_id( 'pay' ) ) == true) {

            WC_Gateway_KCP::$is_script_already = true;
            
		    $order_id  = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
            $order_key = isset( $_GET['key'] ) ? woocommerce_clean( $_GET['key'] ) : '';
            
            $order = new WC_Order( $order_id );
            
            $thanks_url = get_permalink( woocommerce_get_page_id( 'thanks' ) );
            $thanks_url = add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, $thanks_url ) );
            
			echo '<script>aler("'.$thanks_url.'");</script>';
			//wp_enqueue_script( 'wc_kcp_remote', 'https://api.kcp.net/ajax/common/OpenPayAPI.js',null, null,true);
			#ie 7 지원
			/*echo '<script language="javascript" src="https://api.kcp.net/ajax/common/OpenPayAPI.js" charset="utf-8"></script>';*/

//			wp_enqueue_script( 'wc_kcp_main', WCKP_KCP_PLUGIN_URL.'assets/kcp.js', array('jquery'), wc_kcp()->version, true);
			wp_localize_script( 'wc_kcp_main', 'kcpm', array(
				'thanks_url' => $thanks_url,
				'message_failure' => __('결제가 실패했습니다. 다시 이용해 주세요', 'wc_kcp')
			) ); 	
//			wp_register_style( 'wc_kcp_main', WCKP_KCP_PLUGIN_URL.'assets/style.css', '', wc_kcp()->version);
//			wp_enqueue_style( 'wc_kcp_main' );
		}
	}
	
	public function format_settings($value) {
		return ( is_array($value)) ? $value : html_entity_decode($value);
	}
	
	/* *
	 * 	admin option page
	 * */
	 
	public function admin_options() {
		require $this->templates_path . 'admin-woocommerce-kcp.php';
	}

	public function validate_fields() {
		return parent::is_available();
	}

	public function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
		);		
	}

 /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
	public function receipt_page( $order_id ) {
		global $woocommerce;
		
		echo '<p>'.__( '주문해주셔서 감사합니다. KCP 결제 버튼을 누루시면 결제창이 뜹니다.', 'woocommerce' ).'</p>';
        
        $order = new WC_Order( $order_id );
        
		//Customer Address Info.
		$customer_id = get_current_user_id();
		
		
		//Ordered products Table
		echo '
		<h4>주문내역</h4>
		<table class="shop_table order_details">
			<thead>
				<tr>
					<th class="product-img">항목</th>
					<th class="product-name">상품</th>
					<th class="product-quantity">수량</th>
					<th class="product-total">합계</th>
				</tr>
			</thead>
			<tbody>';
		if (sizeof($order->get_items())>0) {

			foreach($order->get_items() as $item) {

				$_product = get_product( $item['variation_id'] ? $item['variation_id'] : $item['product_id'] );
				$thumbnail = apply_filters( 'woocommerce_in_cart_product_thumbnail', $_product->get_image(), $item );
				
				echo '
					<tr class = "' . esc_attr( apply_filters( 'woocommerce_order_table_item_class', 'order_table_item', $item, $order ) ) . '">';
				echo '<td class="product-img">';
				
				if ( ! $_product->is_visible() || ( ! empty( $_product->variation_id ) && ! $_product->parent_is_visible() ) )
					echo $thumbnail;
				else
					printf('<a href="%s">%s</a>', esc_url( get_permalink( apply_filters('woocommerce_in_cart_product_id', $item['product_id'] ) ) ), $thumbnail );
				
				echo '</td>
						<td class="product-name">' .
							apply_filters( 'woocommerce_order_table_product_title', '<a href="' . get_permalink( $item['product_id'] ) . '">' . $item['name'] . '</a>', $item );

				$item_meta = new WC_Order_Item_Meta( $item['item_meta'] );
				$item_meta->display();

				if ( $_product && $_product->exists() && $_product->is_downloadable() && $order->is_download_permitted() ) {

					$download_file_urls = $order->get_downloadable_file_urls( $item['product_id'], $item['variation_id'], $item );

					$i     = 0;
					$links = array();

					foreach ( $download_file_urls as $file_url => $download_file_url ) {

						$filename = woocommerce_get_filename_from_url( $file_url );

						$links[] = '<small><a href="' . $download_file_url . '">' . sprintf( __( 'Download file%s', 'woocommerce' ), ( count( $download_file_urls ) > 1 ? ' ' . ( $i + 1 ) . ': ' : ': ' ) ) . $filename . '</a></small>';

						$i++;
					}

					echo implode( '<br/>', $links );
				}

				echo '</td>';
				echo '<td class="product-quantity">'.
						apply_filters( 'woocommerce_checkout_item_quantity', $item['qty'], $item) .
						'</td>';
				echo '<td class="product-total">' . $order->get_formatted_line_subtotal( $item ) . '</td></tr>';

				// Show any purchase notes
				if ($order->status=='completed' || $order->status=='processing') {
					if ($purchase_note = get_post_meta( $_product->id, '_purchase_note', true))
						echo '<tr class="product-purchase-note"><td colspan="3">' . apply_filters('the_content', $purchase_note) . '</td></tr>';
				}

			}
		}

		do_action( 'woocommerce_order_items_table', $order );
		echo '
			</tbody>
		</table>';
		echo '
		<table class="totals_table">
			<tbody>';
		if ( $totals = $order->get_order_item_totals() ) foreach ( $totals as $total ) :
			echo '
			<tr>
				<th scope="row">'.$total['label'].'</th>
				<td>'. $total['value'].'</td>
			</tr>
			';
		endforeach;
		echo '
			</tbody>
		</table>
		<div class="order-hr clearfix"></div>';
		////
		echo '
		<header><h4>고객정보</h4></header>
		<dl class="customer_details">';
		if ($order->billing_email) echo '<dt>'.__( 'Email:', 'woocommerce' ).'</dt><dd>'.$order->billing_email.'</dd>';
		if ($order->billing_phone) echo '<dt>'.__( 'Telephone:', 'woocommerce' ).'</dt><dd>'.$order->billing_phone.'</dd>';
		echo '</dl>';
		echo '
		<header><h4>배송 주소</h4></header>
		<address><p>';
			//if (!$order->get_formatted_shipping_address()) _e( 'N/A', 'woocommerce' ); else echo $order->get_formatted_shipping_address();
			if($order->shipping_company!=NULL) echo $order->shipping_company.' ';
			echo $order->shipping_first_name.'<br>'.$order->shipping_postcode.'<br>'.$order->shipping_city.'<br>'.
					$order->shipping_address_1.'<br>'.$order->shipping_address_2;

		echo '
		</p></address>
		<div class="clear"></div>';	
		
		echo $this->generate_KCP_form( $order_id );
	}

	public function process_payment_response() {
		global $woocommerce;


		// nonce check!
		$woocommerce->verify_nonce( 'process_payment_response' );
		
		$order_id  = isset( $_GET['order'] ) ? absint( $_GET['order'] ) : 0;
		$order_key = isset( $_GET['key'] ) ? woocommerce_clean( $_GET['key'] ) : '';
		
		$order = new WC_Order( $order_id );
		#check order key!! 
		if ( $order_id > 0 ) {
			if ( $order->order_key != $order_key ) {
				$woocommerce->add_error( __('주문번호 검증 실패', 'wc_kcp') );				
			}
		} else {
			$woocommerce->add_error( __('주문번호 검증 실패', 'wc_kcp') );
		}
		
		if ( $woocommerce->error_count() == 0 ) {
			
			#check SHA256!!!
			if( $this->check_salt( $order_id ) === true ) {
				
				$order->payment_complete();
				$woocommerce->cart->empty_cart();
				
			} else {
				$woocommerce->add_error( __('주문번호 검증 실패', 'wc_kcp') );
			}
		}

		if ( $woocommerce->error_count() == 0 ) {
			
            /* 결제 모듈 ajax 사용이 가능해질 경우.
            echo '<!--WCKP_START-->' . json_encode(
                array(
                    'result'    => 'success'
                )
            ) . '<!--WCKP_END-->';*/
			wp_redirect( $this->get_return_url( $order ) );

		} else {
            $erors = $woocommerce->get_errors();
            $messages = $woocommerce->get_messages();
            
            if ( 'true' == $this->debug ) {
                foreach ( $erors as $error ){
                    $this->log->add( $this->id, __FUNCTION__ . wp_kses_post( $error ) );
                     
                }
            }

            echo '<script>
                alert("'.implode(', ', $messages).'");
                window.location="'.get_permalink(woocommerce_get_page_id( 'cart' )).';
            </script>'; 
			
			/* 결제 모듈 ajax 사용이 가능해질 경우.
			$woocommerce->show_messages();
            $messages = ob_get_clean();
            echo '<!--WCKP_START-->' . json_encode(
				array(
					'result'	=> 'failure',
					'messages' 	=> $messages,
				)
			) . '<!--WCKP_END-->';*/
			
		}
        die();
	}
	
	//결제검증
	public function check_salt( $order_id ) {
		global $woocommerce;
		
		if ( 'true' == $this->debug ) {
			$_POST['unitprice'] = 100;	
		}
		
/*		if( $this->api_key ) {
			$order 	= new WC_Order( $order_id );
			
			$data = $_POST['replycode'].$_POST['tid'].$order_id.$_POST['unitprice'].$_POST['goodcurrency'];
			
			$hashReuslt = hash('sha256',$this->api_key.$data);
						
			if( $hashReuslt != $_POST['hashresult'] ) {
				$woocommerce->add_error( __( '비정상적인 결제 시도', 'wc_kcp') );
				return false;
			} 
			
		}
		*/
		
		return true;
	}
		
}

class kcpm_KCP {
	
	private $methods;
	
	
	function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}
	
	private function setup_globals() {
		$this->methods		= array(
			//'alipay', 
			//'ars', 
			//'bank', 
			//'cup', 
			//'mobile', 
			//'phonebill', 
			'card', 
		);
	}
	
	private function includes() {
		foreach( $this->methods as $method ) {
			require_once WCKP_KCP_PLUGIN_DIR.'class-wc-gateway-kcp-'.$method.'.php';
		}
	}
	
	public function setup_actions() {
		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_kcp_class' ) );
	}
	
	public function add_kcp_class( $methods ) {
		
		foreach( $this->methods as $method ) {
			array_unshift( $methods, 'WC_Gateway_KCP_'.$method );
		}

		return $methods;
	}
}

$GLOBALS['kcp'] = new kcpm_KCP();

endif;