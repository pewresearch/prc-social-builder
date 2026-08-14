import { __ } from '@wordpress/i18n';
import { Button, TextareaControl, ToggleControl } from '@wordpress/components';
export interface CopyToRefine {
	copy: string;
}
interface GeneratorChoicesProps {
	hasError: boolean;
	canApply: boolean;
	onApply: () => void;
	onShowSettings: () => void;
}

export function GeneratorChoices({
	hasError,
	canApply,
	onApply,
	onShowSettings,
}: GeneratorChoicesProps) {
	return (
		<div
			style={{
				display: 'flex',
				gap: '8px',
				flexWrap: 'wrap',
				marginTop: '12px',
			}}
		>
			{!hasError && (
				<Button
					variant="primary"
					onClick={onApply}
					disabled={!canApply}
				>
					{__('Apply Social Copy', 'prc-social-builder')}
				</Button>
			)}
			<Button variant="tertiary" onClick={onShowSettings}>
				{__('Regenerate', 'prc-social-builder')}
			</Button>
		</div>
	);
}
interface GeneratorSettingsProps {
	copyToRefine: CopyToRefine | null;
	includeLastTurn: boolean;
	onIncludeLastTurnChange: (value: boolean) => void;
	aiRequestedEdits: string;
	aiAdditionalInstructions: string;
	hasResult: boolean;
	onRequestedEditsChange: (value: string) => void;
	onAdditionalInstructionsChange: (value: string) => void;
	onGenerate: () => void;
	onShowResults: () => void;
}
export function GeneratorSettings({
	copyToRefine,
	includeLastTurn,
	onIncludeLastTurnChange,
	aiRequestedEdits,
	aiAdditionalInstructions,
	hasResult,
	onRequestedEditsChange,
	onAdditionalInstructionsChange,
	onGenerate,
	onShowResults,
}: GeneratorSettingsProps) {
	return (
		<>
			<div>
				{copyToRefine?.copy ? (
					<ToggleControl
						label={
							<h3 style={{ margin: 0 }}>
								{includeLastTurn
									? __('Refine Copy', 'prc-social-builder')
									: __('Generate New Copy', 'prc-social-builder')}
							</h3>
						}
						help={
							includeLastTurn
								? __(
										'Send copy for futher refinement based on requested edits.',
										'prc-social-builder'
									)
								: __(
										'Generate new copy from scratch.',
										'prc-social-builder'
									)
						}
						checked={includeLastTurn}
						onChange={onIncludeLastTurnChange}
					/>
				) : (
					<h3>{__('Generate new copy', 'prc-social-builder')}</h3>
				)}
			</div>
			{includeLastTurn && copyToRefine?.copy && (
				<>
					<p
						style={{
							fontSize: '13px',
							lineHeight: 1.5,
						}}
					>
						{copyToRefine.copy}
					</p>
					<TextareaControl
						label={__('Requested Edits', 'prc-social-builder')}
						help={__(
							'Optional guidance for regeneration (e.g. make it shorter).',
							'prc-social-builder'
						)}
						value={aiRequestedEdits}
						onChange={onRequestedEditsChange}
						rows={3}
					/>
				</>
			)}
			<TextareaControl
				label={__('Additional instructions', 'prc-social-builder')}
				help={__(
					'Optional guidance for the AI (e.g. tone, focus, angle).',
					'prc-social-builder'
				)}
				value={aiAdditionalInstructions}
				onChange={onAdditionalInstructionsChange}
				rows={1}
			/>
			<div
				style={{
					display: 'flex',
					gap: '8px',
					flexWrap: 'wrap',
					marginTop: '12px',
				}}
			>
				<Button
					variant="primary"
					label={__(
						'Generate Social Copy with AI',
						'prc-social-builder'
					)}
					onClick={onGenerate}
				>
					{__('Generate Copy', 'prc-social-builder')}
				</Button>
				{hasResult && (
					<Button variant="secondary" onClick={onShowResults}>
						{__('Return to Results', 'prc-social-builder')}
					</Button>
				)}
			</div>
		</>
	);
}