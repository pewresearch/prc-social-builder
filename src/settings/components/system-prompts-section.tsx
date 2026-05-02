import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Icon,
	TextareaControl,
	Notice,
	__experimentalText as Text,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	__experimentalConfirmDialog as ConfirmDialog,
} from '@wordpress/components';
import { chevronDown } from '@wordpress/icons';

import { store as settingsStore } from '../store';
import { saveSettings } from '../api';
import LockedFormatBlock from './locked-format-block';
import type { FlatPromptKey } from '../types';

const ABILITY_LABELS: Record<FlatPromptKey, string> = {
	'generate-thread': __('Generate Thread', 'prc-social-builder'),
	'generate-message': __('Generate Message', 'prc-social-builder'),
};

const ABILITY_PLACEHOLDERS: Record<FlatPromptKey, string> = {
	'generate-thread': __(
		'Available placeholders: {{platform}}, {{max_messages}}, {{char_limit}}',
		'prc-social-builder'
	),
	'generate-message': __(
		'Available placeholders: {{platform}}, {{max_length}}, {{option_count}}',
		'prc-social-builder'
	),
};

export default function SystemPromptsSection() {
	const { updateSystemPrompt, resetSystemPrompt } =
		useDispatch(settingsStore);

	const { systemPrompts, defaults, formatInstructions } = useSelect(
		(sel) => ({
			systemPrompts: sel(settingsStore).getSystemPrompts(),
			defaults: sel(settingsStore).getDefaults(),
			formatInstructions: sel(settingsStore).getFormatInstructions(),
		}),
		[]
	);

	const [draftThread, setDraftThread] = useState(
		systemPrompts['generate-thread']
	);
	const [draftMessage, setDraftMessage] = useState(
		systemPrompts['generate-message']
	);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const [openKey, setOpenKey] = useState<FlatPromptKey | null>(null);
	const [confirmReset, setConfirmReset] = useState<FlatPromptKey | null>(
		null
	);

	useEffect(() => {
		setDraftThread(systemPrompts['generate-thread']);
		setDraftMessage(systemPrompts['generate-message']);
	}, [systemPrompts]);

	const getDraft = (key: FlatPromptKey) =>
		key === 'generate-thread' ? draftThread : draftMessage;
	const setDraft = (key: FlatPromptKey, value: string) =>
		key === 'generate-thread'
			? setDraftThread(value)
			: setDraftMessage(value);

	const isOverridden = (key: FlatPromptKey) => getDraft(key).trim() !== '';
	const getEffectivePrompt = (key: FlatPromptKey) =>
		getDraft(key) || defaults[key] || '';

	const handleSave = (key: FlatPromptKey) => {
		updateSystemPrompt(key, getDraft(key));
		setLoading(true);
		saveSettings()
			.then(() => setError(null))
			.catch((e: Error) => setError(e.message))
			.finally(() => setLoading(false));
	};

	const handleResetConfirm = () => {
		if (!confirmReset) return;
		const key = confirmReset;
		resetSystemPrompt(key);
		setDraft(key, '');
		setLoading(true);
		saveSettings()
			.then(() => setError(null))
			.catch((e: Error) => setError(e.message))
			.finally(() => {
				setLoading(false);
				setConfirmReset(null);
			});
	};

	return (
		<VStack spacing={0}>
			{(Object.keys(ABILITY_LABELS) as FlatPromptKey[]).map((key) => {
				const isOpen = openKey === key;
				return (
					<div
						key={key}
						className="social-builder-settings__sub-section"
					>
						<Button
							className="social-builder-settings__sub-section-trigger"
							onClick={() => setOpenKey(isOpen ? null : key)}
							aria-expanded={isOpen}
						>
							<HStack justify="space-between">
								<Text>{ABILITY_LABELS[key]}</Text>
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
								<VStack spacing={3}>
									{isOverridden(key) && (
										<Notice
											status="warning"
											isDismissible={false}
										>
											{__(
												'Using a custom prompt. Reset to restore the default.',
												'prc-social-builder'
											)}
										</Notice>
									)}
									<TextareaControl
										__nextHasNoMarginBottom
										label={
											isOverridden(key)
												? __(
														'Custom system prompt',
														'prc-social-builder'
													)
												: __(
														'System prompt (default)',
														'prc-social-builder'
													)
										}
										help={ABILITY_PLACEHOLDERS[key]}
										value={getEffectivePrompt(key)}
										onChange={(value) =>
											setDraft(key, value)
										}
										rows={10}
									/>
									{formatInstructions[key] && (
										<LockedFormatBlock
											instruction={
												formatInstructions[key]
											}
										/>
									)}
									{error && (
										<Notice
											status="error"
											isDismissible
											onRemove={() => setError(null)}
										>
											{error}
										</Notice>
									)}
									<HStack justify="flex-end">
										<Button
											variant="secondary"
											isDestructive={isOverridden(key)}
											disabled={
												!isOverridden(key) || loading
											}
											onClick={() => setConfirmReset(key)}
										>
											{__(
												'Reset to Default',
												'prc-social-builder'
											)}
										</Button>
										<Button
											variant="primary"
											onClick={() => handleSave(key)}
											isBusy={loading}
											disabled={loading}
										>
											{__(
												'Save prompt',
												'prc-social-builder'
											)}
										</Button>
									</HStack>
								</VStack>
							</div>
						)}
					</div>
				);
			})}

			{confirmReset && (
				<ConfirmDialog
					onConfirm={handleResetConfirm}
					onCancel={() => setConfirmReset(null)}
					confirmButtonText={__(
						'Reset to default',
						'prc-social-builder'
					)}
					isBusy={loading}
					size="small"
				>
					{sprintf(
						__(
							'You are about to reset the %s prompt to its default. This will save immediately.',
							'prc-social-builder'
						),
						ABILITY_LABELS[confirmReset]
					)}
				</ConfirmDialog>
			)}
		</VStack>
	);
}
