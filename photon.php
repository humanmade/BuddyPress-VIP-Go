<?php
/**
 * Integrates Photon into BuddyPress.
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

add_action( 'bp_init', function() {
	//Activity and PM content? Status update strings?
	//add_filter( 'ye_olde_output_strings_with_url', array( 'Jetpack_Photon', 'filter_the_content' ), 999999 );
} );
