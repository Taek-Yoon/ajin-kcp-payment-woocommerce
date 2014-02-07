<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

 /**
 * KCP .
 *
 * @class 		WC_Gateway_kcp_card
 * @extends		WC_Payment_Gateway
 * @version		0.1.0
 * @author 		studio-jt
 */
 if ( !class_exists( 'WC_Gateway_KCP_card' ) ) :
	 
class WC_Gateway_KCP_card extends WC_Gateway_KCP {
	
	var $notify_url;
	var $conf_home_dir;
	
	function __construct(){
		global $woocommerce; 
		
		$this->id 					= 'KCP';
		$this->method 				= 'card';
		$this->icon 				=  apply_filters( 'woocommerce_KCP_icon', WCKP_KCP_PLUGIN_URL . 'images/KcpLogo.jpg' );
        $this->has_fields   = true;

		$this->method_title 		= 'kcp [Card]';
		$this->method_description	= 'kcp_card';
        $this->notify_url           = str_replace('https:', 'http:', add_query_arg( 'wc-api', strtolower(__CLASS__), home_url( '/' ) ) ) ;

		$this->live_gw_url      = 'paygw.kcp.co.kr';
		$this->test_gw_url      = 'testpaygw.kcp.co.kr';
		$this->live_gw_port     = '8090';
        
        $this->live_js_url      = 'http://pay.kcp.co.kr/plugin/payplus_un.js';
		$this->test_js_url      = 'http://pay.kcp.co.kr/plugin/payplus_test_un.js';

		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables
		$this->title 			= $this->get_option( 'title' );
		$this->description 		= $this->get_option( 'description' );
		$this->testmode			= $this->get_option( 'testmode' );
		$this->debug			= $this->get_option( 'debug' );

        $this->site_cd   = $this->get_option( 'site_cd' );
        $this->site_key  = $this->get_option( 'site_key' );
        $this->site_name = $this->get_option( 'site_name' );

		// Logs
		if ( 'yes' == $this->debug )
			$this->log = $woocommerce->logger();
    	
		// Actions
		add_action( 'check_ipn_response', array( $this, 'check_ipn_response' ) );
		add_action( 'successful_request', array( $this, 'successful_request' ) );
		
		add_action( 'woocommerce_receipt_KCP' , array(&$this, 'receipt_page' ) );
		add_action( 'woocommerce_thankyou_KCP', array( $this, 'thankyou_page') );
		
		// Save options
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		
		
        // Payment listener/API hook
        add_action( 'woocommerce_api_'.strtolower(__CLASS__), array( $this, 'process_payment_response' ) );
        
        add_filter('wc_kcpm_kcp_card_args', array($this, 'kcp_card_args') );
        $this->kcp_currencies_args    = apply_filters('wc_kcpm_kcp_currencies_args_card', array(
            'KRW' => array( 
                    'goodcurrency' => 'WON',
                    'langcode' => 'KR'
                ),
            'USD' => array(
                    'goodcurrency' => 'USD',
                    'langcode' => 'US'
            ),
            'RMB' => array(
                'goodcurrency' => 'CNY',
                'langcode' => 'CN',
            ),
            'JPY' => array(
                'goodcurrency' => 'JPY',
                'langcode' => 'JP',
            ),
        ));
        
        $this->supported_currencies = array_keys( $this->kcp_currencies_args );
        
		parent::__construct();
	}

    /**
     * If There are no payment fields show the description if set.
     * Override this in your gateway if you have some.
     *
     * @access public
     * @return void
     */
    public function payment_fields( ) {
		global $woocommerce;
		
    }
	
	/**
	 * Admin Panel Options
	 * - Options for bits like 'title' and availability on a country-by-country basis
	 *
	 * @since 1.0.0
	 */
	public function admin_options() {

		?>
		<h3><?php _e( 'KCP standard', 'woocommerce' ); ?></h3>
		<p><?php _e( 'KCP standard works by sending the user to KCP to enter their payment information.', 'woocommerce' ); ?></p>

		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table><!--/.form-table-->
		<?php
	}


	public function init_form_fields() {
		parent::init_form_fields();
	}

	/**
	 * Check for KCP IPN Response
	 *
	 * @access public
	 * @return void
	 */
	function check_ipn_response() {

		@ob_clean();
		if ($this->debug=='yes') $this->log->add( 'check_ipn_response', 'Called' );
		
    	if ( ! empty( $_POST["res_cd"] ) && $_POST["res_cd"] = "0000" ) {

    		header( 'HTTP/1.1 200 OK' );
        	do_action( "successful_request", $_POST );

		}
	}

