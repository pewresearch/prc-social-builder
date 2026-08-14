import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
import {
	PanelBody,
	Button,
	TextareaControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useAISuggest, AISuggestButton, AISuggestModal } from '@prc/components';

import { StoryResult, StorySuggestionPreview } from './suggestion-preview';

declare const prcSocialBuilderAI: {
	enabled: boolean;
	storyAbilityName: string;
	socialCopyAbilityName: string;
};

interface MediaRecord {
	id: number;
	source_url: string;
	alt_text?: string;
}

interface InspectorAIProps {
	platform: string;
	sourcePostId: number;
	aiAdditionalInstructions: string;
	setAttributes: (
		attrs: Partial<{
			caption: string;
			overlayText: string;
			mediaId: number;
			mediaUrl: string;
			mediaType: string;
			aiAdditionalInstructions: string;
		}>
	) => void;
}

export function StoryInspectorAI({
	platform,
	sourcePostId,
	aiAdditionalInstructions,
	setAttributes,
}: InspectorAIProps) {
	const aiConfig =
		typeof prcSocialBuilderAI !== 'undefined' ? prcSocialBuilderAI : null;

	const [isModalOpen, setIsModalOpen] = useState(false);
	const [selectedMediaIndex, setSelectedMediaIndex] = useState<number | null>(
		null
	);
	const [includeLastTurn, setIncludeLastTurn] = useState(true);

	const { isLoading, error, result, fetch, reset, dismissError } =
		useAISuggest<StoryResult>({
			abilityName: aiConfig?.storyAbilityName ?? '',
			transformResult: (raw) => raw as StoryResult,
		});

	// Resolve attachment URLs for suggested media IDs.
	const mediaRecords = useSelect(
		(select) => {
			if (!result?.suggestedMediaIds?.length) {
				return [];
			}
			return result.suggestedMediaIds.map((id) => {
				const media = (
					select(coreStore) as {
						getMedia: (
							id: number,
							args?: object
						) => MediaRecord | undefined;
					}
				).getMedia(id, { context: 'view' });
				return media ?? null;
			});
		},
		[result?.suggestedMediaIds]
	);

	const buildRequest = useCallback(
		(previousOutput?: { caption: string; overlayText: string }) => {
			const request: {
				postId: number;
				platform: string;
				additionalInstructions?: string;
				previousOutput?: { caption: string; overlayText: string };
			} = {
				postId: sourcePostId,
				platform,
				additionalInstructions: aiAdditionalInstructions || undefined,
			};
			if (previousOutput) {
				request.previousOutput = previousOutput;
			}
			return request;
		},
		[sourcePostId, platform, aiAdditionalInstructions]
	);

	const handleGenerate = useCallback(() => {
		setIsModalOpen(true);
		setSelectedMediaIndex(null);
		fetch(buildRequest());
	}, [fetch, buildRequest]);

	const handleApply = useCallback(() => {
		if (!result) {
			return;
		}

		const attrs: Parameters<typeof setAttributes>[0] = {
			caption: result.caption,
			overlayText: result.overlayText,
		};

		if (
			selectedMediaIndex !== null &&
			mediaRecords[selectedMediaIndex]?.source_url
		) {
			const media = mediaRecords[selectedMediaIndex] as MediaRecord;
			attrs.mediaId = media.id;
			attrs.mediaUrl = media.source_url;
			attrs.mediaType = 'image';
		}

		setAttributes(attrs);
		setIsModalOpen(false);
		reset();
	}, [result, selectedMediaIndex, mediaRecords, setAttributes, reset]);

	const handleClose = useCallback(() => {
		setIsModalOpen(false);
		reset();
	}, [reset]);

	const handleRegenerate = useCallback(() => {
		const previous =
			includeLastTurn && result
				? {
						caption: result.caption,
						overlayText: result.overlayText,
					}
				: undefined;
		reset();
		setSelectedMediaIndex(null);
		fetch(buildRequest(previous));
	}, [includeLastTurn, result, reset, fetch, buildRequest]);

	if (!aiConfig?.enabled) {
		return null;
	}

	return (
		<PanelBody title={__('AI Story Generation', 'prc-social-builder')}>
			<TextareaControl
				__nextHasNoMarginBottom
				label={__('Additional instructions', 'prc-social-builder')}
				help={__(
					'Optional guidance for the AI (e.g. tone, focus, angle).',
					'prc-social-builder'
				)}
				value={aiAdditionalInstructions}
				onChange={(val) =>
					setAttributes({ aiAdditionalInstructions: val })
				}
				rows={3}
			/>
			<AISuggestButton
				text={__('Generate Story', 'prc-social-builder')}
				label={__('Generate story with AI', 'prc-social-builder')}
				onClick={handleGenerate}
				isLoading={isLoading}
				disabled={sourcePostId === 0}
			/>
			{sourcePostId === 0 && (
				<p
					style={{
						fontSize: '12px',
						color: '#757575',
						marginTop: '8px',
					}}
				>
					{__(
						'Select a source post above to enable AI generation.',
						'prc-social-builder'
					)}
				</p>
			)}

			<AISuggestModal
				title={__('AI Story Suggestions', 'prc-social-builder')}
				isOpen={isModalOpen}
				onClose={handleClose}
				isLoading={isLoading}
				loadingMessage={__('Generating story…', 'prc-social-builder')}
				error={error}
				onDismissError={dismissError}
				footer={
					result && (
						<>
							<TextareaControl
								__nextHasNoMarginBottom
								label={__(
									'Additional instructions',
									'prc-social-builder'
								)}
								help={__(
									'Optional guidance for the next regenerate (e.g. make it shorter).',
									'prc-social-builder'
								)}
								value={aiAdditionalInstructions}
								onChange={(val) =>
									setAttributes({
										aiAdditionalInstructions: val,
									})
								}
								rows={3}
							/>
							<div
								style={{
									display: 'flex',
									gap: '8px',
									flexWrap: 'wrap',
									marginTop: '12px',
								}}
							>
								<Button variant="primary" onClick={handleApply}>
									{__('Apply', 'prc-social-builder')}
								</Button>
								<Button
									variant="tertiary"
									onClick={handleRegenerate}
								>
									{__('Regenerate', 'prc-social-builder')}
								</Button>
							</div>
							<div style={{ marginTop: '8px' }}>
								<ToggleControl
									__nextHasNoMarginBottom
									label={__(
										'Include last turn',
										'prc-social-builder'
									)}
									help={__(
										'Send the previous generation as context so additional instructions can refine it.',
										'prc-social-builder'
									)}
									checked={includeLastTurn}
									onChange={setIncludeLastTurn}
								/>
							</div>
						</>
					)
				}
			>
				{result && (
					<StorySuggestionPreview
						result={result}
						mediaRecords={mediaRecords}
						selectedMediaIndex={selectedMediaIndex}
						onSelectMedia={setSelectedMediaIndex}
					/>
				)}
			</AISuggestModal>
		</PanelBody>
	);
}
