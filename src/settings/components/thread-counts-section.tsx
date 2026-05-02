import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import {
	Button,
	TextControl,
	Notice,
	__experimentalText as Text,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
} from '@wordpress/components';

import { store as settingsStore } from '../store';
import { saveSettings } from '../api';
import type { ThreadCounts } from '../types';

const THREAD_PLATFORM_LABELS: Record<keyof ThreadCounts, string> = {
	twitter: __('Twitter / X', 'prc-social-builder'),
	facebook: __('Facebook', 'prc-social-builder'),
	threads: __('Threads', 'prc-social-builder'),
	bluesky: __('Bluesky', 'prc-social-builder'),
};

export default function ThreadCountsSection() {
	const { updateThreadCount } = useDispatch(settingsStore);

	const threadCounts = useSelect(
		(sel) => sel(settingsStore).getThreadCounts(),
		[]
	);

	const [draft, setDraft] = useState<ThreadCounts>(threadCounts);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState<string | null>(null);

	useEffect(() => setDraft(threadCounts), [threadCounts]);

	const updateDraft = (platform: keyof ThreadCounts, value: number) => {
		setDraft((prev) => ({ ...prev, [platform]: value }));
	};

	const handleSave = () => {
		(Object.keys(draft) as Array<keyof ThreadCounts>).forEach(
			(platform) => {
				updateThreadCount(platform, draft[platform]);
			}
		);
		setLoading(true);
		saveSettings()
			.then(() => setError(null))
			.catch((e: Error) => setError(e.message))
			.finally(() => setLoading(false));
	};

	return (
		<VStack spacing={4}>
			<Text className="social-builder-settings__accordion-description">
				{__(
					'Set the default number of messages to generate when creating a thread. Editors can adjust this per-block.',
					'prc-social-builder'
				)}
			</Text>

			{(
				Object.keys(THREAD_PLATFORM_LABELS) as Array<keyof ThreadCounts>
			).map((platform) => (
				<div key={platform}>
					{platform === 'facebook' ? (
						<VStack spacing={1}>
							<Text>{THREAD_PLATFORM_LABELS[platform]}</Text>
							<Text className="social-builder-settings__facebook-note">
								{__(
									'Facebook does not support threaded posts. Always generates 1 post.',
									'prc-social-builder'
								)}
							</Text>
						</VStack>
					) : (
						<TextControl
							__nextHasNoMarginBottom
							className="social-builder-settings__thread-input"
							label={THREAD_PLATFORM_LABELS[platform]}
							type="number"
							min={2}
							max={10}
							value={draft[platform]}
							onChange={(value) =>
								updateDraft(
									platform,
									Math.max(
										2,
										Math.min(10, parseInt(value, 10) || 2)
									)
								)
							}
						/>
					)}
				</div>
			))}

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
					variant="primary"
					onClick={handleSave}
					isBusy={loading}
					disabled={loading}
				>
					{__('Save thread counts', 'prc-social-builder')}
				</Button>
			</HStack>
		</VStack>
	);
}
