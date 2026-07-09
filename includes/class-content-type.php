<?php
/**
 * Social Builder content type.
 *
 * @package PRC\Platform\Social_Builder
 */

declare(strict_types=1);

namespace PRC\Platform\Social_Builder;

/**
 * Social Builder content type.
 *
 * @package PRC\Platform\Social_Builder
 */
class Content_Type {
	/**
	 * The post type slug.
	 *
	 * @var string
	 */
	public static string $post_type = 'social-package';

	/**
	 * The meta key for associated posts.
	 *
	 * @var string
	 */
	public static string $meta_key_associated_posts = '_prc_associated_posts';

	/**
	 * Constructor.
	 *
	 * @param Loader $loader The loader instance.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'init', $this, 'register_post_type' );
		$loader->add_action( 'init', $this, 'register_post_meta' );
		$loader->add_action( 'init', $this, 'register_default_post_type_support', 5 );
		$loader->add_action( 'rest_api_init', $this, 'register_rest_fields' );
		$loader->add_action( 'rest_api_init', $this, 'register_rest_query_params' );
		$loader->add_filter( 'allowed_block_types_all', $this, 'restrict_blocks_to_post_type', 10, 2 );
		$loader->add_action( 'admin_enqueue_scripts', $this, 'dequeue_ai_summarization', 20 );
	}

	/**
	 * Dequeue the AI summarization script on the spoken-article editor screen.
	 *
	 * @hook admin_enqueue_scripts 20
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function dequeue_ai_summarization( string $hook_suffix ): void {
		if ( 'post.php' !== $hook_suffix && 'post-new.php' !== $hook_suffix ) {
			return;
		}
		$screen = get_current_screen();
		if ( $screen && self::$post_type === $screen->post_type ) {
			wp_dequeue_script( 'ai_summarization' );
			wp_dequeue_style( 'ai_summarization' );
			wp_dequeue_script( 'ai_title_generation' );
			wp_dequeue_style( 'ai_title_generation' );
			wp_dequeue_script( 'ai_excerpt_generation' );
			wp_dequeue_style( 'ai_excerpt_generation' );
		}
	}

	/**
	 * Get the labels for the social-package post type.
	 *
	 * @return array
	 */
	private function get_labels(): array {
		return array(
			'name'               => __( 'Social Packages', 'prc-social-builder' ),
			'singular_name'      => __( 'Social Package', 'prc-social-builder' ),
			'add_new'            => __( 'Add New', 'prc-social-builder' ),
			'add_new_item'       => __( 'Add New Social Package', 'prc-social-builder' ),
			'edit_item'          => __( 'Edit Social Package', 'prc-social-builder' ),
			'new_item'           => __( 'New Social Package', 'prc-social-builder' ),
			'view_item'          => __( 'View Social Package', 'prc-social-builder' ),
			'search_items'       => __( 'Search Social Packages', 'prc-social-builder' ),
			'not_found'          => __( 'No social packages found.', 'prc-social-builder' ),
			'not_found_in_trash' => __( 'No social packages found in Trash.', 'prc-social-builder' ),
		);
	}

	/**
	 * Register the social-package post type.
	 *
	 * @return void
	 */
	public function register_post_type(): void {
		$args = array(
			'labels'             => $this->get_labels(),
			'public'             => false,
			'publicly_queryable' => false,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'show_in_rest'       => true,
			'menu_icon'          => 'dashicons-share',
			'supports'           => array( 'title', 'editor', 'author', 'revisions', 'custom-fields' ),
			'has_archive'        => false,
			'rewrite'            => false,
			'template'           => array(
				array( 'prc-social/thread', array( 'platform' => 'twitter' ) ),
			),
			'template_lock'      => false,
		);
		register_post_type( self::$post_type, $args );
		add_post_type_support( self::$post_type, 'editor', array( 'notes' => true ) );
	}

	/**
	 * Register the post meta for the social-package post type.
	 *
	 * @return void
	 */
	public function register_post_meta(): void {
		register_post_meta(
			self::$post_type,
			self::$meta_key_associated_posts,
			array(
				'type'          => 'integer',
				'single'        => false,
				'show_in_rest'  => true,
				'description'   => __( 'Post IDs associated with this social package.', 'prc-social-builder' ),
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		register_post_meta(
			self::$post_type,
			'_hootsuite_draft_ids',
			array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
				'default'       => array(),
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);

		register_post_meta(
			self::$post_type,
			'_hootsuite_errors',
			array(
				'type'          => 'array',
				'single'        => true,
				'show_in_rest'  => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
				'default'       => array(),
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			)
		);
	}

	/**
	 * Register the REST fields for the social-package post type.
	 *
	 * @return void
	 */
	public function register_rest_fields(): void {
		register_rest_field(
			self::$post_type,
			'associatedPostsOrdered',
			array(
				'get_callback'    => array( $this, 'get_associated_posts_ordered' ),
				'update_callback' => array( $this, 'update_associated_posts_ordered' ),
				'schema'          => array(
					'description' => __( 'Ordered list of posts associated with this social package.', 'prc-social-builder' ),
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'key'    => array( 'type' => 'string' ),
							'postId' => array( 'type' => 'integer' ),
							'title'  => array( 'type' => 'string' ),
						),
					),
					'context'     => array( 'view', 'edit' ),
				),
			)
		);
	}

