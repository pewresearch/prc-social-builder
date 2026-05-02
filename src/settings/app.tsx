import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Spinner,
	Notice,
	__experimentalVStack as VStack,
	__experimentalHeading as Heading,
	__experimentalText as Text,
} from '@wordpress/components';

import './style.scss';
import './store';
import { fetchSettings } from './api';
import SettingsAccordion from './components/settings-accordion';
import ThreadCountsSection from './components/thread-counts-section';
import NetworkInstructionsSection from './components/network-instructions-section';
import SystemPromptsSection from './components/system-prompts-section';
import StoryPromptsSection from './components/story-prompts-section';
import type { SettingsAccordionItem } from './types';

const SETTINGS_SECTIONS: SettingsAccordionItem[] = [
	{
		title: __('Default Thread Counts', 'prc-social-builder'),
		description: __(
			'Configure how many messages are generated per platform when creating threads.',
			'prc-social-builder'
		),
		slug: 'thread-counts',
	},
	{
		title: __('Per-Network Instructions', 'prc-social-builder'),
		description: __(
			'Add platform-specific instructions appended to all AI prompts for each network.',
			'prc-social-builder'
		),
		slug: 'network-instructions',
	},
	{
		title: __('System Prompt Templates', 'prc-social-builder'),
		description: __(
			'Override the default system prompts for thread and message generation.',
			'prc-social-builder'
		),
		slug: 'system-prompts',
	},
	{
		title: __('Story Prompt Instructions', 'prc-social-builder'),
		description: __(
			'Customize per-field instructions for AI story generation.',
			'prc-social-builder'
		),
		slug: 'story-prompts',
	},
];

function getSectionComponent(slug: string) {
	switch (slug) {
		case 'thread-counts':
			return <ThreadCountsSection />;
		case 'network-instructions':
			return <NetworkInstructionsSection />;
		case 'system-prompts':
			return <SystemPromptsSection />;
		case 'story-prompts':
			return <StoryPromptsSection />;
		default:
			return null;
	}
}

export default function SettingsApp() {
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState<string | null>(null);

	useEffect(() => {
		fetchSettings()
			.then(() => setError(null))
			.catch((e: Error) => setError(e.message))
			.finally(() => setLoading(false));
	}, []);

	return (
		<div className="social-builder-settings">
			{error && (
				<Notice status="error" isDismissible={false}>
					<VStack spacing={2}>
						<span>
							{__(
								'Error loading settings:',
								'prc-social-builder'
							)}{' '}
							{error}
						</span>
						<span>
							{__(
								'Please try again. If the problem persists, contact support.',
								'prc-social-builder'
							)}
						</span>
					</VStack>
				</Notice>
			)}
			<VStack spacing={2} className="social-builder-settings__header">
				<Heading level={1}>
					{__(
						'Social Package Builder AI Settings',
						'prc-social-builder'
					)}
				</Heading>
				<Text className="social-builder-settings__header-description">
					{__(
						'Configure AI generation defaults, per-network instructions, and system prompt templates.',
						'prc-social-builder'
					)}
				</Text>
			</VStack>
			{loading ? (
				<div className="social-builder-settings__loading">
					<Spinner />
				</div>
			) : (
				!error && (
					<VStack
						spacing={4}
						className="social-builder-settings__content"
					>
						{/* eslint-disable jsx-a11y/no-redundant-roles */}
						<ul
							className="social-builder-settings__list"
							role="list"
						>
							{SETTINGS_SECTIONS.map((section) => {
								const contentId = `social-builder-settings-${section.slug}`;
								const headingId = `social-builder-settings-${section.slug}-heading`;
								const descriptionId = `social-builder-settings-${section.slug}-description`;

								return (
									<li
										key={section.slug}
										className="social-builder-settings__list-item"
									>
										<SettingsAccordion
											title={section.title}
											description={section.description}
											contentId={contentId}
											headingId={headingId}
											descriptionId={descriptionId}
										>
											{getSectionComponent(section.slug)}
										</SettingsAccordion>
									</li>
								);
							})}
						</ul>
						{/* eslint-enable jsx-a11y/no-redundant-roles */}
					</VStack>
				)
			)}
		</div>
	);
}
