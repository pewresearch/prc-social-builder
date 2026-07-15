<?php
/**
 * Smoke tests for Social Builder editorial and number-check passes.
 *
 * Run with:
 *   php plugins/prc-social-builder/tests/test-editorial-passes.php
 */

declare(strict_types=1);

namespace {

	$GLOBALS['__failed']    = 0;
	$GLOBALS['__abilities'] = array();
	$GLOBALS['__calls']     = array();

	function assert_true( bool $condition, string $message ): void {
		if ( $condition ) {
			echo "PASS: {$message}\n";
			return;
		}
		echo "FAIL: {$message}\n";
		++$GLOBALS['__failed'];
	}

	class WP_Error {
		private string $code;
		private string $message;
		private $data;

		public function __construct( string $code = '', string $message = '', $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data() {
			return $this->data;
		}
	}

	class Fake_Ability {
		private string $name;
		private $callback;

		public function __construct( string $name, callable $callback ) {
			$this->name     = $name;
			$this->callback = $callback;
		}

		public function execute( array $input ) {
			$GLOBALS['__calls'][] = array(
				'name'  => $this->name,
				'input' => $input,
			);
			return ( $this->callback )( $input );
		}
	}

	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}

	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}

	function wp_get_ability( string $name ) {
		return $GLOBALS['__abilities'][ $name ] ?? null;
	}

	define( 'ABSPATH', __DIR__ . '/' );
}

namespace PRC\Platform\Social_Builder {
	/**
	 * Test double for Settings::get_settings().
	 */
	class Settings {
		/** @var array<string, mixed> */
		public static array $values = array(
			'enable_neutrality_pass' => true,
		);

		/**
		 * @return array<string, mixed>
		 */
		public static function get_settings(): array {
			return self::$values;
		}
	}
}

namespace {
	use PRC\Platform\Social_Builder\Editorial_Passes;
	use PRC\Platform\Social_Builder\Number_Check;
	use PRC\Platform\Social_Builder\Settings;

	require_once dirname( __DIR__ ) . '/includes/ai/class-editorial-passes.php';
	require_once dirname( __DIR__ ) . '/includes/ai/class-number-check.php';

	$items = array(
		array(
			'id'   => 'message-a',
			'text' => 'A groundbreaking 42% result demands action.',
		),
		array(
			'id'   => 'message-b',
			'text' => 'Only 7% disagreed.',
		),
	);

	$missing = Editorial_Passes::apply_batch( $items, 'Source text.' );
	assert_true(
		is_wp_error( $missing ) && 'editorial_ability_unavailable' === $missing->get_error_code(),
		'fails when both editorial abilities are missing'
	);

	$GLOBALS['__abilities'][ Editorial_Passes::HUMANIZER_ABILITY ] = new Fake_Ability(
		'humanizer',
		static fn( array $input ): array => array(
			'items'   => $input['items'],
			'summary' => '',
		)
	);
	$one_sided = Editorial_Passes::apply_batch( $items, 'Source text.' );
	assert_true(
		is_wp_error( $one_sided ) && 'editorial_ability_unavailable' === $one_sided->get_error_code(),
		'fails when neutrality ability is missing'
	);

	Settings::$values['enable_neutrality_pass'] = false;
	$GLOBALS['__calls']                         = array();
	unset( $GLOBALS['__abilities'][ Editorial_Passes::NEUTRALITY_ABILITY ] );
	$GLOBALS['__abilities'][ Editorial_Passes::HUMANIZER_ABILITY ] = new Fake_Ability(
		'humanizer',
		static function ( array $input ): array {
			$out            = $input['items'];
			$out[0]['text'] = 'Humanized only.';
			return array(
				'items'   => $out,
				'summary' => '',
			);
		}
	);
	$neutrality_off = Editorial_Passes::apply_batch( $items, 'Source text.' );
	assert_true( ! is_wp_error( $neutrality_off ), 'succeeds when neutrality is disabled' );
	assert_true(
		array( 'humanizer' ) === array_column( $GLOBALS['__calls'], 'name' ),
		'skips neutrality ability when disabled'
	);
	assert_true(
		'Humanized only.' === $neutrality_off[0]['text'],
		'returns humanized copy when neutrality is disabled'
	);
	Settings::$values['enable_neutrality_pass'] = true;

	$GLOBALS['__calls'] = array();
	$GLOBALS['__abilities'][ Editorial_Passes::HUMANIZER_ABILITY ] = new Fake_Ability(
		'humanizer',
		static function ( array $input ): array {
			$out = $input['items'];
			foreach ( $out as &$item ) {
				$item['text'] = str_replace( 'groundbreaking ', '', $item['text'] );
			}
			unset( $item );
			return array(
				'items'   => $out,
				'summary' => 'Removed AI wording.',
			);
		}
	);
	$GLOBALS['__abilities'][ Editorial_Passes::NEUTRALITY_ABILITY ] = new Fake_Ability(
		'neutrality',
		static function ( array $input ): array {
			assert_true(
				false === strpos( $input['items'][0]['text'], 'groundbreaking' ),
				'neutrality receives humanized copy'
			);
			assert_true( 15000 === strlen( $input['source'] ), 'neutrality source is bounded' );
			$out = $input['items'];
			foreach ( $out as &$item ) {
				$item['text'] = str_replace( ' demands action', '', $item['text'] );
			}
			unset( $item );
			return array(
				'items'   => $out,
				'summary' => 'Removed advocacy.',
			);
		}
	);

