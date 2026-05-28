<?php
/**
 * Plugin Name: Waste Alchemists
 * Plugin URI:  https://www.wastealchemists.com/
 * Version:     1.0.1
 * Author:      S. A. Wagner, D. Jacobs, P. Eg
 * License:     GPL2
 */

require_once plugin_dir_path( __FILE__ ) . 'update.php';

 
 // ─── Settings ───────────────────────────────────────────────
 
  function wastealch_settings_menu() {
  
	 add_submenu_page (
		 'options-general.php',
		 __( 'Waste Alchemists', 'my-plugin' ),
		 __( 'Waste Alchemists', 'my-plugin' ),
		 'manage_options',
		 'waste_alchemists_settings',
		 'waste_alchemists_settingstemplate_callback'
	 ) ;
	 
  } add_action('admin_menu', "wastealch_settings_menu");
  
  add_action( 'admin_init', function() {
	  register_setting( 'waste_alchemists_settings_group', 'wastealch_webhook_url' );
	  
	  add_filter( 'pre_update_option_wastealch_webhook_url', function( $new_value ) {
			if ( ! preg_match( '/^(https?:\/\/)?[\w.-]+\/[a-zA-Z0-9]{64}$/', $new_value ) ) {
				add_settings_error(
					'wastealch_webhook_url',
					'invalid_url',
					'Invalid URL. Need help? Just <a href="https://wastealchemists.com/contact/" target="_blank">contact</a> us for more information, and we will help you out. ',
					'error'
				);
				return get_option( 'wastealch_webhook_url' ); // oude waarde behouden
			}
			return $new_value;
		});
		
  });
  
  function waste_alchemists_settingstemplate_callback() {
	?>
	<section style="max-width: 800px; padding: 20px; background: white;margin:auto; display: flex; flex-flow: column nowrap; gap: 20px;">
		<section style="padding: 20px; height: 300px; background: url('<?= plugin_dir_url(__FILE__) ?>/assets/banner-1544x500.png') center/cover; border-radius: 20px; ">
			
		</section>
		<section>
			<section>
				<h2>Waste Alchemists Settings</h2>
				<p>Please fill in the API URL with your secret key. No secret key yet? Just contact us for more information, and we will help you out.
				<a href="https://wastealchemists.com/contact/" target="_blank">Wastealchemists.com</a></p>
			</section>
			<form method="post" action="options.php">
				<?php settings_fields( 'waste_alchemists_settings_group' ); ?>
				<table class="form-table">
					<tr>
						<th style="width: 100px;">API URL</th>
						<td>
							<input 
								type="text" 
								name="wastealch_webhook_url" 
								value="<?php echo esc_attr( get_option( 'wastealch_webhook_url' ) ); ?>" 
								class="regular-text"
								pattern="(https?://)?[\w.-]+/[a-zA-Z0-9]{64}" required
							/>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</section>
		<section style="display: flex; flex-flow: row wrap; align-items: end; justify-content: space-between;">
			<img src="<?= plugin_dir_url(__FILE__) ?>/assets/icon-256x256.png" alt="Waste Alchemists logo" width="70" height="70"/>
			<p style="font-size: 12px;">Copyright Waste Alchemists 2026. <a href="https://www.wastealchemists.com/" target="_blank";>wastealchemists.com</a></p> 
		</section>
	</section>
	<?php
	}
  
  add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wastealch_settings_link' );
  
  function wastealch_settings_link( $settings ) {
	 $settings[] = '<a href="'. get_admin_url(null, 'options-general.php?page=waste_alchemists_settings') .'">Settings</a>';
	 return $settings;
  }
  
  // ─── Admin notice als URL niet ingevuld is ───────────────────
  
  add_action('admin_notices', function() {
	  $url = get_option('wastealch_webhook_url');
	  if (empty($url)) {
		  echo '<div class="notice notice-error" style="display: flex; flex-flow: row wrap; align-items: center; gap: 12px;">
			  <img src="' . plugin_dir_url(__FILE__) . '/assets/icon-256x256.png" alt="Waste Alchemists logo" width="100" height="100"/>
			  <div style="display: flex; flex-flow: column wrap; align-items: start; justify-content: start;">
				<p style="margin: 0 "><strong>Waste Alchemists</strong></p>
				<p style="margin: 0 ">The API URL has not been filled in yet. The integration will not work until you set this under
				<a href="'. get_admin_url(null, 'options-general.php?page=waste_alchemists_settings') .'">Settings → Waste Alchemists</a>.
				</p>
			  </div>
		  </div>';
	  }
  });

 
