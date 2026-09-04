<?php
/**
 * Add Social Package ability.
 *
 * Creates a social-package post from a source post, optionally prefilled with AI copy.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and executes prc-social-builder/add-social-package.
 */
class Add_Social_Package_Ability {

	/**
	 * Plugin file used to validate activation on the target site.
	 *
	 * @var string
	 */
	private const PLUGIN_FILE = 'prc-social-builder/prc-social-builder.php';

	/**
	 * Ability name.
	 *
	 * @var string
	 */
	public static $ability_name = 'prc-social-builder/add-social-package';

	/**
	 * Blocks allowed to use this ability.
	 *
	 * @var array<int, string>
	 */
	public static $allowed_blocks = array();

	/**
	 * Register the ability with the Abilities API.
	 *
	 * @hook wp_abilities_api_init
	 */
	public function register_ability(): void {
		wp_register_ability(
			self::$ability_name,
			array(
				'label'               => __( 'Add a Social Package', 'prc-social-builder' ),
				'description'         => __( 'Adds a social package from a post id, often with a preset template, though blank or custom packages are supported', 'prc-social-builder' ),
				'category'            => 'communication',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'isDefaultList'    => array(
							'type'        => 'boolean',
							'description' => 'Create the default social package set? (Facebook, LinkedIn, Twitter, Bluesky)',
						),
						'blankTemplate'    => array(
							'type'        => 'boolean',
							'description' => 'Skip generation of AI social copy template',
						),
						'associatedPostId' => array(
							'type'        => 'number',
							'description' => 'The associated post id for the package',
						),
						'title'            => array(
							'type'        => 'string',
							'description' => 'Social package title',
						),
						'skipCopyEdits'    => array(
							'type'        => 'boolean',
							'description' => 'Skip AI editorial skills?',
						),
						'content'          => array(
							'type'        => 'array',
							'description' => 'List of content to include',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'contentType'  => array(
										'type'        => 'string',
										'description' => 'The type of content, must be either "wp-post" or "preformatted"',
									),
									'postId'       => array(
										'type'        => 'number',
										'description' => 'The id of the post to generate social copy for.',
									),
									'includeReportChildren' => array(
										'type'        => 'boolean',
										'description' => 'Add children',
									),
									'unselectedReportChildren' => array(
										'type'        => 'string',
										'description' => 'List of ids to skip',
									),
									'preformatted' => array(
										'type'        => 'string',
										'description' => 'Preformatted text to generate the social copy from',
									),
								),
							),
						),
						'copyList'         => array(
							'type'        => 'array',
							'description' => 'List of platforms to create social copy for',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'platform' => array(
										'type'        => 'string',
										'description' => 'One of the supported social platforms: twitter, facebook, threads, bluesky, linkedin',
									),
									'additionalInstructions' => array(
										'type'        => 'string',
										'description' => 'Optional additional instructions from the editor to guide generation.',
									),
									'tone'     => array(
										'type'        => 'string',
										'description' => 'Optional tone guidance for the copy.',
									),
								),
							),
						),
						'site_id'          => \PRC\Platform\AI\Utils\site_id_input_schema_property(),
					),
					'required'             => array( 'isDefaultList', 'title', 'content' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'newPostId' => array(
							'type'        => 'number',
							'description' => 'The ID of the new post',
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					return $this->with_site(
						$input,
						function () use ( $input ) {
							return $this->add_social_package( $input );
						}
					);
				},
				'permission_callback' => function ( $input = null ) {
					return $this->with_site(
						$input,
						function () {
							return current_user_can( 'edit_posts' );
						}
					);
				},
				'meta'                => array(
					'annotations'    => array(
						'instructions' => 'Adds a social package based on a passed postId. Optionally pass site_id to run against a specific multisite blog.',
						'destructive'  => false,
						'idempotent'   => false,
					),
					'show_in_rest'   => true,
					'allowed_blocks' => self::$allowed_blocks,
					'mcp'            => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}


	/**
	 * Build the generate-social-copy ability input.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, mixed>|WP_Error
	 */
	private function build_package_request( array $input ) {
		$is_default      = isset( $input['isDefaultList'] ) ? (bool) $input['isDefaultList'] : true;
		$copy_list       = isset( $input['copyList'] ) ? (array) $input['copyList'] : array();
		$content_list    = isset( $input['content'] ) ? (array) $input['content'] : array();
		$skip_copy_edits = isset( $input['skipCopyEdits'] ) ? (bool) $input['skipCopyEdits'] : false;
		$site_id         = \PRC\Platform\AI\Utils\resolve_site_id( $input );

		if ( empty( $content_list ) ) {
			return new WP_Error( 'missing_content', __( 'No content provided.', 'prc-social-builder' ) );
		}

		$is_default_package = ( $is_default || empty( $copy_list ) );

		$request = array(
			'isDefaultList' => $is_default_package,
			'content'       => $content_list,
			'skipCopyEdits' => $skip_copy_edits,
			'site_id'       => $site_id,
		);

		if ( ! $is_default_package ) {
			$request['copyList'] = $copy_list;
		}

		return $request;
	}

	/**
	 * Create a social-package post from ability input.
	 *
	 * @param array<string, mixed> $input Input parameters.
	 * @return array<string, mixed>|WP_Error
	 */
	public function add_social_package( $input ) {
		$generate_social_copy = wp_get_ability( 'prc-social-builder/generate-social-copy' );
		$use_blank_template   = isset( $input['blankTemplate'] ) ? (bool) $input['blankTemplate'] : false;
		$associated_post_id   = ( isset( $input['associatedPostId'] ) ) ? (int) $input['associatedPostId'] : 0;
		$package_title        = isset( $input['title'] ) ? sanitize_text_field( (string) $input['title'] ) : '';

		if ( '' === trim( $package_title ) ) {
			return new WP_Error( 'missing_title', __( 'Please provide a package title.', 'prc-social-builder' ) );
		}

		if ( ! $use_blank_template ) {
			if ( ! $generate_social_copy ) {
				return new WP_Error( 'ai_generation_unavailable', __( 'AI social copy generation is not available.', 'prc-social-builder' ) );
			}
			$request = $this->build_package_request( $input );
			if ( is_wp_error( $request ) ) {
				return $request;
			}
			$social_copy = $generate_social_copy->execute( $request );
			if ( is_wp_error( $social_copy ) ) {
				return $social_copy;
			}
		}

		$posted = $this->post_social_package( $social_copy ?? array(), $package_title, $associated_post_id );
		if ( is_wp_error( $posted ) ) {
			return $posted;
		}
		if ( $associated_post_id ) {
			add_post_meta( $posted, '_prc_associated_posts', $associated_post_id );
		}

		return array( 'newPostId' => $posted );
	}

	/**
	 * Insert a draft social-package post from generated copy.
	 *
	 * @param array  $social_copy        Generated copy rows.
	 * @param string $package_title      Post title.
	 * @param int    $associated_post_id Source post ID stored on each container.
	 * @return int|WP_Error
	 */
	private function post_social_package( array $social_copy, string $package_title, int $associated_post_id ) {
		$social_container_blocks = array();
		foreach ( $social_copy as $copy ) {
			if ( isset( $copy['error'] ) && $copy['error'] ) {
				continue;
			}
			$container                 = array(
				'blockName'    => 'prc-social/container',
				'attrs'        => array(
					'platform'                 => $copy['platform'],
					'sourcePostId'             => $associated_post_id,
					'aiAdditionalInstructions' => $copy['additionalInstructions'],
				),
				'innerBlocks'  => array(
					Message_Text::message_block( (string) $copy['copy'] ),
				),
				'innerHTML'    => '<div class="wp-block-prc-social-container"></div>',
				'innerContent' => array(
					'<div class="wp-block-prc-social-container">',
					null,
					'</div>',
				),
			);
			$social_container_blocks[] = $container;
		}

		$post_content            = wp_slash( serialize_blocks( $social_container_blocks ) );
		$social_package_post_arr = array(
			'post_content' => $post_content,
			'post_type'    => 'social-package',
			'post_title'   => $package_title,
			'post_status'  => 'draft',
		);
		$new_package_id          = wp_insert_post( $social_package_post_arr );
		if ( 0 === $new_package_id ) {
			$new_package_id = new WP_Error( 'post_id_was_0', __( 'Failed to create the social package. `wp_insert_post` returned 0.', 'prc-social-builder' ) );
		}
		return $new_package_id;
	}

	/**
	 * Run a callback on the requested target site.
	 *
	 * @param array|null $input    Ability input.
	 * @param callable   $callback Callback to run after site validation/switching.
	 * @return mixed
	 */
	private function with_site( $input, callable $callback ) {
		return \PRC\Platform\AI\Utils\with_site(
			\PRC\Platform\AI\Utils\resolve_site_id( is_array( $input ) ? $input : null ),
			self::PLUGIN_FILE,
			$callback
		);
	}
}
