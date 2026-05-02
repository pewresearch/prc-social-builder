<?php
/**
 * Admin Bar class.
 *
 * @package PRC\Platform\Social_Builder
 */

declare(strict_types=1);

namespace PRC\Platform\Social_Builder;

/**
 * Admin Bar
 *
 * @package PRC\Platform\Social_Builder
 */
class Admin_Bar {
	/**
	 * The constructor.
	 *
	 * @param mixed $loader The loader.
	 */
	public function __construct( $loader ) {
		$loader->add_action( 'admin_bar_menu', $this, 'add_admin_bar_menu', 100 );
	}

	/**
	 * Add the admin bar menu.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar.
	 */
	public function add_admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			array(
				'id'    => 'prc-social-builder',
				'title' => __( 'Social Packages', 'prc-social-builder' ),
				'href'  => admin_url( 'edit.php?post_type=social-package' ),
			)
		);

		if ( is_singular() && post_type_supports( get_post_type(), 'prc-social-builder' ) ) {
			$post_id  = get_the_ID();
			$packages = get_posts(
				array(
					'post_type'   => Content_Type::$post_type,
					'meta_key'    => Content_Type::$meta_key_associated_posts,
					'meta_value'  => $post_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'numberposts' => 5,
					'post_status' => array( 'draft', 'publish' ),
				)
			);

			if ( ! empty( $packages ) ) {
				foreach ( $packages as $package ) {
					// translators: %s is the package title.
					$title = sprintf( __( 'Edit Social Package (%s)', 'prc-social-builder' ), $package->post_title );
					$wp_admin_bar->add_node(
						array(
							'id'     => 'prc-social-builder-edit-' . $package->ID,
							'parent' => 'prc-social-builder',
							'title'  => $title,
							'href'   => get_edit_post_link( $package->ID ),
						)
					);
				}
			}
		}
	}
}
