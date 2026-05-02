<?php
/**
 * Fired during plugin deactivation.
 *
 * @package    PRC\Platform\Social_Builder
 */

namespace PRC\Platform\Social_Builder;

class Plugin_Deactivator {
	public static function deactivate() {
		flush_rewrite_rules();
	}
}
