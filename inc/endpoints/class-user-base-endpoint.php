<?php

namespace H_API\Endpoints;

class User_Base_Endpoint extends Endpoint {

	protected $pattern = 'user';
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
		),
		'first_name' => array(
			'sanitize_callback' => 'sanitize_text_field',
			'required' => false,
			'methods' => array( 'POST' )
		),
		'last_name' => array(
			'sanitize_callback' => 'sanitize_text_field',
			'required' => false,
			'methods' => array( 'POST' )
		),
	);

	protected function get() {


	}

	protected function post( $args ) {

		$args = wp_parse_args( $args, array(
			'first_name' => '',
			'last_name' => ''
		));
		
		$id = wp_insert_user( array(
			'user_email' => $args['email'],
			'user_pass' => $args['password'],
			'user_login' => $args['email'],
			'first_name' => $args['first_name'],
			'last_name' => $args['last_name']
		) );

		if ( is_wp_error( $id ) ) {
			$this->send_error( $id->get_error_message(), 400 );
		}

		$this->send_response( User_Endpoint::get_user_object( new \WP_User( $id ) ) );
	}
}