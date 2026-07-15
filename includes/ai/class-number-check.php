<?php
/**
 * Number check annotation helper.
 *
 * Thin wrapper around the platform-wide `prc-ai/check-numbers` ability used
 * to annotate generated social copy with numeric-claim verdicts. Annotation
 * only — options are never blocked or filtered.
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
	 * Check a batch of generated text against source content.
	 *
	 * Executes the number-check ability once for the combined final copy, then
	 * maps unverified tokens back to each item that contains them.
	 *
	 * @param list<array{id: string, text: string}> $items       Generated text items.
	 * @param string                                $source_text Source content.
	 * @return array<string, array{valid: bool, flagged: list<string>}>|null
	 */
	public static function annotate_many( array $items, string $source_text ): ?array {
		if ( ! function_exists( 'wp_get_ability' ) || array() === $items ) {
			return null;
		}

		$ability = wp_get_ability( 'prc-ai/check-numbers' );
		if ( ! $ability ) {
			return null;
		}

		$texts = array();
		$out   = array();
		foreach ( $items as $item ) {
			if (
				! is_array( $item )
				|| ! isset( $item['id'], $item['text'] )
				|| ! is_string( $item['id'] )
				|| ! is_string( $item['text'] )
				|| '' === $item['id']
				|| '' === $item['text']
			) {
				return null;
			}

			$texts[]            = $item['text'];
			$out[ $item['id'] ] = array(
				'valid'   => true,
				'flagged' => array(),
			);
		}

		$result = $ability->execute(
			array(
				'output' => implode( "\n\n---\n\n", $texts ),
				'input'  => $source_text,
				'passes' => self::PASSES,
			)
		);
		if (
			is_wp_error( $result )
			|| ! is_array( $result )
			|| ! isset( $result['valid'] )
			|| ! is_bool( $result['valid'] )
			|| ! isset( $result['numbers'] )
			|| ! is_array( $result['numbers'] )
		) {
			return null;
		}

		$has_unverified = false;
		$mapped_tokens  = 0;
		foreach ( $result['numbers'] as $number ) {
			if (
				! is_array( $number )
				|| ! isset( $number['status'], $number['token'] )
				|| 'verified' === $number['status']
			) {
				continue;
			}

			$has_unverified = true;
			$token          = (string) $number['token'];
			foreach ( $items as $item ) {
				if ( self::contains_token( $item['text'], $token ) ) {
					$out[ $item['id'] ]['valid']     = false;
					$out[ $item['id'] ]['flagged'][] = $token;
					++$mapped_tokens;
				}
			}
		}

		if ( $result['valid'] === $has_unverified || ( $has_unverified && 0 === $mapped_tokens ) ) {
			return null;
		}

		foreach ( $out as &$annotation ) {
			$annotation['flagged'] = array_values( array_unique( $annotation['flagged'] ) );
		}
		unset( $annotation );

		return $out;
	}

	/**
	 * Check one generated text item.
	 *
	 * @param string $text        Generated text.
	 * @param string $source_text Source content.
	 * @return array{valid: bool, flagged: list<string>}|null
	 */
	public static function annotate( string $text, string $source_text ): ?array {
		$annotations = self::annotate_many(
			array(
				array(
					'id'   => 'item',
					'text' => $text,
				),
			),
			$source_text
		);

		return null === $annotations ? null : $annotations['item'];
	}

	/**
	 * Whether text contains a numeric token without embedding it in another word or number.
	 *
	 * @param string $text  Generated text.
	 * @param string $token Numeric token returned by the checker.
	 * @return bool
	 */
	private static function contains_token( string $text, string $token ): bool {
		if ( '' === $token ) {
			return false;
		}

		$pattern = '/(?<![\p{L}\p{N}])' . preg_quote( $token, '/' ) . '(?![\p{L}\p{N}])/u';
		return 1 === preg_match( $pattern, $text );
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
