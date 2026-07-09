import { __ } from '@wordpress/i18n';
import { AINumberCheckBadge } from '@prc/components';

export interface StoryResult {
	caption: string;
	overlayText: string;
	suggestedMediaIds: number[];
	suggestedMediaDescriptions: string[];
	numberCheck?: {
		valid: boolean;
		flagged: string[];
	};
}

interface MediaRecord {
	id: number;
	source_url: string;
	alt_text?: string;
}

interface StorySuggestionPreviewProps {
	result: StoryResult;
	mediaRecords: Array<MediaRecord | null>;
	selectedMediaIndex: number | null;
	onSelectMedia: (index: number | null) => void;
}

function StoryMediaOption({
	id,
	index,
	media,
	description,
	isSelected,
	onSelect,
}: {
	id: number;
	index: number;
	media: MediaRecord | null;
	description: string;
	isSelected: boolean;
	onSelect: (index: number | null) => void;
}) {
	return (
		<div
			role="button"
			tabIndex={0}
			aria-pressed={isSelected}
			onClick={() => onSelect(isSelected ? null : index)}
			onKeyDown={(event) => {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					onSelect(isSelected ? null : index);
				}
			}}
			style={{
				display: 'flex',
				alignItems: 'flex-start',
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
					src={media.source_url}
					alt={media.alt_text ?? ''}
					style={{
						width: '48px',
						height: '48px',
						objectFit: 'cover',
						borderRadius: '4px',
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
				{description || `Attachment #${id}`}
			</span>
		</div>
	);
}

export function StorySuggestionPreview({
	result,
	mediaRecords,
	selectedMediaIndex,
	onSelectMedia,
}: StorySuggestionPreviewProps) {
	return (
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
					{__('Caption', 'prc-social-builder')}{' '}
					<AINumberCheckBadge numberCheck={result.numberCheck} />
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
						{result.suggestedMediaIds.map((id, index) => (
							<StoryMediaOption
								key={id}
								id={id}
								index={index}
								media={mediaRecords[index]}
								description={
									result.suggestedMediaDescriptions[index] ??
									''
								}
								isSelected={selectedMediaIndex === index}
								onSelect={onSelectMedia}
							/>
						))}
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
	);
}
