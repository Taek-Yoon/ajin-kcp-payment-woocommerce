<h3><?php echo $this->method_title ;?></h3>

    <?php if ( $this->is_valid_for_use() ) : ?>
	<div id="wc_get_started" class="kcp">
		<span class="main"><?php  _e( 'Get started PayGate', 'wc_kcp' ); ?></span>
		<span>
			<a href="https://admin.kcp.net/" target="kcp_admin" ><?php _e('Paygate 상점관리자 ', 'wc_kcp');?></a>
		</span>

		<p><a href="http://www.kcp.net/apply/general.php" target="_blank" class="button button-primary"><?php _e( 'Join', 'wc_kcp' ); ?></a> </p>
	</div>
	<table class="form-table">
		<?php $this->generate_settings_html(); ?>
	</table><!--/.form-table-->
    <?php else : ?>
            <div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'wc_kcp' ); ?></strong>: <?php _e( 'This Gateway does not support your store currency.', 'wc_kcp' ); ?></p></div>
    <?php endif; ?>
