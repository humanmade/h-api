<?php
/**
 * Base API Endpoint class
 */
namespace H_API\Endpoints;

abstract class Endpoint {

	protected $pattern = '';
	protected $query = '';
	protected $arguments = array();
	protected $methods = array( 'GET' );
	protected $authenticated = true;
	protected $premium = false;
	protected $public = true;
	protected $capability = 'edit_posts';
	private $is_cookie_request;

	/**
	 * Get the pattern associated with the endpoint
	 */
	public function get_pattern() {
		return $this->pattern;
	}

	/**
	 * Get the array to transform tokens into regex
	 * 
	 * @return array
	 */
	public function get_regex_tokens() {
		return array(
			'{POST_ID}' => '([\d]+)',
			'{USER_ID}' => '([\d]+)',
			'{COMMENT_ID}' => '([\d]+)',
			'{TERM_ID}' => '([\d]+)',
		);
	}

	/**
	 * Get the regex associated with the endpoint
	 */
	public function get_regex() {

		return str_replace( array_keys( $this->get_regex_tokens() ), array_values( $this->get_regex_tokens() ), $this->get_pattern() );
	}

	/**
	 * Get the query associated with the endpoint
	 */
	public function get_query() {
		return $this->query;
	}

	/**
	 * Get the arguments associated with the endpoint
	 */
	public function get_arguments() {
		return $this->arguments;
	}

	/**
	 * Get the methods associated with the endpoint
	 */
	public function get_methods() {
		return $this->methods;
	}

	/**
	 * Whether or not this endpoint requires authentication
	 * 
	 * @return bool
	 */
	public function is_authenticated() {
		return $this->authenticated;
	}

	/**
	 * Whether or not this endpoint is public
	 * 
	 * @return bool
	 */
	public function is_public() {
		return $this->public;
	}

	public function is_cookie_request() {
		return $this->is_cookie_request;
	}

	/**
	 * A base API request callback
	 */
	public function base_callback( $wp ) {

		// If it's an authenticated request, do the authentication
		if ( $this->is_authenticated() )
			$this->authenticate();

		// Ensure URL arguments are properly decoded
		$wp->query_vars = array_map( 'urldecode', $wp->query_vars );

		// Perform whatever setup needed for the query vars
		$this->validate_query_vars( $wp->query_vars );

		// Handle different HTTP request methods
		$args = array();
		switch ( $_SERVER['REQUEST_METHOD'] ) {
			case 'POST':
				$args = $this->validate_arguments( stripslashes_deep( $_POST ) );
				break;

			case 'GET':
				$args = $this->validate_arguments( stripslashes_deep( $_GET ) );
				break;

			case 'DELETE':
				$args = array();
				break;

		}

		$method = strtolower( $_SERVER['REQUEST_METHOD'] );

		if ( is_callable( array( $this, $method ) ) ) {
			$this->$method( $args );

			// default to 204 no content if nothing has been sent
			$this->send_error( '', 204 );
		} else {
			$this->send_error( '', 501 );
		}

		if ( isset( $_POST['redirect_to'] ) ) {
			wp_safe_redirect( esc_url_raw( $_POST['redirect_to'] ), 303 );
			exit;
		}

		exit;
	}

	/**
	 * Send an error
	 * 
	 * @param string     $error_message
	 * @param int        $status_code
	 */
	protected function send_error( $error_message, $status_code = 400 ) {
		H_API()->send_error( $error_message, $status_code );
	}

	/**
	 * Send a response
	 * 
	 * @param mixed      $response_body
	 * @param int        $status_code
	 */
	protected function send_response( $response_body, $status_code = 200 ) {
		H_API()->send_response( $response_body, $status_code );
	}

	/**
	 * Validate the query variables for a request
	 *
	 * @param array      $query_vars
	 */
	protected function validate_query_vars( $query_vars ) {}