	/**
	 * Successful Payment!
	 *
	 * @access public
	 * @param array $posted
	 * @return void
	 */
	function successful_request( $posted ) {
		global $woocommerce;

		if ($this->debug=='yes') $this->log->add( 'successful_request', 'Called' );
		
        $g_conf_home_dir  = $_POST[ 'g_conf_home_dir'  ];
        
        $g_conf_gw_url    = $_POST[ 'g_conf_gw_url'    ];
    	$g_conf_js_url	  = $_POST[ 'g_conf_js_url'    ];
        $g_conf_site_cd   = $_POST[ 'g_conf_site_cd'   ];
        $g_conf_site_key  = $_POST[ 'g_conf_site_key'  ];
        $g_conf_site_name = $_POST[ 'g_conf_site_name' ];
        
        $g_conf_log_level = "3";           // 변경불가
        $g_conf_gw_port   = "8090";        // 포트번호(변경불가)
    
        //require "pp_ax_hub_lib.php";              // library [수정불가]
		require plugin_dir_path( __FILE__ )."modules/pp_ax_hub_lib.php";              // library [수정불가]
		
        
        /* ============================================================================== */
        /* =   01. 지불 요청 정보 설정                                                  = */
        /* = -------------------------------------------------------------------------- = */
    	$req_tx         = $_POST[ "req_tx"         ]; // 요청 종류
    	$tran_cd        = $_POST[ "tran_cd"        ]; // 처리 종류
    	/* = -------------------------------------------------------------------------- = */
    	$cust_ip        = getenv( "REMOTE_ADDR"    ); // 요청 IP
    	$ordr_idxx      = $_POST[ "ordr_idxx"      ]; // 쇼핑몰 주문번호
    	$good_name      = $_POST[ "good_name"      ]; // 상품명
    	$good_mny       = $_POST[ "good_mny"       ]; // 결제 총금액
    	/* = -------------------------------------------------------------------------- = */
        $res_cd         = "";                         // 응답코드
        $res_msg        = "";                         // 응답메시지
    	$res_en_msg     = "";                         // 응답 영문 메세지
        $tno            = $_POST[ "tno"            ]; // KCP 거래 고유 번호
        /* = -------------------------------------------------------------------------- = */
        $buyr_name      = $_POST[ "buyr_name"      ]; // 주문자명
        $buyr_tel1      = $_POST[ "buyr_tel1"      ]; // 주문자 전화번호
        $buyr_tel2      = $_POST[ "buyr_tel2"      ]; // 주문자 핸드폰 번호
        $buyr_mail      = $_POST[ "buyr_mail"      ]; // 주문자 E-mail 주소
        /* = -------------------------------------------------------------------------- = */
        $mod_type       = (empty($_POST[ "mod_type"       ])) ? "" : $_POST[ "mod_type"       ]; // 변경TYPE VALUE 승인취소시 필요
        $mod_desc       = (empty($_POST[ "mod_desc"       ])) ? "" : $_POST[ "mod_desc"       ]; // 변경사유
        /* = -------------------------------------------------------------------------- = */
        $use_pay_method = $_POST[ "use_pay_method" ]; // 결제 방법
        $bSucc          = "";                         // 업체 DB 처리 성공 여부
        /* = -------------------------------------------------------------------------- = */
    	$app_time       = "";                         // 승인시간 (모든 결제 수단 공통)
    	$amount         = "";                         // KCP 실제 거래 금액
    	$total_amount   = 0;                          // 복합결제시 총 거래금액
    	$coupon_mny		= "";						  // 쿠폰금액
        /* = -------------------------------------------------------------------------- = */
        $card_cd        = "";                         // 신용카드 코드
        $card_name      = "";                         // 신용카드 명
        $app_no         = "";                         // 신용카드 승인번호
        $noinf          = "";                         // 신용카드 무이자 여부
        $quota          = "";                         // 신용카드 할부개월
    	$partcanc_yn    = "";						  // 부분취소 가능유무
    	$card_bin_type_01 = "";                       // 카드구분1
    	$card_bin_type_02 = "";                       // 카드구분2
    	$card_mny		= "";						  // 카드결제금액
        /* = -------------------------------------------------------------------------- = */
    	$bank_name      = "";                         // 은행명
    	$bank_code      = "";						  // 은행코드
    	$bk_mny			= "";						  // 계좌이체결제금액
    	/* = -------------------------------------------------------------------------- = */
        $bankname       = "";                         // 입금할 은행명
        $depositor      = "";                         // 입금할 계좌 예금주 성명
        $account        = "";                         // 입금할 계좌 번호
    	$va_date		= "";						  // 가상계좌 입금마감시간
        /* = -------------------------------------------------------------------------- = */
    	$pnt_issue      = "";                         // 결제 포인트사 코드
    	$pt_idno        = "";                         // 결제 및 인증 아이디
    	$pnt_amount     = "";                         // 적립금액 or 사용금액
    	$pnt_app_time   = "";                         // 승인시간
    	$pnt_app_no     = "";                         // 승인번호
        $add_pnt        = "";                         // 발생 포인트
    	$use_pnt        = "";                         // 사용가능 포인트
    	$rsv_pnt        = "";                         // 총 누적 포인트
        /* = -------------------------------------------------------------------------- = */
    	$commid         = "";                         // 통신사 코드
    	$mobile_no      = "";                         // 휴대폰 번호
    	/* = -------------------------------------------------------------------------- = */
    	$tk_shop_id		= empty($_POST[ "tk_shop_id"     ]) ? "" : $_POST[ "tk_shop_id"     ]; // 가맹점 고객 아이디
    	$tk_van_code    = "";                         // 발급사 코드
    	$tk_app_no      = "";                         // 상품권 승인 번호
    	/* = -------------------------------------------------------------------------- = */
        $cash_yn        = $_POST[ "cash_yn"        ]; // 현금영수증 등록 여부
        $cash_authno    = "";                         // 현금 영수증 승인 번호
        $cash_tr_code   = $_POST[ "cash_tr_code"   ]; // 현금 영수증 발행 구분
        $cash_id_info   = $_POST[ "cash_id_info"   ]; // 현금 영수증 등록 번호
        /* = -------------------------------------------------------------------------- = */
        $trace_no       = $_POST[ "trace_no"       ];
    
        /* ============================================================================== */
    
        /* ============================================================================== */
        /* =   02. 인스턴스 생성 및 초기화                                              = */
        /* = -------------------------------------------------------------------------- = */
        /* =       결제에 필요한 인스턴스를 생성하고 초기화 합니다.                     = */
        /* = -------------------------------------------------------------------------- = */
        $c_PayPlus = new C_PP_CLI;
    
        $c_PayPlus->mf_clear();
        /* ------------------------------------------------------------------------------ */
    	/* =   02. 인스턴스 생성 및 초기화 END											= */
    	/* ============================================================================== */
    
    
        /* ============================================================================== */
        /* =   03. 처리 요청 정보 설정                                                  = */
        /* = -------------------------------------------------------------------------- = */
    
        /* = -------------------------------------------------------------------------- = */
        /* =   03-1. 승인 요청                                                          = */
        /* = -------------------------------------------------------------------------- = */
        if ( $req_tx == "pay" )
        {   
    		    /* 1004원은 실제로 업체에서 결제하셔야 될 원 금액을 넣어주셔야 합니다. 결제금액 유효성 검증 */
                $c_PayPlus->mf_set_ordr_data( "ordr_mony",  $good_mny );
                $c_PayPlus->mf_set_encx_data( $_POST[ "enc_data" ], $_POST[ "enc_info" ] );
        }
    
        /* = -------------------------------------------------------------------------- = */
        /* =   03-2. 취소/매입 요청                                                     = */
        /* = -------------------------------------------------------------------------- = */
        else if ( $req_tx == "mod" )
        {
            $tran_cd = "00200000";
    
            $c_PayPlus->mf_set_modx_data( "tno",      $tno      ); // KCP 원거래 거래번호
            $c_PayPlus->mf_set_modx_data( "mod_type", $mod_type ); // 원거래 변경 요청 종류
            $c_PayPlus->mf_set_modx_data( "mod_ip",   $cust_ip  ); // 변경 요청자 IP
            $c_PayPlus->mf_set_modx_data( "mod_desc", $mod_desc ); // 변경 사유
        }
    	/* ------------------------------------------------------------------------------ */
    	/* =   03.  처리 요청 정보 설정 END  											= */
    	/* ============================================================================== */
    
    
        if ( isset($g_conf_key_dir) ) $g_conf_key_dir = "";
        if ( isset($g_conf_log_dir) ) $g_conf_log_dir = "";
        
        /* ============================================================================== */
        /* =   04. 실행                                                                 = */
        /* = -------------------------------------------------------------------------- = */
        if ( $tran_cd != "" )
        {
            $c_PayPlus->mf_do_tx( $trace_no, $g_conf_home_dir, $g_conf_site_cd, "", $tran_cd, "",
                                  $g_conf_gw_url, $g_conf_gw_port, "payplus_cli_slib", $ordr_idxx,
                                  $cust_ip, "3" , 0, 0); // 응답 전문 처리
    		
    		$res_cd  = $c_PayPlus->m_res_cd;  // 결과 코드
    		$res_msg = $c_PayPlus->m_res_msg; // 결과 메시지
    		/* $res_en_msg = $c_PayPlus->mf_get_res_data( "res_en_msg" );  // 결과 영문 메세지 */ 
        }
        else
        {
            $c_PayPlus->m_res_cd  = "9562";
            $c_PayPlus->m_res_msg = "연동 오류|Payplus Plugin이 설치되지 않았거나 tran_cd값이 설정되지 않았습니다.";
        }
    
        
        /* = -------------------------------------------------------------------------- = */
        /* =   04. 실행 END                                                             = */
        /* ============================================================================== */
    
    
        /* ============================================================================== */
        /* =   05. 승인 결과 값 추출                                                    = */
        /* = -------------------------------------------------------------------------- = */
        if ( $req_tx == "pay" )
        {
            if( $res_cd == "0000" )
            {
                $tno       = $c_PayPlus->mf_get_res_data( "tno"       ); // KCP 거래 고유 번호
                $amount    = $c_PayPlus->mf_get_res_data( "amount"    ); // KCP 실제 거래 금액
    			$pnt_issue = $c_PayPlus->mf_get_res_data( "pnt_issue" ); // 결제 포인트사 코드
    			$coupon_mny = $c_PayPlus->mf_get_res_data( "coupon_mny" ); // 쿠폰금액
    
        /* = -------------------------------------------------------------------------- = */
        /* =   05-1. 신용카드 승인 결과 처리                                            = */
        /* = -------------------------------------------------------------------------- = */
                if ( $use_pay_method == "100000000000" )
                {
                    $card_cd   = $c_PayPlus->mf_get_res_data( "card_cd"   ); // 카드사 코드
                    $card_name = $c_PayPlus->mf_get_res_data( "card_name" ); // 카드 종류
                    $app_time  = $c_PayPlus->mf_get_res_data( "app_time"  ); // 승인 시간
                    $app_no    = $c_PayPlus->mf_get_res_data( "app_no"    ); // 승인 번호
                    $noinf     = $c_PayPlus->mf_get_res_data( "noinf"     ); // 무이자 여부 ( 'Y' : 무이자 )
                    $quota     = $c_PayPlus->mf_get_res_data( "quota"     ); // 할부 개월 수
    				$partcanc_yn = $c_PayPlus->mf_get_res_data( "partcanc_yn" ); // 부분취소 가능유무
    				$card_bin_type_01 = $c_PayPlus->mf_get_res_data( "card_bin_type_01" ); // 카드구분1
    				$card_bin_type_02 = $c_PayPlus->mf_get_res_data( "card_bin_type_02" ); // 카드구분2
    				$card_mny = $c_PayPlus->mf_get_res_data( "card_mny" ); // 카드결제금액
    
                    /* = -------------------------------------------------------------- = */
                    /* =   05-1.1. 복합결제(포인트+신용카드) 승인 결과 처리               = */
                    /* = -------------------------------------------------------------- = */
                    if ( $pnt_issue == "SCSK" || $pnt_issue == "SCWB" )
                    {
    					$pt_idno      = $c_PayPlus->mf_get_res_data ( "pt_idno"      ); // 결제 및 인증 아이디    
                        $pnt_amount   = $c_PayPlus->mf_get_res_data ( "pnt_amount"   ); // 적립금액 or 사용금액
    	                $pnt_app_time = $c_PayPlus->mf_get_res_data ( "pnt_app_time" ); // 승인시간
    	                $pnt_app_no   = $c_PayPlus->mf_get_res_data ( "pnt_app_no"   ); // 승인번호
    	                $add_pnt      = $c_PayPlus->mf_get_res_data ( "add_pnt"      ); // 발생 포인트
                        $use_pnt      = $c_PayPlus->mf_get_res_data ( "use_pnt"      ); // 사용가능 포인트
                        $rsv_pnt      = $c_PayPlus->mf_get_res_data ( "rsv_pnt"      ); // 총 누적 포인트
    					$total_amount = $amount + $pnt_amount;                          // 복합결제시 총 거래금액
                    }
                }
    
        /* = -------------------------------------------------------------------------- = */
        /* =   05-2. 계좌이체 승인 결과 처리                                            = */
        /* = -------------------------------------------------------------------------- = */
                if ( $use_pay_method == "010000000000" )
                {
    				$app_time  = $c_PayPlus->mf_get_res_data( "app_time"   );  // 승인 시간
                    $bank_name = $c_PayPlus->mf_get_res_data( "bank_name"  );  // 은행명
                    $bank_code = $c_PayPlus->mf_get_res_data( "bank_code"  );  // 은행코드
    				$bk_mny = $c_PayPlus->mf_get_res_data( "bk_mny" ); // 계좌이체결제금액
                }
    
        /* = -------------------------------------------------------------------------- = */
        /* =   05-3. 가상계좌 승인 결과 처리                                            = */
        /* = -------------------------------------------------------------------------- = */
                if ( $use_pay_method == "001000000000" )
                {
                    $bankname  = $c_PayPlus->mf_get_res_data( "bankname"  ); // 입금할 은행 이름
                    $depositor = $c_PayPlus->mf_get_res_data( "depositor" ); // 입금할 계좌 예금주
                    $account   = $c_PayPlus->mf_get_res_data( "account"   ); // 입금할 계좌 번호
                    $va_date   = $c_PayPlus->mf_get_res_data( "va_date"   ); // 가상계좌 입금마감시간
                }
    
        /* = -------------------------------------------------------------------------- = */
        /* =   05-4. 포인트 승인 결과 처리                                               = */
        /* = -------------------------------------------------------------------------- = */
                if ( $use_pay_method == "000100000000" )
                {
    				$pt_idno      = $c_PayPlus->mf_get_res_data( "pt_idno"      ); // 결제 및 인증 아이디
                    $pnt_amount   = $c_PayPlus->mf_get_res_data( "pnt_amount"   ); // 적립금액 or 사용금액
    	            $pnt_app_time = $c_PayPlus->mf_get_res_data( "pnt_app_time" ); // 승인시간
    	            $pnt_app_no   = $c_PayPlus->mf_get_res_data( "pnt_app_no"   ); // 승인번호 
    	            $add_pnt      = $c_PayPlus->mf_get_res_data( "add_pnt"      ); // 발생 포인트
                    $use_pnt      = $c_PayPlus->mf_get_res_data( "use_pnt"      ); // 사용가능 포인트
                    $rsv_pnt      = $c_PayPlus->mf_get_res_data( "rsv_pnt"      ); // 적립 포인트
                }
    
        /* = -------------------------------------------------------------------------- = */
        /* =   05-5. 휴대폰 승인 결과 처리                                              = */
        /* = -------------------------------------------------------------------------- = */
                if ( $use_pay_method == "000010000000" )
                {
    				$app_time  = $c_PayPlus->mf_get_res_data( "hp_app_time"  ); // 승인 시간
    				$commid    = $c_PayPlus->mf_get_res_data( "commid"	     ); // 통신사 코드
    				$mobile_no = $c_PayPlus->mf_get_res_data( "mobile_no"	 ); // 휴대폰 번호
                }
    
        /* = -------------------------------------------------------------------------- = */
        /* =   05-6. 상품권 승인 결과 처리                                              = */
        /* = -------------------------------------------------------------------------- = */
                if ( $use_pay_method == "000000001000" )
                {
    				$app_time    = $c_PayPlus->mf_get_res_data( "tk_app_time"  ); // 승인 시간
    				$tk_van_code = $c_PayPlus->mf_get_res_data( "tk_van_code"  ); // 발급사 코드
    				$tk_app_no   = $c_PayPlus->mf_get_res_data( "tk_app_no"    ); // 승인 번호
                }
    
        /* = -------------------------------------------------------------------------- = */
        /* =   05-7. 현금영수증 결과 처리                                               = */
        /* = -------------------------------------------------------------------------- = */
                $cash_authno  = $c_PayPlus->mf_get_res_data( "cash_authno"  ); // 현금 영수증 승인 번호
           
    		}
    	}
    	/* = -------------------------------------------------------------------------- = */
        /* =   05. 승인 결과 처리 END                                                   = */
        /* ============================================================================== */
    
    	/* ============================================================================== */
        /* =   06. 승인 및 실패 결과 DB처리                                             = */
        /* = -------------------------------------------------------------------------- = */
    	/* =       결과를 업체 자체적으로 DB처리 작업하시는 부분입니다.                 = */
        /* = -------------------------------------------------------------------------- = */
    
    	if ( $req_tx == "pay" )
        {
    		if( $res_cd == "0000" )
            {
    			// 06-1-1. 신용카드
    			if ( $use_pay_method == "100000000000" )
                {
    				// 06-1-1-1. 복합결제(신용카드 + 포인트)
    				if ( $pnt_issue == "SCSK" || $pnt_issue == "SCWB" )
                    {
    				}
    			}
    			// 06-1-2. 계좌이체
    			if ( $use_pay_method == "010000000000" )
                {
    			}
    			// 06-1-3. 가상계좌
    			if ( $use_pay_method == "001000000000" )
                {
    			}
    			// 06-1-4. 포인트
    			if ( $use_pay_method == "000100000000" )
                {
    			}
    			// 06-1-5. 휴대폰
    			if ( $use_pay_method == "000010000000" )
                {
    			}
    			// 06-1-6. 상품권
    			 if ( $use_pay_method == "000000001000" )
                {
    			}
    		}
    
    	/* = -------------------------------------------------------------------------- = */
        /* =   06. 승인 및 실패 결과 DB처리                                             = */
        /* ============================================================================== */
    		else if ( $res_cd != "0000" )
    		{
    		}
    	}
    	
    	/* ============================================================================== */
        /* =   07. 승인 결과 DB처리 실패시 : 자동취소                                   = */
        /* = -------------------------------------------------------------------------- = */
        /* =         승인 결과를 DB 작업 하는 과정에서 정상적으로 승인된 건에 대해      = */
        /* =         DB 작업을 실패하여 DB update 가 완료되지 않은 경우, 자동으로       = */
        /* =         승인 취소 요청을 하는 프로세스가 구성되어 있습니다.                = */
    	/* =                                                                            = */
        /* =         DB 작업이 실패 한 경우, bSucc 라는 변수(String)의 값을 "false"     = */
        /* =         로 설정해 주시기 바랍니다. (DB 작업 성공의 경우에는 "false" 이외의 = */
        /* =         값을 설정하시면 됩니다.)                                           = */
        /* = -------------------------------------------------------------------------- = */
        
    	$bSucc = ""; // DB 작업 실패 또는 금액 불일치의 경우 "false" 로 세팅
    
        /* = -------------------------------------------------------------------------- = */
        /* =   07-1. DB 작업 실패일 경우 자동 승인 취소                                 = */
        /* = -------------------------------------------------------------------------- = */
        if ( $req_tx == "pay" )
        {
    		if( $res_cd == "0000" )
            {	
    			if ( $bSucc == "false" )
                {
                    $c_PayPlus->mf_clear();
    
                    $tran_cd = "00200000";
    
                    $c_PayPlus->mf_set_modx_data( "tno",      $tno                         );  // KCP 원거래 거래번호
                    $c_PayPlus->mf_set_modx_data( "mod_type", "STSC"                       );  // 원거래 변경 요청 종류
                    $c_PayPlus->mf_set_modx_data( "mod_ip",   $cust_ip                     );  // 변경 요청자 IP
                    $c_PayPlus->mf_set_modx_data( "mod_desc", "결과 처리 오류 - 자동 취소" );  // 변경 사유
    
                    $c_PayPlus->mf_do_tx( $tno,  $g_conf_home_dir, $g_conf_site_cd,
                                          "",  $tran_cd,    "",
                                          $g_conf_gw_url,  $g_conf_gw_port,  "payplus_cli_slib",
                                          $ordr_idxx, $cust_ip, "3" ,
                                          0, 0, $g_conf_key_dir, $g_conf_log_dir);
    
                    $res_cd  = $c_PayPlus->m_res_cd;
                    $res_msg = $c_PayPlus->m_res_msg;
                }
            }
    	} // End of [res_cd = "0000"]
	}

    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     * /
	function receipt_page( $order_id ) {

		echo '<p>'.__( '주문해주셔서 감사합니다. KCP 결제 버튼을 누루시면 결제창이 뜹니다.', 'woocommerce' ).'</p>';
		
		
        
		echo $this->generate_KCP_form( $order );
	}
	*/
	
