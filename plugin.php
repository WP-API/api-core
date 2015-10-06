<?php
/**
 * Plugin Name: WP REST API
 * Description: JSON-based REST API for WordPress, developed as part of GSoC 2013.
 * Author: WP REST API Team
 * Author URI: http://wp-api.org
 * Version: 2.0-beta4
 * Plugin URI: https://github.com/WP-API/WP-API
 * License: GPL2+
 */

/**
 * Version number for our API.
 *
 * @var string
 */
define( 'REST_API_VERSION', '2.0-beta4' );

/** v1 Compatibility */
include_once( dirname( __FILE__ ) . '/compatibility-v1.php' );

/** Compatibility shims for PHP functions */
include_once( dirname( __FILE__ ) . '/lib/compat.php' );

/** Main API functions */
include_once( dirname( __FILE__ ) . '/lib/functions.php' );

/** WP_REST_Server class */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-server.php' );

/** WP_HTTP_ResponseInterface interface */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-http-responseinterface.php' );

/** WP_HTTP_Response class */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-http-response.php' );

/** WP_REST_Response class */
include_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-response.php' );

/** WP_REST_Request class */
require_once( dirname( __FILE__ ) . '/lib/infrastructure/class-wp-rest-request.php' );

/** REST functions */
include_once( dirname( __FILE__ ) . '/lib/rest-functions.php' );

/** REST filters */
include_once( dirname( __FILE__ ) . '/lib/filters.php' );

/** WP_REST_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-controller.php';

/** WP_REST_Posts_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-posts-controller.php';

/** WP_REST_Attachments_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-attachments-controller.php';

/** WP_REST_Post_Types_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-post-types-controller.php';

/** WP_REST_Post_Statuses_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-post-statuses-controller.php';

/** WP_REST_Revisions_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-revisions-controller.php';

/** WP_REST_Taxonomies_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-taxonomies-controller.php';

/** WP_REST_Terms_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-terms-controller.php';

/** WP_REST_Users_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-users-controller.php';

/** WP_REST_Comments_Controller class */
require_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-comments-controller.php';

/** WP_REST_Meta_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-meta-controller.php';

/** WP_REST_Meta_Posts_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-meta-posts-controller.php';

/** WP_REST_Posts_Terms_Controller class */
include_once dirname( __FILE__ ) . '/lib/endpoints/class-wp-rest-posts-terms-controller.php';


/**
 * Adds extra post type registration arguments.
 *
 * These attributes will eventually be committed to core.
 *
 * @since 4.4.0
 *
 * @param array  $args      Array of arguments for registering a post type.
 * @param string $post_type Post type key.
 */
function _add_extra_api_post_type_arguments( $args, $post_type ) {
	if ( 'post' === $post_type ) {
		$args['show_in_rest'] = true;
		$args['rest_base'] = 'posts';
		$args['rest_controller_class'] = 'WP_REST_Posts_Controller';
	}

	if ( 'page' === $post_type ) {
		$args['show_in_rest'] = true;
		$args['rest_base'] = 'pages';
		$args['rest_controller_class'] = 'WP_REST_Posts_Controller';
	}

	if ( 'attachment' === $post_type ) {
		$args['show_in_rest'] = true;
		$args['rest_base'] = 'media';
		$args['rest_controller_class'] = 'WP_REST_Attachments_Controller';
	}

	return $args;
}
add_filter( 'register_post_type_args', '_add_extra_api_post_type_arguments', 10, 2 );

/**
 * Adds extra taxonomy registration arguments.
 *
 * These attributes will eventually be committed to core.
 *
 * @since 4.4.0
 *
 * @global array $wp_taxonomies Registered taxonomies.
 */
function _add_extra_api_taxonomy_arguments() {
	global $wp_taxonomies;

	if ( isset( $wp_taxonomies['category'] ) ) {
		$wp_taxonomies['category']->show_in_rest = true;
		$wp_taxonomies['category']->rest_base = 'category';
		$wp_taxonomies['category']->rest_controller_class = 'WP_REST_Terms_Controller';
	}

	if ( isset( $wp_taxonomies['post_tag'] ) ) {
		$wp_taxonomies['post_tag']->show_in_rest = true;
		$wp_taxonomies['post_tag']->rest_base = 'tag';
		$wp_taxonomies['post_tag']->rest_controller_class = 'WP_REST_Terms_Controller';
	}
}
add_action( 'init', '_add_extra_api_taxonomy_arguments', 11 );

/**
 * Registers default REST API routes.
 *
 * @since 4.4.0
 */
