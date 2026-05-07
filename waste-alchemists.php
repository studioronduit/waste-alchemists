<?php
/**
 * Plugin Name: Waste Alchemists
 * Plugin URI:  https://www.wastealchemists.com/
 * Description: Waste Alchemists plugin
 * Version:     0.9.7
 * Author:      S. A. Wagner, D. Jacobs, P. Eg
 * License:     GPL2
 */
 
 // ─── Self-hosted update checker ───────────────────────────────────────────
 
 add_filter( 'plugins_api', 'wastealch_plugin_info', 20, 3 );
 add_filter( 'site_transient_update_plugins', 'wastealch_check_for_update' );
 add_action( 'upgrader_process_complete', 'wastealch_purge_cache', 10, 2 );
 
 function wastealch_get_remote_info() {
	 $remote = get_transient( 'wastealch_update_check' );
 
	 if ( ! $remote ) {
		 $remote = wp_remote_get( 'https://raw.githubusercontent.com/studioronduit/waste-alchemists/main/info.json', [
			 'timeout' => 10,
		 ] );
 
		 if ( is_wp_error( $remote ) || 200 !== wp_remote_retrieve_response_code( $remote ) ) {
			 return false;
		 }
 
		 set_transient( 'wastealch_update_check', $remote, 12 * HOUR_IN_SECONDS );
	 }
 
	 return json_decode( wp_remote_retrieve_body( $remote ) );
 }
 
 function wastealch_check_for_update( $transient ) {
	 if ( empty( $transient->checked ) ) return $transient;
 
	 $remote = wastealch_get_remote_info();
	 if ( ! $remote ) return $transient;
 
	 if ( version_compare( $remote->version, $transient->checked['waste-alchemists/waste-alchemists.php'], '>' ) ) {
		 $transient->response['waste-alchemists/waste-alchemists.php'] = (object) [
			 'slug'        => 'waste-alchemists',
			 'plugin'      => 'waste-alchemists/waste-alchemists.php',
			 'new_version' => $remote->version,
			 'package'     => $remote->download_url,
		 ];
	 }
 
	 return $transient;
 }
 
 function wastealch_plugin_info( $res, $action, $args ) {
	 if ( 'plugin_information' !== $action || 'waste-alchemists' !== $args->slug ) return $res;
 
	 $remote = wastealch_get_remote_info();
	 if ( ! $remote ) return $res;
 
	 return (object) [
		 'name'          => $remote->name,
		 'slug'          => $remote->slug,
		 'version'       => $remote->version,
		 'author'        => $remote->author,
		 'download_link' => $remote->download_url,
		 'sections'      => (array) $remote->sections,
	 ];
 }
 
 function wastealch_purge_cache( $upgrader, $options ) {
	 if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
		 delete_transient( 'wastealch_update_check' );
	 }
 }
 
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
  });
  
  function waste_alchemists_settingstemplate_callback() {
	?>
		<h2>Waste Alchemists</h2>
		<form method="post" action="options.php">
		<?php settings_fields( 'waste_alchemists_settings_group' ); ?>
		<table class="form-table">
			<tr>
				<th>Webhook URL</th>
				<td>
					<input 
						type="text" 
						name="wastealch_webhook_url" 
						value="<?php echo esc_attr( get_option( 'wastealch_webhook_url' ) ); ?>" 
						class="regular-text"
					/>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
		</form>
	<?php
	}
  
  add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'wastealch_settings_link' );
  
  function wastealch_settings_link( $settings ) {
	 $settings[] = '<a href="'. get_admin_url(null, 'options-general.php?page=waste_alchemists_settings') .'">Settings</a>';
	 return $settings;
  }

 
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
				'bedrag'    => $order->get_total(),
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
