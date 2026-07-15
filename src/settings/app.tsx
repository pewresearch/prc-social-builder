import { useMemo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { __experimentalText as Text } from '@wordpress/components';
import { SettingsPage, type SettingsFieldConfig } from '@prc/components';

import './style.scss';
import './store';
import { fetchSettings, saveSettings } from './api';
import { store as settingsStore } from './store';
import LockedFormatBlock from './components/locked-format-block';
import type { FlatPromptKey, StoryFieldPrompts } from './types';

const TEXT_DOMAIN = 'prc-social-builder';

const EDITORIAL_PASS_FIELDS: SettingsFieldConfig[] = [
	{
		id: 'enable_neutrality_pass',
		type: 'boolean',
		label: __('Neutrality pass', TEXT_DOMAIN),
		description: __(
			'After humanization, rewrite generated copy for source-grounded editorial neutrality. Disable to skip this pass.',
			TEXT_DOMAIN
		),
	},
];

const THREAD_COUNT_FIELDS: SettingsFieldConfig[] = [
	{
		id: 'thread_counts.twitter',
		type: 'integer',
		label: __('Twitter / X', TEXT_DOMAIN),
		min: 2,
		max: 10,
	},
	{
		id: 'thread_counts.facebook',
		type: 'integer',
		label: __('Facebook', TEXT_DOMAIN),
		description: __(
			'This platform does not support threaded posts. Always generates 1 post.',
			TEXT_DOMAIN
		),
		isDisabled: () => true,
	},
	{
		id: 'thread_counts.threads',
		type: 'integer',
		label: __('Threads', TEXT_DOMAIN),
		min: 2,
		max: 10,
	},
	{
		id: 'thread_counts.bluesky',
		type: 'integer',
		label: __('Bluesky', TEXT_DOMAIN),
		min: 2,
		max: 10,
	},
	{
		id: 'thread_counts.linkedin',
		type: 'integer',
		label: __('LinkedIn', TEXT_DOMAIN),
		description: __(
			'This platform does not support threaded posts. Always generates 1 post.',
			TEXT_DOMAIN
		),
		isDisabled: () => true,
	},
];

const NETWORK_INSTRUCTION_FIELDS: SettingsFieldConfig[] = [
	{
		id: 'network_instructions.twitter',
		type: 'textarea',
		label: __('Twitter / X', TEXT_DOMAIN),
		description: __(
			'These instructions are appended to all AI prompts when generating content for this network.',
			TEXT_DOMAIN
		),
	},
	{
		id: 'network_instructions.facebook',
		type: 'textarea',
		label: __('Facebook', TEXT_DOMAIN),
	},
	{
		id: 'network_instructions.threads',
		type: 'textarea',
		label: __('Threads', TEXT_DOMAIN),
	},
	{
		id: 'network_instructions.bluesky',
		type: 'textarea',
		label: __('Bluesky', TEXT_DOMAIN),
	},
	{
		id: 'network_instructions.linkedin',
		type: 'textarea',
		label: __('LinkedIn', TEXT_DOMAIN),
	},
	{
		id: 'network_instructions.instagram',
		type: 'textarea',
		label: __('Instagram', TEXT_DOMAIN),
	},
	{
		id: 'network_instructions.tiktok',
		type: 'textarea',
		label: __('TikTok', TEXT_DOMAIN),
	},
	{
		id: 'network_instructions.youtube',
		type: 'textarea',
		label: __('YouTube', TEXT_DOMAIN),
	},
];

const SYSTEM_PROMPT_LABELS: Record<FlatPromptKey, string> = {
	'generate-thread': __('Generate Thread', TEXT_DOMAIN),
	'generate-message': __('Generate Message', TEXT_DOMAIN),
};

const SYSTEM_PROMPT_HELP: Record<FlatPromptKey, string> = {
	'generate-thread': __(
		'Available placeholders: {{platform}}, {{max_messages}}, {{char_limit}}',
		TEXT_DOMAIN
	),
	'generate-message': __(
		'Available placeholders: {{platform}}, {{max_length}}, {{option_count}}',
		TEXT_DOMAIN
	),
};

const STORY_FIELD_LABELS: Record<keyof StoryFieldPrompts, string> = {
	caption: __('Caption', TEXT_DOMAIN),
	overlay_text: __('Overlay Text', TEXT_DOMAIN),
	media_descriptions: __('Suggested Media Descriptions', TEXT_DOMAIN),
};

const STORY_FIELD_HELP: Record<keyof StoryFieldPrompts, string> = {
	caption: __(
		'Instructions for the story caption text (1-3 sentences).',
		TEXT_DOMAIN
	),
	overlay_text: __(
		'Instructions for the on-image overlay text (short, large-type friendly).',
		TEXT_DOMAIN
	),
	media_descriptions: __(
		'Instructions for describing why each suggested image fits the story.',
		TEXT_DOMAIN
	),
};

function StoryPromptsIntro() {
	return (
		<Text size={12} color="#757575">
			{__(
				'Customize the per-field instructions for AI story generation. The preamble, media ID selection, and output format are always hard-coded.',
				TEXT_DOMAIN
			)}
		</Text>
	);
}

export default function SettingsApp() {
	const { defaults, storyFieldDefaults, formatInstructions } = useSelect(
		(sel) => ({
			defaults: sel(settingsStore).getDefaults(),
			storyFieldDefaults: sel(settingsStore).getStoryFieldDefaults(),
			formatInstructions: sel(settingsStore).getFormatInstructions(),
		}),
		[]
	);

	const systemPromptFields = useMemo(
		(): SettingsFieldConfig[] =>
			(Object.keys(SYSTEM_PROMPT_LABELS) as FlatPromptKey[]).map(
				(key) => ({
					id: `system_prompts.${key}`,
					type: 'textarea' as const,
					label: SYSTEM_PROMPT_LABELS[key],
					description: SYSTEM_PROMPT_HELP[key],
					rows: 10,
					placeholder: defaults[key] || undefined,
					annotation: () => (
						<LockedFormatBlock
							instruction={formatInstructions[key]}
						/>
					),
				})
			),
		[defaults, formatInstructions]
	);

	const storyPromptFields = useMemo(
		(): SettingsFieldConfig[] =>
			(
				Object.keys(STORY_FIELD_LABELS) as Array<
					keyof StoryFieldPrompts
				>
			).map((field) => ({
				id: `system_prompts.generate-story.${field}`,
				type: 'textarea' as const,
				label: STORY_FIELD_LABELS[field],
				description: STORY_FIELD_HELP[field],
				rows: 3,
				placeholder: storyFieldDefaults[field] || undefined,
			})),
		[storyFieldDefaults]
	);

	return (
		<SettingsPage
			title={__('Social Package Builder AI Settings', TEXT_DOMAIN)}
			description={__(
				'Configure AI generation defaults, per-network instructions, and system prompt templates.',
				TEXT_DOMAIN
			)}
			textDomain={TEXT_DOMAIN}
			idPrefix="prc-social-builder-settings"
			store={settingsStore}
			saveSettings={saveSettings}
			errorRetryLabel={__(
				'Please try again. If the problem persists, contact support.',
				TEXT_DOMAIN
			)}
			sections={[
				{
					slug: 'editorial-passes',
					title: __('Editorial Passes', TEXT_DOMAIN),
					description: __(
						'Control post-generation editorial rewriting applied to AI suggestions.',
						TEXT_DOMAIN
					),
					fields: EDITORIAL_PASS_FIELDS,
				},
				{
					slug: 'thread-counts',
					title: __('Default Thread Counts', TEXT_DOMAIN),
					description: __(
						'Configure how many messages are generated per platform when creating threads.',
						TEXT_DOMAIN
					),
					fields: THREAD_COUNT_FIELDS,
				},
				{
					slug: 'network-instructions',
					title: __('Per-Network Instructions', TEXT_DOMAIN),
					description: __(
						'Add platform-specific instructions appended to all AI prompts for each network.',
						TEXT_DOMAIN
					),
					fields: NETWORK_INSTRUCTION_FIELDS,
				},
				{
					slug: 'system-prompts',
					title: __('System Prompt Templates', TEXT_DOMAIN),
					description: __(
						'Override the default system prompts for thread and message generation. Leave blank to use the default.',
						TEXT_DOMAIN
					),
					fields: systemPromptFields,
				},
				{
					slug: 'story-prompts',
					title: __('Story Prompt Instructions', TEXT_DOMAIN),
					description: __(
						'Customize per-field instructions for AI story generation. Leave blank to use the default.',
						TEXT_DOMAIN
					),
					intro: () => <StoryPromptsIntro />,
					fields: storyPromptFields,
					footer: () => (
						<LockedFormatBlock
							instruction={formatInstructions['generate-story']}
						/>
					),
				},
			]}
			onLoad={fetchSettings}
		/>
	);
}
