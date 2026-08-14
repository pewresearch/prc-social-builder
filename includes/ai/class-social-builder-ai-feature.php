<?php
/**
 * Social Builder AI feature.
 *
 * Registers Social Builder AI abilities with the WordPress AI plugin and
 * exposes ability names to the block editor script.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

use WordPress\AI\Abstracts\Abstract_Feature;
use WordPress\AI\Experiments\Experiment_Category;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Social Builder AI feature class.
 *
 * @since 1.0.0
 */
class Social_Builder_AI_Feature extends Abstract_Feature {

	/**
	 * Feature identifier.
	 */
	public static function get_id(): string {
		return 'social-builder-ai';
	}

	/**
	 * Feature metadata.
	 *
	 * @return array{label: string, description: string, category: string}
	 */
	protected function load_metadata(): array {
		return array(
			'label'       => __( 'Social Builder AI', 'prc-social-builder' ),
			'description' => __( 'Generates copy, messages, and story concepts for Social Builder from post content.', 'prc-social-builder' ),
			'category'    => Experiment_Category::EDITOR,
		);
	}

	/**
	 * Register abilities and editor localization when the feature is enabled.
	 */
	public function register(): void {
		$story   = new Generate_Story_Ability();
		$social_copy    = new Generate_Social_Copy_Ability();
		$social_package = new Add_Social_Package_Ability();

		add_action( 'wp_abilities_api_init', array( $story, 'register_ability' ) );
		add_action( 'wp_abilities_api_init', array( $social_copy, 'register_ability' ) );
		add_action( 'wp_abilities_api_init', array( $social_package, 'register_ability' ) );

		add_action( 'enqueue_block_editor_assets', array( $this, 'localize_ai_ability_names' ), 20 );
	}

	/**
	 * Expose registered ability names to the editor UI script.
	 *
	 * @hook enqueue_block_editor_assets
	 */
	public function localize_ai_ability_names(): void {
		$handle = 'prc-social-builder';

		if ( ! wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		wp_localize_script(
			$handle,
			'prcSocialBuilderAI',
			array(
				'enabled'            => true,
				'storyAbilityName'   => Generate_Story_Ability::$ability_name,
				'socialCopyAbilityName' => Generate_Social_Copy_Ability::$ability_name
			)
		);
	}
}
