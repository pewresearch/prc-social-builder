<?php
/**
 * Number check annotation helper.
 *
 * Thin wrapper around the platform-wide number checker
 * (PRC\Platform\AI\Utils\check_numbers) used to annotate generated social
 * copy with a numeric-claim verification verdict. Annotation only — options
 * are never blocked or filtered; the editor decides what to do with flags.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Annotates generated text with number verification results.
 *
 * @since 1.1.0
 */
class Number_Check {

	/**
	 * LLM verification passes for social copy checks.
	 */
	const PASSES = 2;

	/**
	 * Check the numbers in a piece of generated text against source content.
	 *
	 * Returns null when the platform number checker is unavailable (e.g. a
	 * standalone install outside the monorepo), so callers can skip the
	 * annotation gracefully.
	 *
	 * @param string $text        The generated text to check.
	 * @param string $source_text The source content the text was generated from.
	 * @return array{valid: bool, flagged: array<int, string>}|null
	 */
	public static function annotate( string $text, string $source_text ): ?array {
		if ( ! function_exists( '\PRC\Platform\AI\Utils\check_numbers' ) ) {
			return null;
		}

		$result = \PRC\Platform\AI\Utils\check_numbers( $text, $source_text, self::PASSES );

		$flagged = array();
		foreach ( $result['numbers'] as $number ) {
			if ( 'verified' !== $number['status'] ) {
				$flagged[] = (string) $number['token'];
			}
		}

		return array(
			'valid'   => (bool) $result['valid'],
			'flagged' => $flagged,
		);
	}

	/**
	 * Shared output-schema fragment for the numberCheck property.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_output_schema_fragment(): array {
		return array(
			'type'        => 'object',
			'description' => 'Numeric-claim verification of the generated text against the source post. Omitted when the platform number checker is unavailable.',
			'properties'  => array(
				'valid'   => array(
					'type'        => 'boolean',
					'description' => 'True when every number in the text is verified against the source.',
				),
				'flagged' => array(
					'type'        => 'array',
					'items'       => array( 'type' => 'string' ),
					'description' => 'Numeric claims that could not be verified or are contradicted by the source.',
				),
			),
		);
	}
}
