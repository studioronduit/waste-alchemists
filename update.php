<?php

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
		 'homepage'      => $remote->homepage,
		 'requires'      => $remote->requires,
		 'requires_php'  => $remote->requires_php,
		 'tested'        => $remote->tested,
		 'last_updated'  => $remote->last_updated,
		 'version'       => $remote->version,
		 'author'        => $remote->author,
		 'download_link' => $remote->download_url,
		 'sections'      => [
			 'description'  => $remote->sections->description ?? '',
			 'installation' => $remote->sections->installation ?? '',
			 'changelog'    => $remote->sections->changelog ?? '',
			 'faq'          => $remote->sections->FAQ ?? '',
		 ],
		 'screenshots' => array_combine(
			 range( 1, count( (array) $remote->screenshots ) ),
			 array_map( fn($s) => [ 'src' => $s->src, 'caption' => $s->caption ], (array) $remote->screenshots )
		 ),
		 'banners'       => [
			 'low'  => $remote->banners->low ?? '',
			 'high' => $remote->banners->high ?? '',
		 ],
		 'icons' => (array) $remote->icons,
	 ];
 }
 
 add_filter( 'plugin_row_meta', 'wa_plugin_row_meta', 10, 2 );
 
 function wa_plugin_row_meta( $links, $file ) {
	 if ( 'waste-alchemists/waste-alchemists.php' !== $file ) return $links;
 
	 // Controleer of er al een "View details" link is
	 $has_view_details = false;
	 foreach ( $links as $link ) {
		if ( strpos( $link, 'plugin-install.php' ) !== false || strpos( $link, 'thickbox' ) !== false ) {
			$has_view_details = true;
			break;
		 }
	 }
 
	 // Alleen toevoegen als hij er nog niet in zit
	 if ( ! $has_view_details ) {
		 $slug = 'waste-alchemists';
		 $url  = admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $slug . '&TB_iframe=true&width=600&height=550' );
		 array_splice( $links, 1, 0, [ '<a href="' . $url . '" class="thickbox open-plugin-details-modal">View details</a>' ] );
	 }
 
	 return $links;
 }
 
 function wastealch_purge_cache( $upgrader, $options ) {
	 if ( 'update' === $options['action'] && 'plugin' === $options['type'] ) {
		 delete_transient( 'wastealch_update_check' );
	 }
 }
 
 ?>