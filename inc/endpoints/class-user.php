<?php

namespace H_API\Endpoints;

class User_Endpoint extends Endpoint {

	protected $pattern = 'user/{USER_ID}';
	protected $query = 'user_id=$matches[1]';
	protected $methods = array( 'GET', 'POST' );
	protected $authenticated = true;
	protected $public = true;
	protected $arguments = array(
		'password' => array(
			'sanitize_callback' => '',
			'required' => true,
			'methods' => array( 'POST' )
		),
		'email' => array(
			'sanitize_callback' => 'sanitize_email',
			'required' => true,
			'methods' => array( 'POST' )
		)
	);

	protected function get() {
		$this->send_response( $this->get_user_object( $this->user ) );
	}

	protected function post() {

	}

	/**
	 * Get the object that a GET request will return
	 * 
	 * @return array
	 */
	public static function get_user_object( $user ) {

		return array(
			'id' => $user->ID,
			'email' => $user->user_email
		);
	}

	protected function validate_query_vars( $query_vars ) {

		parent::validate_query_vars( $query_vars );

		$this->user = new \WP_User( $query_vars['user_id'] );

	}
}