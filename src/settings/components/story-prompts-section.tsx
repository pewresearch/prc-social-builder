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
import type { StoryFieldPrompts } from '../types';

const STORY_FIELD_LABELS: Record<keyof StoryFieldPrompts, string> = {
	caption: __('Caption', 'prc-social-builder'),
	overlay_text: __('Overlay Text', 'prc-social-builder'),
	media_descriptions: __(
		'Suggested Media Descriptions',
		'prc-social-builder'
	),
};

const STORY_FIELD_HELP: Record<keyof StoryFieldPrompts, string> = {
	caption: __(
		'Instructions for the story caption text (1-3 sentences).',
		'prc-social-builder'
	),
	overlay_text: __(
		'Instructions for the on-image overlay text (short, large-type friendly).',
		'prc-social-builder'
	),
	media_descriptions: __(
		'Instructions for describing why each suggested image fits the story.',
		'prc-social-builder'
	),
};

export default function StoryPromptsSection() {
	const { updateStoryFieldPrompt, resetStoryFieldPrompt } =
		useDispatch(settingsStore);

	const { storyPrompts, storyFieldDefaults, formatInstructions } = useSelect(
		(sel) => ({
			storyPrompts: sel(settingsStore).getStoryPrompts(),
			storyFieldDefaults: sel(settingsStore).getStoryFieldDefaults(),
			formatInstructions: sel(settingsStore).getFormatInstructions(),
		}),
		[]
	);

	const [draft, setDraft] = useState<StoryFieldPrompts>(storyPrompts);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState<string | null>(null);
	const [openField, setOpenField] = useState<keyof StoryFieldPrompts | null>(
		null
	);
	const [confirmReset, setConfirmReset] = useState<
		keyof StoryFieldPrompts | null
	>(null);

	useEffect(() => setDraft(storyPrompts), [storyPrompts]);

	const updateDraft = (field: keyof StoryFieldPrompts, value: string) => {
		setDraft((prev) => ({ ...prev, [field]: value }));
	};

	const isFieldOverridden = (field: keyof StoryFieldPrompts) =>
		draft[field].trim() !== '';
	const getEffectiveValue = (field: keyof StoryFieldPrompts) =>
		draft[field] || storyFieldDefaults[field] || '';

	const handleSave = (field: keyof StoryFieldPrompts) => {
		updateStoryFieldPrompt(field, draft[field]);
		setLoading(true);
		saveSettings()
			.then(() => setError(null))
			.catch((e: Error) => setError(e.message))
			.finally(() => setLoading(false));
	};

	const handleResetConfirm = () => {
		if (!confirmReset) return;
		const field = confirmReset;
		resetStoryFieldPrompt(field);
		setDraft((prev) => ({ ...prev, [field]: '' }));
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
			<div className="social-builder-settings__section-intro">
				<Text className="social-builder-settings__accordion-description">
					{__(
						'Customize the per-field instructions for AI story generation. The preamble, media ID selection, and output format are always hard-coded.',
						'prc-social-builder'
					)}
				</Text>
			</div>

			{(
				Object.keys(STORY_FIELD_LABELS) as Array<
					keyof StoryFieldPrompts
				>
			).map((field) => {
				const isOpen = openField === field;
				return (
					<div
						key={field}
						className="social-builder-settings__sub-section"
					>
						<Button
							className="social-builder-settings__sub-section-trigger"
							onClick={() => setOpenField(isOpen ? null : field)}
							aria-expanded={isOpen}
						>
							<HStack justify="space-between">
								<Text>{STORY_FIELD_LABELS[field]}</Text>
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
									{isFieldOverridden(field) && (
										<Notice
											status="warning"
											isDismissible={false}
										>
											{__(
												'Using a custom instruction. Reset to restore the default.',
												'prc-social-builder'
											)}
										</Notice>
									)}
									<TextareaControl
										__nextHasNoMarginBottom
										label={
											isFieldOverridden(field)
												? __(
														'Custom instruction',
														'prc-social-builder'
													)
												: __(
														'Instruction (default)',
														'prc-social-builder'
													)
										}
										help={STORY_FIELD_HELP[field]}
										value={getEffectiveValue(field)}
										onChange={(value) =>
											updateDraft(field, value)
										}
										rows={3}
									/>
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
											isDestructive={isFieldOverridden(
												field
											)}
											disabled={
												!isFieldOverridden(field) ||
												loading
											}
											onClick={() =>
												setConfirmReset(field)
											}
										>
											{__(
												'Reset to Default',
												'prc-social-builder'
											)}
										</Button>
										<Button
											variant="primary"
											onClick={() => handleSave(field)}
											isBusy={loading}
											disabled={loading}
										>
											{__(
												'Save instruction',
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

			<div className="social-builder-settings__section-footer">
				<LockedFormatBlock
					instruction={formatInstructions['generate-story']}
				/>
			</div>

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
							'You are about to reset the %s instruction to its default. This will save immediately.',
							'prc-social-builder'
						),
						STORY_FIELD_LABELS[confirmReset]
					)}
				</ConfirmDialog>
			)}
		</VStack>
	);
}
