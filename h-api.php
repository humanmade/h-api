<?php

/**
 * Plugin Name: H Api
 * Description: The Happy API
 * Author: Human Made Limited
 */

require_once dirname( __FILE__ ) . '/inc/class-endpoints.php';

/**
 * The H_API singleton instance
 */
function H_API() {
	return H_API\Endpoints::get_instance();
}
add_action( 'plugins_loaded', 'H_API' );