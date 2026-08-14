<?php
/**
 * DataViews provider for Social Packages column.
 *
 * @package PRC\Platform\Social_Builder
 */

declare(strict_types=1);

namespace PRC\Platform\Social_Builder;

use WP_Post;

/**
 * Enriches shared admin DataViews rows with associated social packages.
 */
class DataViews_Provider {
	/**
	 * Constructor.
	 *
	 * @param Loader $loader Loader.
	 */
	public function __construct( $loader ) {
		$loader->add_filter( 'prc_wp_admin_dataview_shape_row', $this, 'shape_row', 10, 3 );
		$loader->add_filter( 'prc_wp_admin_dataview_localize', $this, 'localize', 10, 2 );
	}

	/**
	 * Whether the post type supports social builder.
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	private function supports( string $post_type ): bool {
		return post_type_supports( $post_type, 'prc-social-builder' );
	}

	/**
	 * Localize social field availability.
	 *
	 * @param array  $localize  Localize payload.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public function localize( $localize, $post_type ) {
		if ( ! $this->supports( (string) $post_type ) ) {
			return $localize;
		}

		$localize['social'] = array(
			'enabled' => true,
		);

		return $localize;
	}

	/**
	 * Enrich rows with social package links.
	 *
	 * @param array   $row       Row.
	 * @param WP_Post $post      Post.
	 * @param string  $post_type Post type.
	 * @return array
	 */
	public function shape_row( $row, $post, $post_type ) {
		if ( ! $post instanceof WP_Post || ! $this->supports( (string) $post_type ) ) {
			return $row;
		}

		$packages = get_posts(
			array(
				'post_type'   => Content_Type::$post_type,
				'meta_key'    => Content_Type::$meta_key_associated_posts,
				'meta_value'  => $post->ID,
				'numberposts' => 5,
				'post_status' => array( 'draft', 'publish' ),
			)
		);

		$row['socialPackages'] = array_map(
			static function ( $pkg ) {
				$status = 'publish' === $pkg->post_status
					? __( 'Published', 'prc-social-builder' )
					: __( 'Draft', 'prc-social-builder' );

				return array(
					'id'       => (int) $pkg->ID,
					'title'    => $pkg->post_title
						? (string) $pkg->post_title
						: sprintf(
							/* translators: %d: social package ID */
							__( 'Package #%d', 'prc-social-builder' ),
							(int) $pkg->ID
						),
					'status'   => $status,
					'edit_url' => (string) get_edit_post_link( $pkg->ID, 'raw' ),
				);
			},
			$packages
		);

		return $row;
	}
}
