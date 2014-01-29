<?php

namespace H_API\Endpoints;

class Post_Base_Endpoint extends Endpoint {

	protected $pattern = 'post';
	protected $methods = array( 'GET', 'POST' );
	protected $authenticated = true;
	protected $public = true;
	protected $arguments = array(
		'title' => array(
			'sanitize_callback' => 'sanitize_text_field',
			'required' => false,
			'methods' => array( 'POST' )
		),
		'content' => array(
			'sanitize_callback' => 'wp_filter_kses_post',
			'required' => false,
			'methods' => array( 'POST' )
		)
	);

	protected function get() {

	}

	protected function post( $args ) {

		$args = wp_parse_args( $args, array(
			'title' => '',
			'content' => ''
		));
		
		$id = wp_insert_post( array(
			'post_title' => $args['title'],
			'post_content' => $args['content'],
		));

		if ( is_wp_error( $id ) ) {
			$this->send_error( $id->get_error_message(), 400 );
		}

		$this->post = get_post( $id );
	}

	protected function delete() {
		wp_trash_post( $this->post->ID );
	}
}