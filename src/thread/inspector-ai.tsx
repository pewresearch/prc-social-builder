import { __ } from '@wordpress/i18n';
import { useState, useCallback, useEffect, useRef } from '@wordpress/element';
import {
	PanelBody,
	Button,
	RangeControl,
	TextareaControl,
} from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import {
	useAISuggest,
	AISuggestButton,
	AISuggestModal,
	AISuggestionsList,
} from '@prc/components';

declare const prcSocialBuilderAI: {
	enabled: boolean;
	threadAbilityName: string;
	messageAbilityName: string;
	storyAbilityName: string;
};

interface ThreadMessage {
	content: string;
	position: number;
	linkUrl?: string;
}

interface InspectorAIProps {
	platform: string;
	clientId: string;
	sourcePostId: number;
	aiAdditionalInstructions: string;
	setAttributes: (attrs: { aiAdditionalInstructions: string }) => void;
	innerBlockCount: number;
}

export function ThreadInspectorAI({
	platform,
	clientId,
	sourcePostId,
	aiAdditionalInstructions,
	setAttributes,
	innerBlockCount,
}: InspectorAIProps) {
	const aiConfig =
		typeof prcSocialBuilderAI !== 'undefined' ? prcSocialBuilderAI : null;

	const postId = sourcePostId;

	const { replaceInnerBlocks } = useDispatch(blockEditorStore);

	const [isModalOpen, setIsModalOpen] = useState(false);
	const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
	const isFacebook = platform === 'facebook';
	const previousPlatformRef = useRef(platform);

	const [messageCount, setMessageCount] = useState(() => {
		if (platform === 'facebook') {
			return 1;
		}
		return innerBlockCount > 3 ? Math.max(4, innerBlockCount) : 4;
	});

	const { isLoading, error, result, fetch, reset, dismissError } =
		useAISuggest<ThreadMessage[]>({
			abilityName: aiConfig?.threadAbilityName ?? '',
			transformResult: (raw) => raw.messages as ThreadMessage[],
		});

	// Pre-select all messages when results arrive.
	useEffect(() => {
		if (result) {
			setSelectedIds(new Set(result.map((msg) => msg.position)));
		}
	}, [result]);

	// Facebook does not support threads — always a single message for AI generation.
	useEffect(() => {
		if (isFacebook) {
			setMessageCount(1);
			previousPlatformRef.current = platform;
			return;
		}
		if (previousPlatformRef.current === 'facebook') {
			setMessageCount(
				innerBlockCount > 3 ? Math.max(4, innerBlockCount) : 4
			);
		}
		previousPlatformRef.current = platform;
	}, [isFacebook, platform, innerBlockCount]);

	// Keep slider aligned with inner block count when there are more than three messages.
	useEffect(() => {
		if (isFacebook) {
			return;
		}
		if (innerBlockCount > 3) {
			setMessageCount(innerBlockCount);
		}
	}, [innerBlockCount, isFacebook]);

	const handleGenerate = useCallback(() => {
		setIsModalOpen(true);
		fetch({
			postId,
			platform,
			messageCount,
			additionalInstructions: aiAdditionalInstructions || undefined,
		});
	}, [fetch, postId, platform, messageCount, aiAdditionalInstructions]);

	const handleToggle = useCallback((id: string | number) => {
		setSelectedIds((prev) => {
			const next = new Set(prev);
			if (next.has(id as number)) {
				next.delete(id as number);
			} else {
				next.add(id as number);
			}
			return next;
		});
	}, []);

	const handleApply = useCallback(() => {
		if (!result) {
			return;
		}
		const selected = result.filter((msg) => selectedIds.has(msg.position));
		const newBlocks = selected.map((msg, i) =>
			createBlock('prc-social/message', {
				content: msg.content,
				position: i + 1,
				linkUrl: msg.linkUrl ?? '',
			})
		);
		replaceInnerBlocks(clientId, newBlocks, false);
		setIsModalOpen(false);
		reset();
	}, [result, selectedIds, clientId, replaceInnerBlocks, reset]);

	const handleClose = useCallback(() => {
		setIsModalOpen(false);
		reset();
	}, [reset]);

	const handleRegenerate = useCallback(() => {
		reset();
		fetch({
			postId,
			platform,
			messageCount,
			additionalInstructions: aiAdditionalInstructions || undefined,
		});
	}, [
		reset,
		fetch,
		postId,
		platform,
		messageCount,
		aiAdditionalInstructions,
	]);

	if (!aiConfig?.enabled) {
		return null;
	}

	return (
		<PanelBody title={__('AI Thread Generation', 'prc-social-builder')}>
			<RangeControl
				__nextHasNoMarginBottom
				label={__('Number of messages', 'prc-social-builder')}
				value={messageCount}
				onChange={(val) => setMessageCount(val ?? (isFacebook ? 1 : 4))}
				min={isFacebook ? 1 : 2}
				max={isFacebook ? 1 : 10}
				disabled={isFacebook}
				help={
					isFacebook
						? __(
								'Facebook does not support threaded posts, so only one message can be generated.',
								'prc-social-builder'
							)
						: undefined
				}
			/>
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
				text={__('Generate Thread', 'prc-social-builder')}
				label={__('Generate thread with AI', 'prc-social-builder')}
				onClick={handleGenerate}
				isLoading={isLoading}
				disabled={postId === 0}
			/>

			{postId === 0 && (
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
				title={__('AI Thread Suggestions', 'prc-social-builder')}
				isOpen={isModalOpen}
				onClose={handleClose}
				isLoading={isLoading}
				loadingMessage={__('Generating thread…', 'prc-social-builder')}
				error={error}
				onDismissError={dismissError}
				footer={
					result && (
						<>
							<Button
								variant="primary"
								onClick={handleApply}
								disabled={selectedIds.size === 0}
							>
								{__('Apply Selected', 'prc-social-builder')}
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
					<AISuggestionsList<ThreadMessage>
						suggestions={result}
						selectedIds={selectedIds}
						onToggle={handleToggle}
						getId={(msg) => msg.position}
						renderItem={(msg) => (
							<span
								style={{ fontSize: '13px', lineHeight: '1.5' }}
							>
								<strong
									style={{
										display: 'block',
										marginBottom: '2px',
									}}
								>
									{__('Message', 'prc-social-builder')}{' '}
									{msg.position}
								</strong>
								{msg.content}
							</span>
						)}
						emptyMessage={__(
							'No messages generated.',
							'prc-social-builder'
						)}
					/>
				)}
			</AISuggestModal>
		</PanelBody>
	);
}
