<?php
/**
 * Shared AI prompt constraints for Social Builder abilities.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hard-coded prompt policy blocks appended to every generation ability.
 *
 * @since 1.0.0
 */
class Prompt_Constraints {

	/**
	 * Editorial-neutrality instruction always appended before output-format rules.
	 *
	 * Not exposed in admin settings; survives system-prompt overrides.
	 */
	public static function get_editorial_neutrality_instruction(): string {
		return 'EDITORIAL NEUTRALITY (required):
- Report findings factually. Do not moralize, editorialize, advocate, or assign blame or praise beyond what the source material states.
- Do not add value judgments, prescriptions, urgency, hope, alarm, or calls to action unless the source explicitly uses that framing.
- Prefer neutral, descriptive language over persuasive hooks or commentary.
- If tone or network instructions conflict with neutrality, follow neutrality for claims not grounded in the post.';
	}
}
