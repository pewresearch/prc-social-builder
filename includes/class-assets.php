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

	/**
	 * Data-only script handle that carries the character limits.
	 */
	const LIMITS_HANDLE = 'prc-social-builder-limits';

	/**
	 * Blocks whose editor scripts read the character limits.
	 *
	 * @var array<int, string>
	 */
	const LIMIT_BLOCKS = array( 'prc-social/container', 'prc-social/message', 'prc-social/story' );

	/**
	 * Hook asset registration.
	 *
	 * @param object|null $loader Plugin loader.
	 */
	public function __construct( $loader = null ) {
		$loader->add_action( 'init', $this, 'register_limits_script', 20 );
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_editor_ui', 1 );
	}

	/**
	 * Register the limits handle and make the block editor scripts depend on it.
	 *
	 * The blocks load on any block editor screen, so the handle is registered
	 * everywhere. It has no source. WordPress prints the localized data only when
	 * a script that depends on it prints.
	 *
	 * @hook init 20
	 */
	public function register_limits_script(): void {
		wp_register_script( self::LIMITS_HANDLE, false, array(), '1.0.0', true );
		wp_localize_script( self::LIMITS_HANDLE, 'prcSocialBuilderLimits', Platform_Limits::get_editor_payload() );

		$registry = \WP_Block_Type_Registry::get_instance();
		$scripts  = wp_scripts();
		foreach ( self::LIMIT_BLOCKS as $block_name ) {
			$block_type = $registry->get_registered( $block_name );
			if ( ! $block_type ) {
				continue;
			}
			foreach ( $block_type->editor_script_handles as $handle ) {
				if ( isset( $scripts->registered[ $handle ] ) ) {
					$scripts->registered[ $handle ]->deps[] = self::LIMITS_HANDLE;
				}
			}
		}
	}

	/**
	 * Enqueue the editor UI script on social package and supporting post types.
	 *
	 * @hook enqueue_block_editor_assets 1
	 */
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
			array_merge( $plugin_asset_file['dependencies'], array( self::LIMITS_HANDLE ) ),
			$plugin_asset_file['version'],
			true
		);
	}
}