// ─── Referral opslaan in cookie (alle pagina's) ───────────────────

add_action('wp_head', 'wastealch_hook_javascript');

function wastealch_hook_javascript() {
	?>
		<script>
		(function () {
			const params = new URLSearchParams(window.location.search);
			const ref    = params.get('ref');
			if (ref === 'wastealchemists') {
				// Cookie voor 30 dagen
				const expires = new Date(Date.now() + 3 * 30 * 24 * 60 * 60 * 1000).toUTCString();
				document.cookie = 'referral=wastealchemists; expires=' + expires + '; path=/; SameSite=Lax';
			} 
			
			console.log('Referral cookie:', document.cookie
				.split('; ')
				.find(row => row.startsWith('referral='))
				?.split('=')[1] ?? 'niet gevonden'
			);
		})();
		
		

		</script>
	<?php
}

// ─── Bedankpagina: stuur order door via proxy ─────────────────────────────


add_action( 'woocommerce_thankyou', 'wastealch_send_order' );

function wastealch_send_order( $order_id ) {
	$order    = wc_get_order( $order_id );
	$referral = $_COOKIE['referral'] ?? '';

	if ( $referral !== 'wastealchemists' ) return;

	if ( $order->get_meta( 'wastealch_sent' ) ) return;
	$order->update_meta_data( 'wastealch_sent', true );
	$order->save();

	$products = [];
	foreach ( $order->get_items() as $item ) {
		$products[] = [
			'naam'      => $item->get_name(),
			'aantal'    => $item->get_quantity(),
			'subtotaal' => $item->get_subtotal(),
		];
	}

	wp_remote_post( get_option( 'wastealch_webhook_url' ), [
		'headers' => [
			'Content-Type' => 'application/json',
			'X-Wastealch-Platform' => 'wordpress',
		],
		'body' => wp_json_encode([
			'type'       => 'bestelling',
			'referral'   => $referral,
			'bestelling' => [
				'order_id'  => $order_id,
				'bedrag'    => $order->get_subtotal(),
				'producten' => $products,
			]
		]),
		'timeout' => 10,
	]);
}
 
 // ─── Activechecker ───────────────────────────────────────────
 
	//add_action('wp_head', 'wastealch_activechecker');
	
	// function wastealch_activechecker() {
	// 	$allowed_ips = ['ips'];
	// 	
	// 	if (in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
	// 		plugin_active_tracker();
	// 	}
	// }
	
	function plugin_active_tracker( ) {
		$nonce = wp_create_nonce('wp_rest');
		$plugin_active = false;
		if ( is_plugin_active( 'waste-alchemists/waste-alchemists.php' ) ) {
			$plugin_active = true;
		}
	?>
	<script>
	alert("<?php echo get_option( 'wastealch_webhook_url' ) ; ?>");
	
	(async () => {
		const response = await fetch('<?php echo esc_url( rest_url( 'wastealch/v1/referral' ) ); ?>', {
			method:  'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce':   '<?php echo esc_js( $nonce ); ?>',
				'HTTP_X_API_KEY': '<?php echo wp_json_encode( get_option( 'wastealch_api_key' ) ); ?>',
			},
			body: JSON.stringify({
				type:          'activechecker',
				plugin_active: <?php echo wp_json_encode( $plugin_active ); ?>,
				webhook_url:   <?php echo wp_json_encode( get_option( 'wastealch_webhook_url' ) ); ?>
			})
		});
	})();
	</script>
	
	<?php
	}
