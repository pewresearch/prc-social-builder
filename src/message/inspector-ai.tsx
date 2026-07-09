import { __ } from '@wordpress/i18n';
import { useState, useCallback } from '@wordpress/element';
import { PanelBody, Notice, TextareaControl } from '@wordpress/components';
import {
	useAISuggest,
	AISuggestButton,
	AILoadingIndicator,
	AISuggestionPreview,
	AINumberCheckBadge,
} from '@prc/components';

declare const prcSocialBuilderAI: {
	enabled: boolean;
	threadAbilityName: string;
	messageAbilityName: string;
	storyAbilityName: string;
};

interface MessageOption {
	content: string;
	linkUrl?: string;
	numberCheck?: {
		valid: boolean;
		flagged: string[];
	};
}

interface InspectorAIProps {
	platform: string;
	postId: number;
	aiAdditionalInstructions: string;
	setAttributes: (attrs: { aiAdditionalInstructions: string }) => void;
	onApply: (option: MessageOption) => void;
}

interface OptionCardProps {
	option: MessageOption;
	index: number;
	isSelected: boolean;
	onSelect: () => void;
}

function OptionCard({ option, index, isSelected, onSelect }: OptionCardProps) {
	return (
		<div
			role="button"
			tabIndex={0}
			aria-pressed={isSelected}
			onClick={onSelect}
			onKeyDown={(event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					onSelect();
				}
			}}
			style={{
				padding: '8px',
				marginBottom: '6px',
				borderRadius: '4px',
				border: `2px solid ${
					isSelected
						? 'var(--wp-admin-theme-color, #3858e9)'
						: '#e0e0e0'
				}`,
				cursor: 'pointer',
				fontSize: '13px',
				lineHeight: '1.5',
				background: isSelected
					? 'rgba(56, 88, 233, 0.04)'
					: 'transparent',
			}}
		>
			<span
				style={{
					display: 'block',
					fontWeight: 600,
					fontSize: '11px',
					color: '#757575',
					marginBottom: '4px',
					textTransform: 'uppercase',
					letterSpacing: '0.05em',
				}}
			>
				{__('Option', 'prc-social-builder')} {index + 1}{' '}
				<AINumberCheckBadge numberCheck={option.numberCheck} />
			</span>
			{option.content}
		</div>
	);
}

export function MessageInspectorAI({
	platform,
	postId,
	aiAdditionalInstructions,
	setAttributes,
	onApply,
}: InspectorAIProps) {
	const aiConfig =
		typeof prcSocialBuilderAI !== 'undefined' ? prcSocialBuilderAI : null;

	const [selectedIndex, setSelectedIndex] = useState<number>(0);

	const { isLoading, error, result, fetch, reset, dismissError } =
		useAISuggest<MessageOption[]>({
			abilityName: aiConfig?.messageAbilityName ?? '',
			transformResult: (raw) => raw.options as MessageOption[],
		});

	const handleFetch = useCallback(() => {
		setSelectedIndex(0);
		fetch({
			postId,
			platform,
			context: aiAdditionalInstructions || undefined,
		});
	}, [fetch, postId, platform, aiAdditionalInstructions]);

	const handleApply = useCallback(() => {
		if (!result || !result[selectedIndex]) {
			return;
		}
		onApply(result[selectedIndex]);
		reset();
	}, [result, selectedIndex, onApply, reset]);

	if (!aiConfig?.enabled) {
		return null;
	}

	return (
		<PanelBody title={__('AI Message Suggestions', 'prc-social-builder')}>
			{!result && !isLoading && (
				<>
					<TextareaControl
						__nextHasNoMarginBottom
						label={__(
							'Additional instructions',
							'prc-social-builder'
						)}
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
						text={__('Suggest Message', 'prc-social-builder')}
						label={__(
							'Suggest message with AI',
							'prc-social-builder'
						)}
						onClick={handleFetch}
						isLoading={isLoading}
						disabled={postId === 0}
					/>
				</>
			)}

			{postId === 0 && !result && !isLoading && (
				<p
					style={{
						fontSize: '12px',
						color: '#757575',
						marginTop: '8px',
					}}
				>
					{__(
						'Select a source post on the thread to enable AI suggestions.',
						'prc-social-builder'
					)}
				</p>
			)}

			{isLoading && (
				<AILoadingIndicator
					message={__('Generating messages…', 'prc-social-builder')}
				/>
			)}

			{error && (
				<Notice status="warning" isDismissible onDismiss={dismissError}>
					{error}
				</Notice>
			)}

			{result && !isLoading && (
				<AISuggestionPreview
					headerLabel={__('Message Options', 'prc-social-builder')}
					applyLabel={__('Apply', 'prc-social-builder')}
					dismissLabel={__('Dismiss', 'prc-social-builder')}
					regenerateLabel={__('Regenerate', 'prc-social-builder')}
					onApply={handleApply}
					onDismiss={reset}
					onRegenerate={handleFetch}
				>
					{result.map((option, index) => (
						<OptionCard
							key={index}
							option={option}
							index={index}
							isSelected={selectedIndex === index}
							onSelect={() => setSelectedIndex(index)}
						/>
					))}
				</AISuggestionPreview>
			)}
		</PanelBody>
	);
}
