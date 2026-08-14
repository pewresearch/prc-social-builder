<?php
/**
 * Batched editorial post-processing for Social Builder AI output.
 *
 * @package PRC\Platform\Social_Builder
 */

declare( strict_types=1 );

namespace PRC\Platform\Social_Builder;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs generated social copy through required platform editorial abilities.
 */
class Editorial_Passes {

	const HUMANIZER_ABILITY  = 'prc-ai/humanizer';
	const NEUTRALITY_ABILITY = 'prc-ai/neutrality-tone-editor';
	const MAX_ITEMS           = 10;
	const MAX_ITEM_CHARS      = 3000;
	const SOURCE_CHAR_LIMIT   = 15000;

	/**
	 * Apply humanization, then optional source-grounded neutrality, to a batch.
	 *
	 * Humanizer is always required. Neutrality runs when enabled in settings
	 * (default on) and fails closed when that ability or its result is unavailable.
	 *
	 * @param list<array{id: string, text: string}> $items  Stable-ID text items.
	 * @param string                                $source Authoritative post source.
	 * @return list<array{id: string, text: string}>|WP_Error
	 */
	public static function apply_batch( array $items, string $source ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error(
				'abilities_api_unavailable',
				__( 'The Abilities API is unavailable for editorial processing.', 'prc-social-builder' )
			);
		}

		if ( ! self::valid_input_items( $items ) ) {
			return new WP_Error(
				'invalid_editorial_items',
				__( 'Generated editorial items are invalid.', 'prc-social-builder' )
			);
		}

		$neutrality_enabled = self::is_neutrality_enabled();
		$humanity_enabled   = self::use_humanizer();

		$humanizer = null; 
		if ( $humanity_enabled ) {
			$humanizer          = wp_get_ability( self::HUMANIZER_ABILITY );
			if ( ! $humanizer ) {
				return new WP_Error(
					'editorial_ability_unavailable',
					__( 'Required editorial abilities are unavailable.', 'prc-social-builder' )
				);
			}
		}

		$neutrality = null;
		if ( $neutrality_enabled ) {
			$neutrality = wp_get_ability( self::NEUTRALITY_ABILITY );
			if ( ! $neutrality ) {
				return new WP_Error(
					'editorial_ability_unavailable',
					__( 'Required editorial abilities are unavailable.', 'prc-social-builder' )
				);
			}
		}

		$humanized = ( $humanity_enabled ) ? $humanizer->execute( array( 'items' => $items ) ) : array( 'items' => $items );
		if ( is_wp_error( $humanized ) ) {
			return self::wrap_ability_error(
				'humanizer_failed',
				__( 'The humanizer could not process the generated copy.', 'prc-social-builder' ),
				$humanized
			);
		}
		if ( ! self::valid_ability_result( $humanized, $items ) ) {
			return new WP_Error(
				'humanizer_failed',
				__( 'The humanizer could not process the generated copy.', 'prc-social-builder' )
			);
		}

		if ( ! $neutrality_enabled ) {
			return $humanized['items'];
		}

		$neutralized = $neutrality->execute(
			array(
				'items'  => $humanized['items'],
				'source' => mb_substr( trim( $source ), 0, self::SOURCE_CHAR_LIMIT ),
			)
		);
		if ( is_wp_error( $neutralized ) ) {
			return self::wrap_ability_error(
				'neutrality_failed',
				__( 'The neutrality tone editor could not process the generated copy.', 'prc-social-builder' ),
				$neutralized
			);
		}
		if ( ! self::valid_ability_result( $neutralized, $items ) ) {
			return new WP_Error(
				'neutrality_failed',
				__( 'The neutrality tone editor could not process the generated copy.', 'prc-social-builder' )
			);
		}

		return $neutralized['items'];
	}

	/**
	 * Whether the neutrality tone pass should run after humanization.
	 *
	 * Defaults to enabled when settings are unavailable so existing behavior is preserved.
	 *
	 * @return bool
	 */
	private static function is_neutrality_enabled(): bool {
		if ( ! class_exists( Settings::class ) ) {
			return true;
		}

		$settings = Settings::get_settings();
		return (bool) ( $settings['enable_neutrality_pass'] ?? true );
	}

	/**
	 * Whether the humanizer should run.
	 *
	 * Defaults to enabled when settings are unavailable so existing behavior is preserved.
	 *
	 * @return bool
	 */
	private static function use_humanizer(): bool {
		if ( ! class_exists( Settings::class ) ) {
			return true;
		}

		$settings = Settings::get_settings();
		return (bool) ( $settings['enable_humanizer'] ?? true );
	}
	

	/**
	 * Preserve the underlying ability error for the editor console / logs.
	 *
	 * @param string   $code    Public error code.
	 * @param string   $message Public error message.
	 * @param WP_Error $cause   Underlying ability error.
	 * @return WP_Error
	 */
	private static function wrap_ability_error( string $code, string $message, WP_Error $cause ): WP_Error {
		$detail = $cause->get_error_message();
		if ( '' !== $detail ) {
			$message = $message . ' ' . $detail;
		}

		return new WP_Error(
			$code,
			$message,
			array(
				'cause_code'    => $cause->get_error_code(),
				'cause_message' => $detail,
				'cause_data'    => $cause->get_error_data(),
			)
		);
	}

	/**
	 * Validate locally-created input items.
	 *
	 * @param array<int, mixed> $items Candidate items.
	 * @return bool
	 */
	private static function valid_input_items( array $items ): bool {
		if ( array() === $items || count( $items ) > self::MAX_ITEMS ) {
			return false;
		}

		$ids = array();
		foreach ( $items as $item ) {
			if (
				! is_array( $item )
				|| ! isset( $item['id'], $item['text'] )
				|| ! is_string( $item['id'] )
				|| ! is_string( $item['text'] )
				|| '' === trim( $item['id'] )
				|| '' === trim( $item['text'] )
				|| mb_strlen( $item['text'] ) > self::MAX_ITEM_CHARS
				|| isset( $ids[ $item['id'] ] )
			) {
				return false;
			}
			$ids[ $item['id'] ] = true;
		}

		return true;
	}

	/**
	 * Confirm an ability returned every item once, in the original order.
	 *
	 * @param mixed                                  $result         Ability result.
	 * @param list<array{id: string, text: string}> $expected_items Original items.
	 * @return bool
	 */
	private static function valid_ability_result( $result, array $expected_items ): bool {
		if ( ! is_array( $result ) || ! isset( $result['items'] ) || ! is_array( $result['items'] ) ) {
			return false;
		}

		if ( ! self::valid_input_items( $result['items'] ) || count( $result['items'] ) !== count( $expected_items ) ) {
			return false;
		}

		return array_column( $expected_items, 'id' ) === array_column( $result['items'], 'id' );
	}
}
