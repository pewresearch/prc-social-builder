<?php
/**
 * Character limits for Social Builder platforms.
 *
 * Single source of truth. PHP reads it through `Platform_Limits`. The editor UI
 * gets the `default`, `social`, and `story` sections through
 * `Platform_Limits::get_editor_payload()`.
 *
 * Each entry has two numbers:
 * - `max` is the limit the network enforces. The editor counter uses it.
 * - `target` is the length generation aims for and enforces. It never exceeds
 *   `max`. Facebook allows 63206 characters, but generated copy stays near 500.
 *
 * `fields` entries are server-only and have a `target` only.
 *
 * @package PRC\Platform\Social_Builder
 */

return array(
	'default' => array(
		'max'    => 280,
		'target' => 280,
	),
	'social'  => array(
		'twitter'  => array(
			'max'    => 280,
			'target' => 280,
		),
		'facebook' => array(
			'max'    => 63206,
			'target' => 500,
		),
		'threads'  => array(
			'max'    => 500,
			'target' => 500,
		),
		'bluesky'  => array(
			'max'    => 300,
			'target' => 300,
		),
		'linkedin' => array(
			'max'    => 3000,
			'target' => 3000,
		),
	),
	'story'   => array(
		'default'   => array(
			'max'    => 2200,
			'target' => 2200,
		),
		'instagram' => array(
			'max'    => 2200,
			'target' => 2200,
		),
		'facebook'  => array(
			'max'    => 2200,
			'target' => 2200,
		),
		'tiktok'    => array(
			'max'    => 2200,
			'target' => 2200,
		),
		'youtube'   => array(
			'max'    => 5000,
			'target' => 3000,
		),
	),
	'fields'  => array(
		'summary'      => array(
			'max'    => 3000,
			'target' => 3000,
		),
		'storyOverlay' => array(
			'max'    => 80,
			'target' => 80,
		),
	),
);
