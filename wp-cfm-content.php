<?php
/*
Plugin Name: WP-CFM Content
Description: Content support for WordPress Configuration Management
Version: 0.1.0
Author: Pascal Martineau <pascal@lewebsimple.ca>
License: GPLv3
*/

defined( 'ABSPATH' ) or exit;

class WPCFM_Content {

	function __construct() {
		add_filter( 'wpcfm_configuration_items', array( $this, 'configuration_items' ) );
		// Make sure we run after WPCFM_Taxonomy with priority 20
		add_filter( 'wpcfm_pull_callback', array( $this, 'pull_callback' ), 20, 2 );
	}

	/**
	 * Register the content types in WP-CFM
	 */
	function configuration_items( $items ) {
		$post_types = get_post_types( array(), 'objects' );
		foreach ( $post_types as $post_type ) {
			/**
			 * Determine if post type can be managed by WP-CFM Content
			 */
			$enabled = apply_filters( 'wpcfm_content/enabled', $post_type->public, $post_type );
			$enabled = apply_filters( 'wpcfm_content/enabled/post_type=' . $post_type->name, $enabled, $post_type );
			if ( ! $enabled ) {
				continue;
			}


			$items[ 'content/' . $post_type->name ] = array(
				'value' => json_encode( $this->get_value( $post_type->name ) ),
				'label' => $post_type->label,
				'group' => 'Content',
			);
		}

		return $items;
	}

	/**
	 * Helper: Get
	 *
	 * @param string $post_type The post type name
	 */
	function get_value( string $post_type ) {
		global $wpdb;
		$value = array();

		// Posts
		$posts          = $wpdb->get_results( "SELECT * FROM $wpdb->posts WHERE post_type='$post_type' ORDER BY ID ASC;", ARRAY_A );
		$value['posts'] = array_combine( array_column( $posts, 'ID' ), $posts );

		foreach ( $value['posts'] as $post_id => $post ) {
			// Postmeta
			$value['postmeta'][ $post_id ] = $wpdb->get_results( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id ORDER BY meta_key ASC;", ARRAY_A );

			// Terms
			foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
				if ( is_wp_error( $terms = get_the_terms( $post_id, $taxonomy ) ?: array() ) ) {
					continue;
				}
				$value['terms'][ $post_id ][ $taxonomy ] = array_combine( array_column( $terms, 'term_id' ), array_column( $terms, 'slug' ) );
			}
		}

		return $value;
	}

	/**
	 * Tell WP-CFM to use import_content() for content items
	 */
	function pull_callback( $callback, $callback_params ) {
		if ( strpos( $callback_params['name'], 'content/' ) === 0 ) {
			return array( $this, 'import_content' );
		}

		return $callback;
	}

	/**
	 * Import content from bundle
	 *
	 * @param $params
	 *
	 * @return false
	 */
	function import_content( $params ) {
		global $wpdb;

		// Validate post_type
		$post_type = str_replace( 'content/', '', $params['name'] );
		if ( ! post_type_exists( $post_type ) ) {
			return false;
		}

		$db_value  = json_decode( $params['old_value'], true );
		$new_value = json_decode( $params['new_value'], true );

		foreach ( $new_value['posts'] as $post_id => $post ) {
			// Update post data
			if ( $db_value['posts'][ $post_id ] != $post ) {
				// TODO: Handle creating missing posts
				// Check post_type
				if ( $db_value['posts'][ $post_id ]['post_type'] !== $post_type ) {
					error_log( sprintf( "[WP-CFM Content] Post type mismatch for post ID %d (expected %s, got %s)", $post_id, $post_type, $db_value['posts'][ $post_id ]['post_type'] ) );
					continue;
				}
				// Update the actual post data
				if ( is_wp_error( $error = wp_update_post( $post, true ) ) ) {
					error_log( "[WP-CFM Content] " . $error->get_error_message() );
					continue;
				}
			}

			// Update postmeta (delete everything and re-insert)
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $post_id ) );
			if ( ! empty( $new_value['postmeta'][ $post_id ] ) ) {
				$values = array_map( function ( $meta ) use ( $post_id ) {
					return "($post_id,'${meta['meta_key']}','${meta['meta_value']}')";
				}, $new_value['postmeta'][ $post_id ] );
				$wpdb->query( sprintf( "INSERT INTO $wpdb->postmeta (`post_id`, `meta_key`, `meta_value`) VALUES %s;", implode( ',', $values ) ) );
			}

			// Update terms
			foreach ( get_object_taxonomies( $post_type ) as $taxonomy ) {
				if ( $db_value['terms'][ $post_id ][ $taxonomy ] == $new_value['terms'][ $post_id ][ $taxonomy ] ) {
					continue;
				}
				// TODO: Check terms' slug to make sure
				wp_set_post_terms( $post_id, array_keys( $new_value['terms'][ $post_id ][ $taxonomy ] ), $taxonomy );
			}
		}

		return true;
	}

}

new WPCFM_Content();
