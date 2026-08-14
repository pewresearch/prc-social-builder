<?php
declare(strict_types=1);

namespace PRC\Platform\Social_Builder;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class Hootsuite {
	const REST_NAMESPACE = 'prc-social-builder/v1';
	const HOOTSUITE_API_URL = 'https://platform.hootsuite.com/v1';

	public function __construct( $loader = null ) {
		$loader->add_action( 'rest_api_init', $this, 'register_rest_routes' );
		$loader->add_action( 'transition_post_status', $this, 'on_publish', 10, 3 );
	}

	public function register_rest_routes(): void {
		register_rest_route(
			self::REST_NAMESPACE,
			'/profiles',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'rest_get_profiles' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/push',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'rest_push_package' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'package_id' => array(
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function rest_get_profiles( WP_REST_Request $unused_request ): WP_REST_Response|WP_Error {
		$profiles = $this->get_social_profiles();
		if ( is_wp_error( $profiles ) ) {
			return $profiles;
		}
		return new WP_REST_Response( array( 'success' => true, 'profiles' => $profiles ), 200 );
	}

	public function rest_push_package( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$package_id = $request->get_param( 'package_id' );
		$result = $this->create_drafts_from_package( $package_id );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new WP_REST_Response( array( 'success' => true, 'drafts' => $result ), 200 );
	}

	public function on_publish( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( Content_Type::$post_type !== $post->post_type ) {
			return;
		}
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		$result = $this->create_drafts_from_package( $post->ID );
		if ( is_wp_error( $result ) ) {
			$errors = get_post_meta( $post->ID, '_hootsuite_errors', true );
			$errors = is_array( $errors ) ? $errors : array();
			$errors[] = $result->get_error_message();
			update_post_meta( $post->ID, '_hootsuite_errors', $errors );
		}
	}

	public function create_drafts_from_package( int $package_id ): array|WP_Error {
		$post = get_post( $package_id );
		if ( ! $post || Content_Type::$post_type !== $post->post_type ) {
			return new WP_Error( 'invalid_package', __( 'Invalid social package.', 'prc-social-builder' ) );
		}

		$blocks = parse_blocks( $post->post_content );
		$draft_ids = array();
		$errors = array();

		foreach ( $blocks as $block ) {
			if ( 'prc-social/container' === $block['blockName'] ) {
				$platform = $block['attrs']['platform'] ?? 'twitter';
				$inner_blocks = $block['innerBlocks'] ?? array();
				foreach ( $inner_blocks as $inner ) {
					if ( 'prc-social/message' !== $inner['blockName'] ) {
						continue;
					}
					$content = $inner['attrs']['content'] ?? '';
					$media_url = $inner['attrs']['mediaUrl'] ?? '';
					if ( empty( $content ) && empty( $media_url ) ) {
						continue;
					}
					$result = $this->create_draft( array(
						'platform'  => $platform,
						'text'      => $content,
						'media_url' => $media_url,
					) );
					if ( is_wp_error( $result ) ) {
						$errors[] = $result->get_error_message();
					} elseif ( ! empty( $result['data']['id'] ) ) {
						$draft_ids[] = $result['data']['id'];
					}
				}
			} elseif ( 'prc-social/story' === $block['blockName'] ) {
				$platform = $block['attrs']['platform'] ?? 'instagram';
				$caption = $block['attrs']['caption'] ?? '';
				$media_url = $block['attrs']['mediaUrl'] ?? '';
				if ( empty( $caption ) && empty( $media_url ) ) {
					continue;
				}
				$result = $this->create_draft( array(
					'platform'  => $platform,
					'text'      => $caption,
					'media_url' => $media_url,
				) );
				if ( is_wp_error( $result ) ) {
					$errors[] = $result->get_error_message();
				} elseif ( ! empty( $result['data']['id'] ) ) {
					$draft_ids[] = $result['data']['id'];
				}
			}
		}

		if ( ! empty( $draft_ids ) ) {
			update_post_meta( $package_id, '_hootsuite_draft_ids', $draft_ids );
		}
		if ( ! empty( $errors ) ) {
			update_post_meta( $package_id, '_hootsuite_errors', $errors );
		}

		return $draft_ids;
	}

	public function create_draft( array $message_data ): array|WP_Error {
		$profiles = $this->get_social_profiles();
		if ( is_wp_error( $profiles ) ) {
			return $profiles;
		}

		$platform = $message_data['platform'] ?? '';
		$profile_id = $this->resolve_profile_id( $platform, $profiles );

		if ( ! $profile_id ) {
			return new WP_Error(
				'profile_not_found',
				sprintf( __( 'No Hootsuite profile found for platform: %s', 'prc-social-builder' ), $platform )
			);
		}

		$body = array(
			'text'             => $message_data['text'] ?? '',
			'socialProfileIds' => array( $profile_id ),
		);

		if ( ! empty( $message_data['media_url'] ) ) {
			$body['mediaUrls'] = array( array( 'url' => $message_data['media_url'] ) );
		}

		return $this->make_api_request( '/messages', 'POST', $body );
	}

	/**
	 * Map a Social Builder platform key to Hootsuite social profile type strings.
	 *
	 * @return array<int, string>
	 */
	private function get_profile_type_aliases( string $platform ): array {
		$aliases = array(
			'linkedin' => array( 'linkedincompany', 'linkedin' ),
			'facebook' => array( 'facebookpage', 'facebook' ),
		);

		$platform = strtolower( $platform );

		return $aliases[ $platform ] ?? array( $platform );
	}

	/**
	 * Resolve a Hootsuite social profile ID for a platform key.
	 *
	 * @param array<int, array<string, mixed>> $profiles
	 */
	private function resolve_profile_id( string $platform, array $profiles ): ?string {
		$aliases = $this->get_profile_type_aliases( $platform );

		foreach ( $aliases as $alias ) {
			foreach ( $profiles as $profile ) {
				if ( strtolower( (string) ( $profile['type'] ?? '' ) ) === $alias ) {
					return isset( $profile['id'] ) ? (string) $profile['id'] : null;
				}
			}
		}

		return null;
	}

	protected function get_api_key(): string|WP_Error {
		if ( ! defined( 'PRC_HOOTSUITE_API_KEY' ) || empty( PRC_HOOTSUITE_API_KEY ) ) {
			return new WP_Error( 'missing_api_key', __( 'Hootsuite API key is not configured.', 'prc-social-builder' ) );
		}
		return PRC_HOOTSUITE_API_KEY;
	}

	protected function make_api_request( string $endpoint, string $method = 'GET', array $body = array() ): array|WP_Error {
		$api_key = $this->get_api_key();
		if ( is_wp_error( $api_key ) ) {
			return $api_key;
		}

		$url = self::HOOTSUITE_API_URL . $endpoint;
		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 30,
		);

		if ( ! empty( $body ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( $status_code >= 400 ) {
			$error_message = $data['errors'][0]['message'] ?? __( 'Hootsuite API error.', 'prc-social-builder' );
			return new WP_Error( 'hootsuite_api_error', $error_message, array( 'status' => $status_code ) );
		}

		return $data ?? array();
	}

	public function get_social_profiles(): array|WP_Error {
		$cache_key = 'prc_hootsuite_profiles';
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$response = $this->make_api_request( '/socialProfiles' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$profiles = $response['data'] ?? array();
		set_transient( $cache_key, $profiles, 5 * MINUTE_IN_SECONDS );
		return $profiles;
	}
}
