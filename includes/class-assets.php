<?php
/**
 * Assets class.
 *
 * @package    PRC\Platform\Social_Builder
 */

namespace PRC\Platform\Social_Builder;

/**
 * Register and enqueue assets.
 *
 * @package    PRC\Platform\Social_Builder
 */
class Assets {
	public function __construct( $loader = null ) {
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_editor_ui', 1 );
	}

	public function enqueue_editor_ui() {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$should_enqueue = 'social-package' === $screen->post_type
			|| post_type_supports( $screen->post_type, 'prc-social-builder' );
		if ( ! $should_enqueue ) {
			return;
		}
		$asset_file = plugin_dir_path( __DIR__ ) . 'build/editor-ui/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$plugin_asset_file = include $asset_file;
		wp_enqueue_script(
			'prc-social-builder',
			plugins_url( 'build/editor-ui/index.js', __DIR__ ),
			$plugin_asset_file['dependencies'],
			$plugin_asset_file['version'],
			true
		);
	}
}
