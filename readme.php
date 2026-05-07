
<?php
 add_filter( 'plugin_row_meta', function( $links, $file ) {
 
	 if ( $file !== 'waste-alchemists-beta/waste-alchemists.php' ) return $links;
 
	 $links[] = sprintf(
		 '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s">%s</a>',
		 add_query_arg( [
			 'tab'       => 'plugin-information',
			 'plugin'    => 'waste-alchemists',
			 'TB_iframe' => 'true',
			 'width'     => 600,
			 'height'    => 550,
		 ], admin_url( 'plugin-install.php' ) ),
		 esc_attr__( 'View details' ),
		 __( 'View details' )
	 );
 
	 return $links;
 
 }, 10, 2 );