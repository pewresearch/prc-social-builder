import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
import { PanelBody, Button, TextareaControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useAISuggest, AISuggestButton, AISuggestModal } from '@prc/components';

declare const prcSocialBuilderAI: {
	enabled: boolean;
	threadAbilityName: string;
	messageAbilityName: string;
	storyAbilityName: string;
};

interface StoryResult {
	caption: string;
	overlayText: string;
	suggestedMediaIds: number[];
	suggestedMediaDescriptions: string[];
}

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

	const handleGenerate = useCallback(() => {
		setIsModalOpen(true);
		setSelectedMediaIndex(null);
		fetch({
			postId: sourcePostId,
			platform,
			additionalInstructions: aiAdditionalInstructions || undefined,
		});
	}, [fetch, sourcePostId, platform, aiAdditionalInstructions]);

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
		reset();
		setSelectedMediaIndex(null);
		fetch({
			postId: sourcePostId,
			platform,
			additionalInstructions: aiAdditionalInstructions || undefined,
		});
	}, [reset, fetch, sourcePostId, platform, aiAdditionalInstructions]);

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
							<Button variant="primary" onClick={handleApply}>
								{__('Apply', 'prc-social-builder')}
							</Button>
							<Button
								variant="tertiary"
								onClick={handleRegenerate}
							>
								{__('Regenerate', 'prc-social-builder')}
							</Button>
						</>
					)
				}
			>
				{result && (
					<div
						style={{
							display: 'flex',
							flexDirection: 'column',
							gap: '16px',
						}}
					>
						<div>
							<strong
								style={{
									display: 'block',
									fontSize: '11px',
									textTransform: 'uppercase',
									letterSpacing: '0.05em',
									color: '#757575',
									marginBottom: '4px',
								}}
							>
								{__('Caption', 'prc-social-builder')}
							</strong>
							<p
								style={{
									margin: 0,
									fontSize: '13px',
									lineHeight: '1.5',
								}}
							>
								{result.caption}
							</p>
						</div>

						<div>
							<strong
								style={{
									display: 'block',
									fontSize: '11px',
									textTransform: 'uppercase',
									letterSpacing: '0.05em',
									color: '#757575',
									marginBottom: '4px',
								}}
							>
								{__('Overlay Text', 'prc-social-builder')}
							</strong>
							<p
								style={{
									margin: 0,
									fontSize: '13px',
									lineHeight: '1.5',
								}}
							>
								{result.overlayText}
							</p>
						</div>

						{result.suggestedMediaIds?.length > 0 && (
							<div>
								<strong
									style={{
										display: 'block',
										fontSize: '11px',
										textTransform: 'uppercase',
										letterSpacing: '0.05em',
										color: '#757575',
										marginBottom: '8px',
									}}
								>
									{__(
										'Suggested Media (click to select)',
										'prc-social-builder'
									)}
								</strong>
								<div
									style={{
										display: 'flex',
										flexDirection: 'column',
										gap: '8px',
									}}
								>
									{result.suggestedMediaIds.map(
										(id, index) => {
											const media = mediaRecords[
												index
											] as MediaRecord | null;
											const isSelected =
												selectedMediaIndex === index;
											return (
												<div
													key={id}
													onClick={() =>
														setSelectedMediaIndex(
															isSelected
																? null
																: index
														)
													}
													style={{
														display: 'flex',
														alignItems:
															'flex-start',
														gap: '8px',
														padding: '8px',
														borderRadius: '4px',
														border: `2px solid ${
															isSelected
																? 'var(--wp-admin-theme-color, #3858e9)'
																: '#e0e0e0'
														}`,
														cursor: 'pointer',
														background: isSelected
															? 'rgba(56, 88, 233, 0.04)'
															: 'transparent',
													}}
												>
													{media?.source_url && (
														<img
															src={
																media.source_url
															}
															alt={
																media.alt_text ??
																''
															}
															style={{
																width: '48px',
																height: '48px',
																objectFit:
																	'cover',
																borderRadius:
																	'4px',
																flexShrink: 0,
															}}
														/>
													)}
													<span
														style={{
															fontSize: '12px',
															lineHeight: '1.4',
															color: '#1e1e1e',
														}}
													>
														{result
															.suggestedMediaDescriptions[
															index
														] ||
															`Attachment #${id}`}
													</span>
												</div>
											);
										}
									)}
								</div>
								<p
									style={{
										fontSize: '11px',
										color: '#757575',
										margin: '6px 0 0',
									}}
								>
									{__(
										'Select a media item to apply it to the story, or leave unselected to keep existing media.',
										'prc-social-builder'
									)}
								</p>
							</div>
						)}
					</div>
				)}
			</AISuggestModal>
		</PanelBody>
	);
}