function create_initial_rest_routes() {

	foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
		$class = ! empty( $post_type->rest_controller_class ) ? $post_type->rest_controller_class : 'WP_REST_Posts_Controller';

		if ( ! class_exists( $class ) ) {
			continue;
		}
		$controller = new $class( $post_type->name );
		if ( ! is_subclass_of( $controller, 'WP_REST_Controller' ) ) {
			continue;
		}

		$controller->register_routes();

		if ( post_type_supports( $post_type->name, 'custom-fields' ) ) {
			$meta_controller = new WP_REST_Meta_Posts_Controller( $post_type->name );
			$meta_controller->register_routes();
		}
		if ( post_type_supports( $post_type->name, 'revisions' ) ) {
			$revisions_controller = new WP_REST_Revisions_Controller( $post_type->name );
			$revisions_controller->register_routes();
		}

		foreach ( get_object_taxonomies( $post_type->name, 'objects' ) as $taxonomy ) {

			if ( empty( $taxonomy->show_in_rest ) ) {
				continue;
			}

			$posts_terms_controller = new WP_REST_Posts_Terms_Controller( $post_type->name, $taxonomy->name );
			$posts_terms_controller->register_routes();
		}
	}

	// Post types.
	$controller = new WP_REST_Post_Types_Controller;
	$controller->register_routes();

	// Post statuses.
	$controller = new WP_REST_Post_Statuses_Controller;
	$controller->register_routes();

	// Taxonomies.
	$controller = new WP_REST_Taxonomies_Controller;
	$controller->register_routes();

	// Terms.
	foreach ( get_taxonomies( array( 'show_in_rest' => true ), 'object' ) as $taxonomy ) {
		$class = ! empty( $taxonomy->rest_controller_class ) ? $taxonomy->rest_controller_class : 'WP_REST_Terms_Controller';

		if ( ! class_exists( $class ) ) {
			continue;
		}
		$controller = new $class( $taxonomy->name );
		if ( ! is_subclass_of( $controller, 'WP_REST_Controller' ) ) {
			continue;
		}

		$controller->register_routes();
	}

	// Users.
	$controller = new WP_REST_Users_Controller;
	$controller->register_routes();

	// Comments.
	$controller = new WP_REST_Comments_Controller;
	$controller->register_routes();
}
add_action( 'rest_api_init', 'create_initial_rest_routes', 0 );

/**
 * Determines if the rewrite rules should be flushed.
 *
 * @since 4.4.0
 */
function rest_api_maybe_flush_rewrites() {
	$version = get_option( 'rest_api_plugin_version', null );

	if ( empty( $version ) || REST_API_VERSION !== $version ) {
		flush_rewrite_rules();
		update_option( 'rest_api_plugin_version', REST_API_VERSION );
	}
}
add_action( 'init', 'rest_api_maybe_flush_rewrites', 999 );

/**
 * Registers routes and flush the rewrite rules on activation.
 *
 * @since 4.4.0
 *
 * @param bool $network_wide ?
 */
function rest_api_activation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {
		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {
			switch_to_blog( $mu_blog['blog_id'] );

			rest_api_register_rewrites();
			update_option( 'rest_api_plugin_version', null );
		}

		restore_current_blog();
	} else {
		rest_api_register_rewrites();
		update_option( 'rest_api_plugin_version', null );
	}
}
register_activation_hook( __FILE__, 'rest_api_activation' );

/**
 * Flushes the rewrite rules on deactivation.
 *
 * @since 4.4.0
 *
 * @param bool $network_wide ?
 */
function rest_api_deactivation( $network_wide ) {
	if ( function_exists( 'is_multisite' ) && is_multisite() && $network_wide ) {

		$mu_blogs = wp_get_sites();

		foreach ( $mu_blogs as $mu_blog ) {
			switch_to_blog( $mu_blog['blog_id'] );
			delete_option( 'rest_api_plugin_version' );
		}

		restore_current_blog();
	} else {
		delete_option( 'rest_api_plugin_version' );
	}
}
register_deactivation_hook( __FILE__, 'rest_api_deactivation' );

/**
 * Retrieves the avatar urls in various sizes based on a given email address.
 *
 * @since 4.4.0
 *
 * @see get_avatar_url()
 *
 * @param string $email Email address.
 * @return array $urls Gravatar url for each size.
 */
function rest_get_avatar_urls( $email ) {
	$avatar_sizes = rest_get_avatar_sizes();

	$urls = array();
	foreach ( $avatar_sizes as $size ) {
		$urls[ $size ] = get_avatar_url( $email, array( 'size' => $size ) );
	}

	return $urls;
}

/**
 * Retrieves the pixel sizes for avatars.
 *
 * @since 4.4.0
 *
 * @return array List of pixel sizes for avatars. Default `[ 24, 48, 96 ]`.
 */
function rest_get_avatar_sizes() {
	/**
	 * Filter the REST avatar sizes.
	 *
	 * Use this filter to adjust the array of sizes returned by the
	 * `rest_get_avatar_sizes` function.
	 *
	 * @since 4.4.0
	 *
	 * @param array $sizes An array of int values that are the pixel sizes for avatars.
	 *                     Default `[ 24, 48, 96 ]`.
	 */
	return apply_filters( 'rest_avatar_sizes', array( 24, 48, 96 ) );
}
