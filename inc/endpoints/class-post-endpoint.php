<?php

namespace H_API\Endpoints;

class Post_Endpoint extends Endpoint {

	protected $pattern = 'post/{POST_ID}';
	protected $query = 'post_id=$matches[1]';
	protected $methods = array( 'GET', 'POST', 'DELETE' );
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

	protected function get( $args ) {

	}

	protected function post( $args ) {

		$id = wp_update_post( array(
			'ID' => $this->post->ID,
			'post_title' => $args['title'],
			'post_content' => $args['content'],
		));

		if ( is_wp_error( $id ) ) {
			$this->send_error( $id->get_error_message(), 400 );
		}
	}

	protected function delete() {
		wp_trash_post( $this->post->ID );
	}

	protected function validate_query_vars( $query_vars ) {

		parent::validate_query_vars( $query_vars );

		$this->post = get_post( $query_vars['post_id'] );

	}
}