<?php
/**
 * Registers Social Builder generate-copy as a PRC Nexus system.
 *
 * @package PRC\Platform\Social_Builder
 */

declare(strict_types=1);

namespace PRC\Platform\Social_Builder;

use PRC\Platform\Slack\Nexus\Suggested_Prompt;
use PRC\Platform\Slack\Nexus\System;
use PRC\Platform\Slack\Nexus\Systems;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Slack footprint: one System that exposes generate-social-copy.
 */
class Nexus_System {

	public const ID   = 'social-builder';
	public const TOOL = 'prc-social-builder/generate-social-copy';

	/**
	 * @hook prc_nexus_systems
	 *
	 * @param Systems $systems Registry.
	 */
	public function register( Systems $systems ): void {
		$systems->add(
			new System(
				id: self::ID,
				label: __( 'Social Builder', 'prc-social-builder' ),
				summary: __( 'Draft social copy for Facebook, X, Bluesky, and LinkedIn from a post ID.', 'prc-social-builder' ),
				instructions: self::instructions(),
				tools: array( self::TOOL ),
				suggested_prompts: array(
					new Suggested_Prompt(
						'Draft social copy',
						'Generate default-network social copy for a WordPress post. Ask me for the post ID if I have not given one.'
					),
				),
				required_capability: 'edit_posts'
			)
		);
	}

	/**
	 * Slack-facing instructions for generate-social-copy.
	 */
	public static function instructions(): string {
		return implode(
			"\n\n",
			array(
				'You draft Pew Research Center social copy. You do not create social-package posts and you do not publish to Hootsuite.',
				'When the editor wants copy, call prc-social-builder/generate-social-copy.',
				'Default call: isDefaultList true, and content as one wp-post item with postId.',
				'If they pasted source text, use contentType preformatted.',
				'If they name specific networks, set isDefaultList false and pass copyList with platform twitter, facebook, bluesky, linkedin, or threads.',
				'If they want report chapters, set includeReportChildren true.',
				'Ask for a numeric WordPress post ID when they name a report without one. Do not guess a post ID.',
				'Return each platform copy in Slack. Quote numberCheck flagged values when present. Keep numbers exactly as generated.',
				'This tool exists only when the Social Builder AI feature is enabled.',
			)
		);
	}
}