	/**
	 * Validate the arguments for a request
	 * 
	 * @param array      $dirty_args     Arguments sent by user (by GET, POST, etc.)
	 * @return array     $safe_args
	 */
	protected function validate_arguments( $dirty_args ) {

		$safe_args = array();
		foreach( $this->get_arguments() as $key => $details ) {

			if ( ! in_array( $_SERVER['REQUEST_METHOD'], $details['methods'] ) )
				continue;
			
			if ( ! empty( $details['required'] ) && ! isset( $dirty_args[$key] ) )
				$this->send_error( "'{$key}' is a required argument.", 400 );

			if ( ! isset( $dirty_args[$key] ) ) {
				if ( isset( $details['default'] ) ) 
					$safe_args[$key] = $details['default'];
				continue;
			}

			if ( ! empty( $details['options'] ) ) {

				if ( ! in_array( $dirty_args[$key], $details['options'] ) ) {
					$this->send_error( "'{$key}' must be one of " . implode( ', ', $details['options'] ), 400 );
				}
			}

			if ( isset( $details['sanitize_callback'] ) ) {

				// Some endpoints permit no sanitization
				if ( false === $details['sanitize_callback'] )
					$sanitize = false;
				else if ( is_callable( $details['sanitize_callback'] ) )
					$sanitize = $details['sanitize_callback'];
				else
					$sanitize = 'sanitize_text_field';

			} else {

				$sanitize = 'sanitize_text_field';

			}

			if ( empty( $details['options'] ) && ! empty( $details['multiple'] ) ) {

				if ( is_string( $dirty_args[$key] ) && false !== strpos( $dirty_args[$key], ',' ) )
					$dirty_values = explode( ',', $dirty_args[$key] );
				elseif ( is_array( $dirty_args[$key] ) )
					$dirty_values = $dirty_args[$key];
				else
					$dirty_values = array( $dirty_args[$key] );

				$safe_args[$key] = array();

				foreach( $dirty_values as $dirty_value ) {

					if ( $sanitize )
						$safe_args[$key][] = $sanitize( $dirty_value );
					else
						$safe_args[$key][] = $dirty_value;

				}

			} else {

				if ( $sanitize ) {
					// special case, as the "sanitizing" function wp_filter_post_kses return slashed data
					if ( $sanitize === 'wp_filter_post_kses' ) {
						$safe_args[$key] = stripslashes( $sanitize( addslashes( $dirty_args[$key] ) ) );
					} else {
						$safe_args[$key] = $sanitize( $dirty_args[$key] );
					}
				} else {
					$safe_args[$key] = $dirty_args[$key];
				}

			}

		}
		return $safe_args;
	}

	protected function authenticate() {

		if ( is_user_logged_in() ) {

			$this->is_cookie_request = true;

			// check they have permission
			if ( $this->capability && ! current_user_can( $this->capability ) ) {
				$this->send_error( '', 401 );
			}

			// check nonces for cookie bases authentication
			if ( empty( $_REQUEST['_nonce'] ) || ! wp_verify_nonce( $_REQUEST['_nonce'], 'h-api' ) ) {
				$this->send_error( '', 401 );
			}

			return;
		}

		// if the HTTP auth user and password is suplied, they are trying to login with credentials
		if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) ) {

			if ( ! empty( $_SERVER['PHP_AUTH_PW'] ) ) {
				$user = wp_authenticate( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] );
			} else {
				$user = $this->get_user_by_api_key( sanitize_text_field( $_SERVER['PHP_AUTH_USER'] ) );
			}

			if ( ! is_wp_error( $user ) ) {

				wp_set_current_user( $user->ID );
				
				if ( ! current_user_can( $this->capability ) )
					$this->send_error( '', 401 );
				
				return;
			}
		}

		$auth_realm = ( defined( 'HAPI_AUTH_REALM') ) ? HAPI_AUTH_REALM : 'H-API';
		header( sprintf( 'WWW-Authenticate: Basic realm="%s"', $auth_realm ) );
		$this->send_error( '', 401 );
	}

	/**
	 * Get a user by their API key
	 */
	protected function get_user_by_api_key( $api_key ) {

		global $wpdb;

		$user_id = $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = %s", 'api_key_' . $api_key ) );

		if ( ! $user_id ) {
			return new \WP_Error( 'not-found' );
		}

		return new \WP_User( $user_id );
	}

}