    /**
	 * Generate the KCP button link
     *
     * @access public
     * @param mixed $order_id
     * @return string
     */
    function generate_KCP_form( $order_id ) {
		
		global $woocommerce;
		
		$order = new WC_Order( $order_id );

        /* ============================================================================== */
        /* =   01. 지불 데이터 셋업 (업체에 맞게 수정)                                  = */
        /* = -------------------------------------------------------------------------- = */
		//$g_conf_home_dir = "/www/public_html/wp-content/plugins/woocommerce-KCP-plugin/kcp/modules/";
		$g_conf_home_dir = plugin_dir_path( __FILE__ )."kcp/modules/";

        /* ============================================================================== */
        /* =   02. 쇼핑몰 지불 정보 설정                                                = */
        /* = -------------------------------------------------------------------------- = */
		$g_conf_site_cd   = $this->site_cd;
		$g_conf_site_key  = $this->site_key;
		$g_conf_site_name = $this->site_name;
        
		if ( $this->testmode == 'yes' ):
			$g_conf_gw_url = $this->test_gw_url;
			$g_conf_js_url = $this->test_js_url;
		else :
			$g_conf_gw_url = $this->live_gw_url;
			$g_conf_js_url = $this->live_js_url;
		endif;
		
		$kcp_args = $this->get_kcp_args( $order );
        $kcp_args_array = array();
        foreach($kcp_args as $key => $value){
			$kcp_args_array[] = "
			<input type='hidden' name='".esc_attr( $key )."' value='".esc_attr( $value )."'/>";
        }
		
		$thanks_url = get_permalink( woocommerce_get_page_id( 'thanks' ) );
		$thanks_url = add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order->id, $thanks_url ) );
		//		    <form action="'.get_permalink(woocommerce_get_page_id('thanks')).'" method="post" name="order_info" onSubmit="return jsf__pay( this );" target="_top"> 
        
		return '
			<script type="text/javascript" src="'. $g_conf_js_url .'"></script>
			
			<script type="text/javascript">StartSmartUpdate();</script>
			
		    <form action="'.$thanks_url.'" method="post" name="order_info" onSubmit="return jsf__pay( this );" target="_top"> 
				' . implode( '', $kcp_args_array ) . '
				<input type="hidden" name="g_conf_home_dir"  value="' . $g_conf_home_dir  .'" /> 
				<input type="hidden" name="g_conf_site_cd"   value="' . $g_conf_site_cd   .'" /> 
				<input type="hidden" name="g_conf_site_key"  value="' . $g_conf_site_key  .'" /> 
				<input type="hidden" name="g_conf_site_name" value="' . $g_conf_site_name .'" /> 
				<input type="hidden" name="g_conf_gw_url"    value="' . $g_conf_gw_url    .'" /> 
				<input type="hidden" name="g_conf_js_url"    value="' . $g_conf_js_url    .'" /> 
				
				
				<input type="submit" class="button alt" id="submit_KCP_payment_form" value="' . __( 'KCP 결제', 'woocommerce' ) . '" /> 
				<a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__( 'Cancel order &amp; restore cart', 'woocommerce' ).'</a>
			</form>
			
			<script type="text/javascript">
			/* Payplus Plug-in 실행 */
			function  jsf__pay( form )
			{
				var RetVal = false;
				
				///* Payplus Plugin 실행 */
				if ( MakePayMessage( form ) == true )
				{
					RetVal = true ;
				}
				else
				{
					/*  res_cd와 res_msg변수에 해당 오류코드와 오류메시지가 설정됩니다.
						ex) 고객이 Payplus Plugin에서 취소 버튼 클릭시 res_cd=3001, res_msg=사용자 취소
						값이 설정됩니다.
					*/
					res_cd  = form.res_cd.value ;
					res_msg = form.res_msg.value ;
				
				}
				
				return RetVal;
			}

			// Payplus Plug-in 설치 안내 
			function init_pay_button()
			{
				if (navigator.userAgent.indexOf("MSIE") > 0)
				{
					try
					{
						if( document.Payplus.object == null )
						{
							document.getElementById("display_setup_message").style.display = "block" ;
						}
						else{
							document.getElementById("display_pay_button").style.display = "block" ;
						}
					}
					catch (e)
					{
						document.getElementById("display_setup_message").style.display = "block" ;
					}
				}
				else
				{
					try
					{
						if( Payplus == null )
						{
							document.getElementById("display_setup_message").style.display = "block" ;
						}
						else{
							document.getElementById("display_pay_button").style.display = "block" ;
						}
					}
					catch (e)
					{
						document.getElementById("display_setup_message").style.display = "block" ;
					}
				}
			};
        </script>';
	}
	/**
	 * Get kcp Args for passing to PP
	 *
	 * @access public
	 * @param mixed $order
	 * @return array
	 */
	function get_kcp_args( $order ) {
		global $woocommerce;

		if (in_array($order->billing_country, array('KO','US','CA'))) :
			$order->billing_phone = str_replace( array( '(', '-', ' ', ')', '.' ), '', $order->billing_phone );
			$phone_args = array(
				'night_phone_a' => substr($order->billing_phone,0,3),
				'night_phone_b' => substr($order->billing_phone,3,3),
				'night_phone_c' => substr($order->billing_phone,6,4),
				'day_phone_a' 	=> substr($order->billing_phone,0,3),
				'day_phone_b' 	=> substr($order->billing_phone,3,3),
				'day_phone_c' 	=> substr($order->billing_phone,6,4)
			);
		else :
			$phone_args = array(
				'night_phone_b' => $order->billing_phone,
				'day_phone_b' 	=> $order->billing_phone
			);
		endif;

		foreach ($woocommerce->cart->get_cart() as $item_id => $values) :
				$_product = $values['data'];
		endforeach;

		$kcp_args = array_merge(
			array(
				'ordr_idxx'      => 'kcpshop-' . $order->id,
				'good_name'      => $_product->get_title(),
				'good_mny'       => round( $order->order_total ),
				'buyr_name'      => $order->billing_first_name.' '.$order->billing_last_name,
				'buyr_mail'      => $order->billing_email,
				'buyr_tel1'      => $order->billing_phone,
				'buyr_tel2'      => $order->billing_phone,
				'pay_method'     => '100000000000',
				'req_tx'         => 'pay',
				'site_cd'        => $this->site_cd,
				'site_name'      => $this->site_name,
				'quotaopt'       => '12',
				'currency'       => 'WON',
				'module_type'    => '01',
				'epnt_issu'      => '',
				'res_cd'         => '',
				'res_msg'        => '',
				'tno'            => '',
				'trace_no'       => '',
				'enc_info'       => '',
				'enc_data'       => '',
				'ret_pay_method' => '',
				'tran_cd'        => '',
				'bank_name'      => '',
				'bank_issu'      => '',
				'use_pay_method' => '',
				'cash_tsdtime'   => '',
				'cash_yn'        => '',
				'cash_authno'    => '',
				'cash_tr_code'   => '',
				'cash_id_info'   => '',
				'good_expr'      => '0'
			),
			$phone_args
		);

		// Shipping
		if ( $this->send_shipping=='yes' ) {
			$kcp_args['address_override'] = ( $this->address_override == 'yes' ) ? 1 : 0;

			$kcp_args['no_shipping'] = 0;

			// If we are sending shipping, send shipping address instead of billing
			$kcp_args['first_name']		= $order->shipping_first_name;
			$kcp_args['last_name']		= $order->shipping_last_name;
			$kcp_args['company']			= $order->shipping_company;
			$kcp_args['address1']		= $order->shipping_address_1;
			$kcp_args['address2']		= $order->shipping_address_2;
			$kcp_args['city']			= $order->shipping_city;
			$kcp_args['state']			= $order->shipping_state;
			$kcp_args['country']			= $order->shipping_country;
			$kcp_args['zip']				= $order->shipping_postcode;
		} else {
			$kcp_args['no_shipping'] = 1;
		}
		
		$kcp_args = apply_filters( 'woocommerce_kcp_args', $kcp_args );

		return $kcp_args;
	}
	
	/**
     * Process the payment and return the result
     *
     * @access public
     * @param int $order_id
     * @return array
     */
	function process_payment( $order_id ) {
    	global $woocommerce;

		$order = new WC_Order( $order_id );
		
		return array(
			'result' 	=> 'success',
			'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(get_option('woocommerce_pay_page_id'))))
    		);
	}
	


    /**
     * Output for the order received page.
     *
     * @access public
     * @return void
     */
    function thankyou_page() {
		global $woocommerce;
		
		$order_id = substr( $_POST[ "ordr_idxx" ], 8);
		$order = new WC_Order( $order_id );
		
    	if ( ! empty( $_POST["res_cd"] ) && $_POST["res_cd"] == "0000" ) {
    	    
    	    do_action( 'check_ipn_response' );
    	    
    		// Mark as on-hold (we're awaiting the cheque)
    		$order->update_status('on-hold', __('Awaiting KCP payment', 'woocommerce'));
    
    		// Reduce stock levels
    		$order->reduce_order_stock();
    
    		// Remove cart
    		$woocommerce->cart->empty_cart();
    
    		// Empty awaiting payment session
    		unset($_SESSION['order_awaiting_payment']);
			
			if ( $description = $this->get_description() )
				echo wpautop( wptexturize( wp_kses_post( $description ) ) );
    	}
    }

	

    public function is_valid_for_use() {

        if ( !in_array( get_woocommerce_currency(), $this->supported_currencies ) ) {
            return false;
        }
        
        return true;
    }
	
    public function kcp_card_args( $args ) {
        
        if( $kcp_currency = $this->kcp_currencies_args[ $args['goodcurrency'] ] ) {
            
            $args['langcode']       = $kcp_currency['langcode'];
            $args['goodcurrency']   = $kcp_currency['goodcurrency'];
        }
        
        return $args;
    }
    
	/**
	 * init_kcp function.
	 *
	 * @access public
	 */
	public function init_kcp() {
	    require_once '/KCP/cfg/site_conf_inc.php';
	}
	
}

class WC_KCP extends WC_Gateway_KCP_card {
	public function __construct() {
		_deprecated_function( 'WC_KCP', '1.4', 'WC_Gateway_KCP' );
		parent::__construct();
	}
}


endif;