	/**
	 * Register the REST query params for the social-package post type.
	 *
	 * @return void
	 */
	public function register_rest_query_params(): void {
		add_filter(
			'rest_social-package_collection_params',
			function ( array $params ): array {
				$params['associated_post_id'] = array(
					'description'       => __( 'Filter by associated post ID.', 'prc-social-builder' ),
					'type'              => 'integer',
					'sanitize_callback' => 'absint',
					'validate_callback' => 'rest_validate_request_arg',
				);
				return $params;
			}
		);

		add_filter(
			'rest_social-package_query',
			function ( array $args, \WP_REST_Request $request ): array {
				$post_id = $request->get_param( 'associated_post_id' );
				if ( $post_id ) {
					$args['meta_query'] = array(
						array(
							'key'     => self::$meta_key_associated_posts,
							'value'   => $post_id,
							'compare' => '=',
							'type'    => 'NUMERIC',
						),
					);
				}
				return $args;
			},
			10,
			2
		);
	}

	/**
	 * Get the associated posts ordered for the social-package post type.
	 *
	 * @param array $post The post array.
	 * @return array
	 */
	public function get_associated_posts_ordered( array $post ): array {
		$post_id  = $post['id'];
		$post_ids = get_post_meta( $post_id, self::$meta_key_associated_posts );
		if ( ! is_array( $post_ids ) ) {
			return array();
		}
		return array_values(
			array_filter(
				array_map(
					function ( $associated_id ) {
						$associated_id = (int) $associated_id;
						$title         = get_the_title( $associated_id );
						if ( ! $title ) {
							return null;
						}
						return array(
							'key'    => 'post_' . $associated_id,
							'postId' => $associated_id,
							'title'  => $title,
						);
					},
					$post_ids
				)
			)
		);
	}

	/**
	 * Update the associated posts ordered for the social-package post type.
	 *
	 * @param array    $items The items array.
	 * @param \WP_Post $post The post object.
	 * @return true|\WP_Error
	 */
	public function update_associated_posts_ordered( array $items, \WP_Post $post ): true|\WP_Error {
		$post_id = $post->ID;
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You do not have permission to edit this post.', 'prc-social-builder' ), array( 'status' => 403 ) );
		}
		delete_post_meta( $post_id, self::$meta_key_associated_posts );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				$item_post_id = isset( $item['postId'] ) ? (int) $item['postId'] : 0;
				if ( $item_post_id > 0 ) {
					add_post_meta( $post_id, self::$meta_key_associated_posts, $item_post_id );
				}
			}
		}
		return true;
	}

	/**
	 * Register default post type support for post
	 *
	 * @return void
	 */
	public function register_default_post_type_support(): void {
		add_post_type_support( 'post', 'prc-social-builder' );
	}

	/**
	 * Prevents prc-social/* blocks from appearing in the inserter on any post type
	 * other than the social-package CPT.
	 *
	 * @hook allowed_block_types_all
	 *
	 * @param bool|string[]            $allowed_block_types The allowed block types.
	 * @param \WP_Block_Editor_Context $editor_context The editor context.
	 * @return bool|string[]
	 */
	public function restrict_blocks_to_post_type( $allowed_block_types, $editor_context ) {
		if ( ! isset( $editor_context->post ) ) {
			return $allowed_block_types;
		}

		// Restrict social-package CPT to only use prc-social/* blocks.
		if ( $editor_context->post->post_type === self::$post_type ) {
			$social_allowed_blocks = array(
				'prc-social/thread',
				'prc-social/message',
				'prc-social/story',
				'tabor/markdown-comment',
				'tabor/todo-list',
				'core/paragraph',
			);
			return array_values(
				array_filter(
					$allowed_block_types,
					fn( string $block_name ) => in_array( $block_name, $social_allowed_blocks, true )
				)
			);
		}

		// If allowed_block_types is true, return all registered blocks.
		if ( true === $allowed_block_types ) {
			$allowed_block_types = array_keys( \WP_Block_Type_Registry::get_instance()->get_all_registered() );
		}

		// Filter out all prc-social/* blocks.
		return array_values(
			array_filter(
				$allowed_block_types,
				fn( string $block_name ) => ! str_starts_with( $block_name, 'prc-social/' )
			)
		);
	}
}
