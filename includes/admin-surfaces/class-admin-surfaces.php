<?php
declare(strict_types=1);

namespace PRC\Platform\Social_Builder;

class Admin_Surfaces {
	public function __construct( $loader ) {
		$loader->add_action( 'enqueue_block_editor_assets', $this, 'enqueue_admin_surfaces_script' );
		$loader->add_action( 'wp_enqueue_scripts', $this, 'enqueue_frontend_report' );
	}

	public function enqueue_admin_surfaces_script(): void {
		$plugin_root = dirname( __DIR__, 2 );
		$plugin_file = $plugin_root . '/prc-social-builder.php';
		$asset_file  = $plugin_root . '/build/admin-surfaces/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script(
			'prc-social-builder-admin-surfaces',
			plugins_url( 'build/admin-surfaces/index.js', $plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'prc-social-builder-admin-surfaces',
			plugins_url( 'build/admin-surfaces/style-index.css', $plugin_file ),
			array(),
			$asset['version']
		);
	}

	public function enqueue_frontend_report(): void {
		if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( ! is_singular() ) {
			return;
		}
		$post_type = get_post_type();
		if ( ! $post_type || ! post_type_supports( $post_type, 'prc-social-builder' ) ) {
			return;
		}
		$plugin_root = dirname( __DIR__, 2 );
		$plugin_file = $plugin_root . '/prc-social-builder.php';
		$asset_file  = $plugin_root . '/build/admin-surfaces/index.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script(
			'prc-social-builder-frontend-report',
			plugins_url( 'build/admin-surfaces/index.js', $plugin_file ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_enqueue_style(
			'prc-social-builder-frontend-report',
			plugins_url( 'build/admin-surfaces/style-index.css', $plugin_file ),
			array(),
			$asset['version']
		);
	}
}
