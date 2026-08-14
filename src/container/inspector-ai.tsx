import { __ } from '@wordpress/i18n';
import { useState, useCallback, useEffect, useMemo } from '@wordpress/element';
import { PanelBody } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import {
	useAISuggest,
	AISuggestButton,
	AISuggestModal,
	AISuggestionsList,
} from '@prc/components';

import {
	SocialCopyMessage,
	SocialCopyMessageItem,
} from './social-copy-message-item';

import {
	GeneratorChoices,
	GeneratorSettings,
} from './social-copy-generator-footer';

declare const prcSocialBuilderAI: {
	enabled: boolean;
	storyAbilityName: string;
	socialCopyAbilityName: string;
};

interface InspectorAIProps {
	platform: string;
	clientId: string;
	sourcePostId: number;
	includeReportChildren: boolean;
	unselectedReportChildren: string;
	aiAdditionalInstructions: string;
	aiRequestedEdits: string;
	setAttributes: (
		attrs: Partial<{
			aiAdditionalInstructions: string;
			aiRequestedEdits: string;
		}>
	) => void;
	innerBlockCount: number;
}

export function ContainerInspectorAI({
	platform,
	clientId,
	sourcePostId,
	includeReportChildren,
	unselectedReportChildren,
	aiAdditionalInstructions,
	aiRequestedEdits,
	setAttributes,
}: InspectorAIProps) {
	const aiConfig =
		typeof prcSocialBuilderAI !== 'undefined' ? prcSocialBuilderAI : null;
	const postId = sourcePostId;
	const { replaceInnerBlocks } = useDispatch(blockEditorStore);
	const [isModalOpen, setIsModalOpen] = useState(false);
	const [showSettings, setShowSettings] = useState(true);
	const [copyBackup, setCopyBackup] = useState<{
		copy: string;
	} | null>(null);
	const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
	const [includeLastTurn, setIncludeLastTurn] = useState(true);

	const { isLoading, error, result, fetch, reset } = useAISuggest<
		SocialCopyMessage[]
	>({
		abilityName: 'prc-social-builder/generate-social-copy',
		transformResult: (raw) => raw as SocialCopyMessage[],
	});
	const existingMessage = useSelect(
		(select) => {
			const { getBlocks } = select(blockEditorStore);
			const [first] = getBlocks(clientId);
			return {
				copy: first?.attributes?.content ?? ''
			};
		},
		[clientId]
	);
	const copyToRefine = useMemo(() => {
		const generated = result?.[0];
		if (generated && !generated.error) {
			return { copy: generated.copy };
		}
		// Prefer backup after a failed regeneration (item-level or hook-level).
		if (copyBackup?.copy) {
			return copyBackup;
		}
		if (existingMessage.copy) {
			return existingMessage;
		}
		return null;
	}, [result, existingMessage, copyBackup]);

	// Pre-select all messages when results arrive.
	useEffect(() => {
		if (result) {
			setSelectedIds(new Set(result.map((msg) => msg.platform)));
		}
	}, [result]);

	const buildRequest = useCallback(() => {
		const copyItem: {
			platform: string;
			additionalInstructions?: string;
			previousOutput?: { copy: string; };
			requestedEdits?: string;
		} = {
			platform,
			additionalInstructions: aiAdditionalInstructions || undefined,
			requestedEdits: aiRequestedEdits || undefined,
		};
		if (includeLastTurn && copyToRefine?.copy) {
			copyItem.previousOutput = {
				copy: copyToRefine.copy,
			};
		}
		return {
			request: {
				isDefaultList: false,
				content: [
					{
						contentType: 'wp-post',
						postId,
						includeReportChildren,
						unselectedReportChildren,
					},
				],
				copyList: [copyItem],
			},
			backup: copyItem.previousOutput
				? {
						copy: copyItem.previousOutput.copy,
					}
				: null,
		};
	}, [
		platform,
		aiAdditionalInstructions,
		aiRequestedEdits,
		postId,
		includeReportChildren,
		unselectedReportChildren,
		includeLastTurn,
		copyToRefine,
	]);

	const handleGenerate = useCallback(() => {
		setShowSettings(false);
		reset();
		const generator = buildRequest();
		setCopyBackup(generator.backup);
		fetch(generator.request);
	}, [fetch, buildRequest]);

	const openModal = () => {
		setIsModalOpen(true);
		setShowSettings(true);
	};

	const handleClose = useCallback(() => {
		setShowSettings(true);
		setCopyBackup(null);
		setIsModalOpen(false);
		reset();
	}, [reset]);

	const handleApply = useCallback(() => {
		if (!result) {
			return;
		}
		const selected = result.filter((msg) => selectedIds.has(msg.platform));
		const newBlocks = selected.map((msg) =>
			createBlock('prc-social/message', {
				content: msg.copy,
			})
		);
		replaceInnerBlocks(clientId, newBlocks, false);
		handleClose();
	}, [result, selectedIds, clientId, replaceInnerBlocks, reset, handleClose]);

	const handleShowSettings = useCallback(() => {
		setShowSettings(true);
	}, [setShowSettings]);

	const handleShowResults = useCallback(() => {
		setShowSettings(false);
	}, [setShowSettings]);

	if (!aiConfig?.enabled) {
		return null;
	}

	return (
		<PanelBody title={__('AI Social Copy Generator', 'prc-social-builder')}>
			<AISuggestButton
				text={__('Generate Social Copy', 'prc-social-builder')}
				label={__('Generate Social Copy with AI', 'prc-social-builder')}
				onClick={openModal}
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
				title={__('AI Social Copy Generator', 'prc-social-builder')}
				isOpen={isModalOpen}
				onClose={handleClose}
				isLoading={isLoading}
				loadingMessage={__('Generating copy…', 'prc-social-builder')}
				error={error}
				footer={
					showSettings ? (
						<GeneratorSettings
							copyToRefine={copyToRefine}
							includeLastTurn={includeLastTurn}
							onIncludeLastTurnChange={setIncludeLastTurn}
							aiRequestedEdits={aiRequestedEdits ?? ''}
							aiAdditionalInstructions={aiAdditionalInstructions}
							hasResult={Boolean(result)}
							onRequestedEditsChange={(val) =>
								setAttributes({ aiRequestedEdits: val })
							}
							onAdditionalInstructionsChange={(val) =>
								setAttributes({ aiAdditionalInstructions: val })
							}
							onGenerate={handleGenerate}
							onShowResults={handleShowResults}
						/>
					) : (
						<GeneratorChoices
							hasError={Boolean(error)}
							canApply={selectedIds.size > 0}
							onApply={handleApply}
							onShowSettings={handleShowSettings}
						/>
					)
				}
			>
				{result && !showSettings && (
					<AISuggestionsList<SocialCopyMessage>
						suggestions={result}
						selectedIds={selectedIds}
						// onToggle={handleToggle}
						getId={(msg) => msg.platform}
						renderItem={(msg) => (
							<SocialCopyMessageItem message={msg} />
						)}
						emptyMessage={__(
							'No messages generated.',
							'prc-social-builder'
						)}
						inertList={true}
					/>
				)}
			</AISuggestModal>
		</PanelBody>
	);
}
