<?php
/**
 * Fired during plugin activation.
 *
 * @package    PRC\Platform\Social_Builder
 */

namespace PRC\Platform\Social_Builder;

class Plugin_Activator {
	public static function activate() {
		flush_rewrite_rules();
	}
}
