<?php
/**
 * REST API integration for the plugin.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Namespace for performance-lab REST API.
 *
 * @var string
 */
const PERFLAB_REST_API_NAMESPACE = 'performance-lab/v1';

/**
 * Route for activating plugin.
 *
 * @var string
 */
const PERFLAB_ACTIVATE_PLUGIN_ROUTE = '/activate-plugin';

/**
 * Route for fetching plugin settings URL.
 *
 * @var string
 */
const PERFLAB_PLUGIN_SETTINGS_URL_ROUTE = '/plugin-settings-url';

/**
 * Registers endpoint for performance-lab REST API.
 *
 * @since n.e.x.t
 * @access private
 */
function perflab_register_endpoint(): void {
	register_rest_route(
		PERFLAB_REST_API_NAMESPACE,
		PERFLAB_ACTIVATE_PLUGIN_ROUTE,
		array(
			'methods'             => 'POST',
			'args'                => array(
				'slug' => array(
					'type'        => 'string',
					'description' => __( 'Plugin slug of plugin that needs to be activated.', 'performance-lab' ),
					'required'    => true,
				),
			),
			'callback'            => 'perflab_handle_activate_plugin',
			'permission_callback' => static function () {
				if ( current_user_can( 'install_plugins' ) ) {
					return true;
				}

				return new WP_Error( 'cannot_install_plugin', __( 'Sorry, you are not allowed to install plugins on this site.', 'performance-lab' ) );
			},
		)
	);

	register_rest_route(
		PERFLAB_REST_API_NAMESPACE,
		PERFLAB_PLUGIN_SETTINGS_URL_ROUTE,
		array(
			'methods'             => 'POST',
			'args'                => array(
				'slug' => array(
					'type'        => 'string',
					'description' => __( 'Plugin slug of plugin whose settings URL is needed.', 'performance-lab' ),
					'required'    => true,
				),
			),
			'callback'            => 'perflab_handle_get_plugin_settings_url',
			'permission_callback' => static function () {
				if ( current_user_can( 'manage_options' ) ) {
					return true;
				}

				return new WP_Error( 'cannot_access_plugin_settings_url', __( 'Sorry, you are not allowed to access plugin settings URL on this site.', 'performance-lab' ) );
			},
		)
	);
}
add_action( 'rest_api_init', 'perflab_register_endpoint' );

/**
 * Handles REST API request to activate plugin.
 *
 * @since n.e.x.t
 * @access private
 *
 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function perflab_handle_activate_plugin( WP_REST_Request $request ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

	// Require to make helper functions available.
	require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/admin/load.php';
	require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/admin/plugins.php';

	$params = $request->get_json_params();

	// Ensure the 'slug' parameter is present.
	if ( ! isset( $params['slug'] ) ) {
		return new WP_Error(
			'missing_parameter',
			__( 'Missing required parameter "slug".', 'performance-lab' ),
			array( 'status' => 400 )
		);
	}

	$plugin_slug = perflab_sanitize_plugin_slug( wp_unslash( $params['slug'] ) );
	if ( null === $plugin_slug ) {
		return new WP_Error(
			'invalid_plugin',
			__( 'Invalid plugin slug provided.', 'performance-lab' ),
			array( 'status' => 400 )
		);
	}

	// Install and activate the plugin and its dependencies.
	$result = perflab_install_and_activate_plugin( $plugin_slug );
	if ( is_wp_error( $result ) ) {
		return new WP_Error(
			'plugin_activation_failed',
			$result->get_error_message(),
			array( 'status' => 500 )
		);
	}

	return new WP_REST_Response(
		array(
			'success' => true,
		)
	);
}

/**
 * Handles REST API request to get plugin settings URL.
 *
 * @since n.e.x.t
 * @access private
 *
 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error Response.
 */
function perflab_handle_get_plugin_settings_url( WP_REST_Request $request ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	// Require to make helper functions available.
	require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/admin/load.php';
	require_once PERFLAB_PLUGIN_DIR_PATH . 'includes/admin/plugins.php';

	$params = $request->get_json_params();

	// Ensure the 'slug' parameter is present.
	if ( ! isset( $params['slug'] ) ) {
		return new WP_Error(
			'missing_parameter',
			__( 'Missing required parameter "slug".', 'performance-lab' ),
			array( 'status' => 400 )
		);
	}

	$plugin_slug = perflab_sanitize_plugin_slug( wp_unslash( $params['slug'] ) );
	if ( null === $plugin_slug ) {
		return new WP_Error(
			'invalid_plugin',
			__( 'Invalid plugin slug provided.', 'performance-lab' ),
			array( 'status' => 400 )
		);
	}

	$plugin_settings_url = perflab_get_plugin_settings_url( $plugin_slug );
	if ( null === $plugin_settings_url ) {
		return new WP_REST_Response(
			array(
				'success'           => true,
				'pluginSettingsURL' => false,
			)
		);
	}

	return new WP_REST_Response(
		array(
			'success'           => true,
			'pluginSettingsURL' => esc_url_raw( $plugin_settings_url ),
		)
	);
}
