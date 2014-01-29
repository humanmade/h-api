<?php

namespace H_API;

class Endpoints {

	private static $instance;
	private $endpoints = array();
	private $paths = array();

	/**
	 * Get the instance of the class
	 */
	public static function get_instance() {

		if ( ! self::$instance ) {
			self::$instance = new Endpoints;
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	public function __construct() {
		$this->paths[] = dirname( __FILE__ ) . '/endpoints';

		require_once dirname( __FILE__ ) . '/../lib/hm-rewrite/hm-rewrites.php';
	}

	/**
	 * Require any files necessary for the API
	 */
	public function require_files() {

		require_once dirname( __FILE__ ) . '/endpoints/class-endpoint.php';

		foreach ( $this->paths as $path ) {
			$endpoints = glob( $path . '/*.php' );

			// Sort by strlen because parent classes need to be loaded
			// before children classes
			usort( $endpoints, function( $a, $b ){
				return strlen( $a ) - strlen( $b );
			});

			// Load the endpoints
			foreach( $endpoints as $endpoint ) {
				require_once $endpoint;
			}
		}
	}

	/**
	 * Add a directory of endpoints, the endpoint files will be auto loaded from the dir
	 * 
	 * @param string $dir
	 */
	public function add_endpoints_dir( $dir ) {

		$this->paths[] = $dir;
	}

	/**
	 * Set up actions associated with the API
	 */
	private function setup_actions() {

		add_action( 'plugins_loaded', array( $this, 'require_files' ), 99 );
		add_action( 'init', array( $this, 'register_endpoints' ), 99 );
	}

	/**
	 * Add an endpoint to the API
	 */
	public function add_endpoint( $endpoint_class ) {

		$this->endpoints[] = $endpoint_class;
	}

	/**
	 * Get all of the endpoints
	 * 
	 * @return array
	 */
	public function get_endpoints() {
		return $this->endpoints;
	}

	/**
	 * Register all of the endpoints with HM Rewrite
	 */
	public function register_endpoints() {

		foreach( $this->endpoints as $endpoint ) {
			hm_add_rewrite_rule( array(
				'regex' => '^api/json/' . $endpoint->get_regex() . '$',
				'query' => $endpoint->get_query(),
				'disable_canonical' => true,
				'request_methods' => $endpoint->get_methods(),
				'request_callback' => array( $endpoint, 'base_callback' )
			) );
		}

		// A catch-all 404 endpoint
		hm_add_rewrite_rule( array(
			'regex' => '^api/json/(.+)$',
			'query' => '',
			'disable_canonical' => true,
			'request_methods' => array( 'GET', 'POST', 'DELETE' ),
			'request_callback' => function() {
				$this->send_error( 'Endpoint not implemented.', 404 );	
			}
		) );

	}

	/**
	 * Send an error
	 * 
	 * @param string     $error_message
	 * @param int        $status_code
	 */
	public function send_error( $error_message, $status_code = 400 ) {
		
		http_response_code( $status_code );
		echo $error_message;
		exit;
	}

	/**
	 * Send a response
	 * 
	 * @param mixed      $response_body
	 * @param int        $status_code
	 */
	public function send_response( $response_body, $status_code = 200 ) {

		header('Content-type: application/json');
		http_response_code( $status_code );
		echo json_encode( $response_body );
		exit;
	}

}