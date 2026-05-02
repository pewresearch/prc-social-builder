import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Icon,
	TextareaControl,
	Notice,
	__experimentalText as Text,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';
import { chevronDown } from '@wordpress/icons';

import { store as settingsStore } from '../store';
import { saveSettings } from '../api';
import type { NetworkInstructions } from '../types';

const NETWORK_LABELS: Record<keyof NetworkInstructions, string> = {
	twitter: __('Twitter / X', 'prc-social-builder'),
	facebook: __('Facebook', 'prc-social-builder'),
	threads: __('Threads', 'prc-social-builder'),
	bluesky: __('Bluesky', 'prc-social-builder'),
	instagram: __('Instagram', 'prc-social-builder'),
	tiktok: __('TikTok', 'prc-social-builder'),
	youtube: __('YouTube', 'prc-social-builder'),
};

export default function NetworkInstructionsSection() {
	const { updateNetworkInstruction } = useDispatch(settingsStore);

	const networkInstructions = useSelect(
		(sel) => sel(settingsStore).getNetworkInstructions(),
		[]
	);

	const [draft, setDraft] =
		useState<NetworkInstructions>(networkInstructions);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const [openPlatform, setOpenPlatform] = useState<
		keyof NetworkInstructions | null
	>(null);

	useEffect(() => setDraft(networkInstructions), [networkInstructions]);

	const updateDraft = (
		platform: keyof NetworkInstructions,
		value: string
	) => {
		setDraft((prev) => ({ ...prev, [platform]: value }));
	};

	const handleSave = () => {
		(Object.keys(draft) as Array<keyof NetworkInstructions>).forEach(
			(platform) => {
				updateNetworkInstruction(platform, draft[platform]);
			}
		);
		setLoading(true);
		saveSettings()
			.then(() => setError(null))
			.catch((e: Error) => setError(e.message))
			.finally(() => setLoading(false));
	};

	const handleClear = () => {
		const empty = Object.fromEntries(
			Object.keys(draft).map((k) => [k, ''])
		) as NetworkInstructions;
		(Object.keys(empty) as Array<keyof NetworkInstructions>).forEach(
			(platform) => {
				updateNetworkInstruction(platform, '');
			}
		);
		setLoading(true);
		saveSettings()
			.then(() => setError(null))
			.catch((e: Error) => setError(e.message))
			.finally(() => setLoading(false));
	};

	const hasContent = Object.values(draft).some((v) => v.trim() !== '');

	return (
		<VStack spacing={0}>
			{(
				Object.keys(NETWORK_LABELS) as Array<keyof NetworkInstructions>
			).map((platform) => {
				const isOpen = openPlatform === platform;
				return (
					<div
						key={platform}
						className="social-builder-settings__sub-section"
					>
						<Button
							className="social-builder-settings__sub-section-trigger"
							onClick={() =>
								setOpenPlatform(isOpen ? null : platform)
							}
							aria-expanded={isOpen}
						>
							<HStack justify="space-between">
								<Text>{NETWORK_LABELS[platform]}</Text>
								<Icon
									className={
										isOpen
											? 'social-builder-settings__sub-section-chevron-up'
											: 'social-builder-settings__sub-section-chevron-down'
									}
									icon={chevronDown}
								/>
							</HStack>
						</Button>
						{isOpen && (
							<div className="social-builder-settings__sub-section-content">
								<TextareaControl
									__nextHasNoMarginBottom
									label={__(
										'Additional instructions',
										'prc-social-builder'
									)}
									help={__(
										'These instructions are appended to all AI prompts when generating content for this network.',
										'prc-social-builder'
									)}
									value={draft[platform]}
									onChange={(value) =>
										updateDraft(platform, value)
									}
									rows={4}
								/>
							</div>
						)}
					</div>
				);
			})}

			{error && (
				<Notice
					status="error"
					isDismissible
					onRemove={() => setError(null)}
				>
					{error}
				</Notice>
			)}

			<HStack
				className="social-builder-settings__form-actions"
				justify="flex-end"
			>
				<Button
					variant="secondary"
					isDestructive={hasContent}
					disabled={!hasContent || loading}
					onClick={handleClear}
				>
					{__('Clear all', 'prc-social-builder')}
				</Button>
				<Button
					variant="primary"
					onClick={handleSave}
					isBusy={loading}
					disabled={loading}
				>
					{__('Save instructions', 'prc-social-builder')}
				</Button>
			</HStack>
		</VStack>
	);
}
