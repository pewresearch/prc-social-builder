<?php
/**
 * Source-post capability bridge for social copy generation.
 *
 * @package PRC\Platform\Social_Builder
 */

declare(strict_types=1);

namespace PRC\Platform\Social_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Social copy abilities check `edit_post` on the source post.
 * Nexus Slack turns run as `prc_wp_bot`, which has `edit_posts` but not
 * `edit_published_posts`, so live reports return `forbidden_post`. While those
 * abilities run, treat a publicly readable post as a valid source.
 */
class Generate_Copy_Source_Caps {

	/**
	 * Abilities that read a post only to draft social copy.
	 *
	 * @var string[]
	 */
	public const SOURCE_ABILITIES = array(
		'prc-social-builder/generate-social-copy',
		'prc-social-builder/generate-story',
	);

	/**
	 * Nested execute depth for wrapped source abilities.
	 *
	 * @var int
	 */
	private static int $depth = 0;

	/**
	 * Register filters.
	 *
	 * @param Loader $loader Plugin loader.
	 */
	public function __construct( $loader ) {
		$loader->add_filter( 'wp_register_ability_args', $this, 'wrap_source_ability', 10, 2 );
		$loader->add_filter( 'user_has_cap', $this, 'grant_read_as_source_edit', 10, 4 );
	}

	/**
	 * Wrap execute callbacks for source-copy abilities.
	 *
	 * @param array<string, mixed> $args Ability args.
	 * @param string               $name Ability name.
	 * @return array<string, mixed>
	 */
	public function wrap_source_ability( $args, $name ) {
		if ( ! is_array( $args ) || ! is_string( $name ) ) {
			return $args;
		}

		if ( ! in_array( $name, self::SOURCE_ABILITIES, true ) ) {
			return $args;
		}

		if ( ! isset( $args['execute_callback'] ) || ! is_callable( $args['execute_callback'] ) ) {
			return $args;
		}

		$original                 = $args['execute_callback'];
		$args['execute_callback'] = function ( $input ) use ( $original ) {
			++self::$depth;
			try {
				return $original( $input );
			} finally {
				--self::$depth;
			}
		};

		return $args;
	}

	/**
	 * During wrapped executes, allow `edit_post` when the source is public.
	 *
	 * @param array<string, bool> $allcaps All caps for the user.
	 * @param string[]            $caps    Primitive caps required.
	 * @param array<int, mixed>   $args    Requested capability and object ids.
	 * @param \WP_User            $user    User being checked.
	 * @return array<string, bool>
	 */
	public function grant_read_as_source_edit( $allcaps, $caps, $args, $user ) {
		unset( $user );

		if ( self::$depth < 1 ) {
			return $allcaps;
		}

		if ( ! is_array( $allcaps ) || ! is_array( $caps ) || ! is_array( $args ) ) {
			return $allcaps;
		}

		$requested = isset( $args[0] ) ? (string) $args[0] : '';
		if ( 'edit_post' !== $requested ) {
			return $allcaps;
		}

		$post_id = isset( $args[2] ) ? absint( $args[2] ) : 0;
		if ( $post_id < 1 ) {
			return $allcaps;
		}

		if ( ! $this->is_public_source_post( $post_id ) ) {
			return $allcaps;
		}

		foreach ( $caps as $cap ) {
			if ( is_string( $cap ) && '' !== $cap ) {
				$allcaps[ $cap ] = true;
			}
		}

		return $allcaps;
	}

	/**
	 * Reset execute depth (tests).
	 */
	public static function reset_depth(): void {
		self::$depth = 0;
	}

	/**
	 * Whether a post is publicly readable source material.
	 *
	 * @param int $post_id Post ID.
	 */
	private function is_public_source_post( int $post_id ): bool {
		if ( function_exists( 'is_post_publicly_viewable' ) ) {
			return (bool) is_post_publicly_viewable( $post_id );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		return 'publish' === $post->post_status && empty( $post->post_password );
	}
}
