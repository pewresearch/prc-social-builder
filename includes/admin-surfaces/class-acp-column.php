<?php
declare(strict_types=1);

namespace PRC\Platform\Social_Builder;

class ACP_Column {
	public function __construct( $loader ) {
		$loader->add_filter( 'manage_posts_columns', $this, 'add_column' );
		$loader->add_action( 'manage_posts_custom_column', $this, 'render_column', 10, 2 );
	}

	public function add_column( array $columns ): array {
		$columns['social_packages'] = __( 'Social Packages', 'prc-social-builder' );
		return $columns;
	}

	public function render_column( string $column_name, int $post_id ): void {
		if ( 'social_packages' !== $column_name ) {
			return;
		}
		if ( ! post_type_supports( get_post_type( $post_id ), 'prc-social-builder' ) ) {
			echo '—';
			return;
		}
		$packages = get_posts( array(
			'post_type'  => Content_Type::$post_type,
			'meta_key'   => Content_Type::$meta_key_associated_posts,
			'meta_value' => $post_id,
			'numberposts' => 5,
			'post_status' => array( 'draft', 'publish' ),
		) );

		if ( empty( $packages ) ) {
			echo '<span style="color:#999;">—</span>';
			return;
		}

		foreach ( $packages as $pkg ) {
			$status = 'publish' === $pkg->post_status
				? __( 'Published', 'prc-social-builder' )
				: __( 'Draft', 'prc-social-builder' );
			$edit_url = get_edit_post_link( $pkg->ID );
			printf(
				'<a href="%s">%s (%s)</a><br>',
				esc_url( $edit_url ),
				esc_html( $pkg->post_title ?: sprintf( 'Package #%d', $pkg->ID ) ),
				esc_html( $status )
			);
		}
	}
}