	$edited = Editorial_Passes::apply_batch( $items, str_repeat( 's', 16000 ) );
	assert_true( ! is_wp_error( $edited ), 'runs editorial passes successfully' );
	assert_true(
		array( 'humanizer', 'neutrality' ) === array_column( $GLOBALS['__calls'], 'name' ),
		'runs humanizer before neutrality exactly once'
	);
	assert_true(
		'A 42% result.' === $edited[0]['text'],
		'returns final neutral copy'
	);

	$GLOBALS['__abilities'][ Editorial_Passes::HUMANIZER_ABILITY ] = new Fake_Ability(
		'humanizer',
		static fn(): WP_Error => new WP_Error( 'provider_error' )
	);
	$humanizer_error = Editorial_Passes::apply_batch( $items, 'Source text.' );
	assert_true(
		is_wp_error( $humanizer_error ) && 'humanizer_failed' === $humanizer_error->get_error_code(),
		'fails closed on humanizer error'
	);

	$GLOBALS['__abilities'][ Editorial_Passes::HUMANIZER_ABILITY ] = new Fake_Ability(
		'humanizer',
		static function ( array $input ): array {
			$input['items'][0]['id'] = 'changed-id';
			return array(
				'items'   => $input['items'],
				'summary' => '',
			);
		}
	);
	$changed_id = Editorial_Passes::apply_batch( $items, 'Source text.' );
	assert_true(
		is_wp_error( $changed_id ) && 'humanizer_failed' === $changed_id->get_error_code(),
		'fails closed when an ability changes ids'
	);

	$GLOBALS['__abilities'][ Editorial_Passes::HUMANIZER_ABILITY ] = new Fake_Ability(
		'humanizer',
		static fn( array $input ): array => array(
			'items'   => $input['items'],
			'summary' => '',
		)
	);
	$GLOBALS['__abilities'][ Editorial_Passes::NEUTRALITY_ABILITY ] = new Fake_Ability(
		'neutrality',
		static fn(): WP_Error => new WP_Error( 'provider_error', 'Model refused the request.' )
	);
	$neutrality_error = Editorial_Passes::apply_batch( $items, 'Source text.' );
	assert_true(
		is_wp_error( $neutrality_error ) && 'neutrality_failed' === $neutrality_error->get_error_code(),
		'fails closed on neutrality error'
	);
	assert_true(
		false !== strpos( $neutrality_error->get_error_message(), 'Model refused the request.' ),
		'surfaces underlying neutrality error detail'
	);

	$GLOBALS['__abilities'][ Editorial_Passes::NEUTRALITY_ABILITY ] = new Fake_Ability(
		'neutrality',
		static function ( array $input ): array {
			$input['items'][0]['id'] = 'changed-id';
			return array(
				'items'   => $input['items'],
				'summary' => '',
			);
		}
	);
	$neutrality_changed_id = Editorial_Passes::apply_batch( $items, 'Source text.' );
	assert_true(
		is_wp_error( $neutrality_changed_id ) && 'neutrality_failed' === $neutrality_changed_id->get_error_code(),
		'fails closed when neutrality changes ids'
	);

	$GLOBALS['__calls'] = array();
	$number_items        = array_merge(
		$items,
		array(
			array(
				'id'   => 'message-c',
				'text' => 'The sample included 142 people.',
			),
		)
	);
	$GLOBALS['__abilities'] = array(
		'prc-ai/check-numbers' => new Fake_Ability(
			'number-check',
			static function ( array $input ): array {
				assert_true( 2 === $input['passes'], 'requests two number-check passes' );
				assert_true( 'The source says 7%.' === $input['input'], 'passes source under input key' );
				assert_true( false === strpos( $input['output'], 'message-a' ), 'combined copy excludes item ids' );
				return array(
					'valid'   => false,
					'numbers' => array(
						array(
							'token'  => '42',
							'status' => 'unverified',
						),
						array(
							'token'  => '7%',
							'status' => 'verified',
						),
					),
					'passes'  => 2,
				);
			}
		),
	);

	$annotations = Number_Check::annotate_many( $number_items, 'The source says 7%.' );
	assert_true( 1 === count( $GLOBALS['__calls'] ), 'runs number-check ability once for batch' );
	assert_true( false === $annotations['message-a']['valid'], 'flags affected item' );
	assert_true( array( '42' ) === $annotations['message-a']['flagged'], 'maps flagged token to affected item' );
	assert_true( true === $annotations['message-b']['valid'], 'leaves verified item valid' );
	assert_true( true === $annotations['message-c']['valid'], 'does not match token inside a larger number' );

	$GLOBALS['__abilities'] = array();
	assert_true( null === Number_Check::annotate_many( $items, 'Source.' ), 'omits annotation when ability is missing' );

	$GLOBALS['__abilities']['prc-ai/check-numbers'] = new Fake_Ability(
		'number-check',
		static fn(): WP_Error => new WP_Error( 'provider_error' )
	);
	assert_true( null === Number_Check::annotate_many( $items, 'Source.' ), 'omits annotation on ability error' );

	$GLOBALS['__abilities']['prc-ai/check-numbers'] = new Fake_Ability(
		'number-check',
		static fn(): array => array(
			'valid'   => false,
			'numbers' => array(),
			'passes'  => 0,
		)
	);
	assert_true(
		null === Number_Check::annotate_many( $items, 'Source.' ),
		'omits internally inconsistent annotation'
	);

	if ( $GLOBALS['__failed'] > 0 ) {
		fwrite( STDERR, "\n{$GLOBALS['__failed']} failure(s)\n" );
		exit( 1 );
	}

	echo "\nAll Social Builder editorial pass tests passed.\n";
	exit( 0 );
}
