/**
 * External Dependencies
 */
import {
	TwitterPreview,
	FacebookPreview,
	BlueskyPreview,
	ThreadsPreview,
	LinkedInPreview,
	StyledComponentContext,
	MediaDropZone,
	CharacterCounter,
	AINumberCheckBadge,
	useAISuggest,
} from '@prc/components';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useRef, useEffect } from '@wordpress/element';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextareaControl, Spinner } from '@wordpress/components';

/**
 * Internal Dependencies
 */
import { getCharLimit, THREAD_PLATFORMS } from '../editor-ui/constants';
import { MessageInspectorAI } from './inspector-ai';

const PREVIEW_COMPONENTS: Record<string, React.ComponentType<any>> = {
	twitter: TwitterPreview,
	facebook: FacebookPreview,
	bluesky: BlueskyPreview,
	threads: ThreadsPreview,
	linkedin: LinkedInPreview,
};

const PRC_DEFAULTS = {
	displayName: 'Pew Research Center',
	username: 'pewresearch',
	handle: '@pewresearch.bsky.social',
	profilePicture: '',
	verified: true,
};

const CHECK_NUMBERS_ABILITY = 'prc-ai/check-numbers';
const DEBOUNCE_MS = 5000;

interface EditProps {
	attributes: {
		content: string;
		mediaId: number;
		mediaUrl: string;
		mediaType: string;
		linkUrl: string;
		position: number;
		aiAdditionalInstructions: string;
		numberCheck?: { valid: boolean; flagged: string[] } | null;
	};
	setAttributes: (attrs: Record<string, unknown>) => void;
	context: {
		'prc-social/platform': string;
		'prc-social/sourcePostId': number;
	};
	clientId: string;
	isSelected: boolean;
}

export default function Edit({
	attributes,
	setAttributes,
	context,
	isSelected,
}: EditProps) {
	const platform = context['prc-social/platform'] || 'twitter';
	const sourcePostId = context['prc-social/sourcePostId'] ?? 0;
	const {
		content,
		mediaId,
		mediaUrl,
		linkUrl,
		aiAdditionalInstructions,
		numberCheck,
	} = attributes;
	const charLimit = getCharLimit(platform);
	const debounceRef = useRef<ReturnType<typeof setTimeout>>();
	const requestIdRef = useRef(0);
	const activeRequestIdRef = useRef(0);
	const latestRequestIdRef = useRef(0);
	const lastCheckContentRef = useRef<string | null>(null);
	const pendingDeferredCheckRef = useRef(false);

	const {
		isLoading: isChecking,
		result: checkResult,
		fetch: runNumberCheck,
		reset: resetNumberCheck,
	} = useAISuggest<{ valid: boolean; flagged: string[] }>({
		abilityName: CHECK_NUMBERS_ABILITY,
		transformResult: (raw) => {
			const numbers =
				(raw.numbers as Array<{ token: string; status: string }>) ?? [];
			return {
				valid: Boolean(raw.valid),
				flagged: numbers
					.filter((n) => n.status !== 'verified')
					.map((n) => String(n.token)),
			};
		},
	});

	const startNumberCheck = (value: string) => {
		if (isChecking) {
			pendingDeferredCheckRef.current = true;
			latestRequestIdRef.current = ++requestIdRef.current;
			lastCheckContentRef.current = value;
			return;
		}
		const requestId = ++requestIdRef.current;
		activeRequestIdRef.current = requestId;
		latestRequestIdRef.current = requestId;
		lastCheckContentRef.current = value;
		void runNumberCheck({ output: value, postId: sourcePostId });
	};

	const invalidatePendingNumberCheck = () => {
		pendingDeferredCheckRef.current = false;
		latestRequestIdRef.current = ++requestIdRef.current;
		resetNumberCheck();
	};

	useEffect(() => {
		if (!checkResult) {
			return;
		}

		const isCurrent =
			activeRequestIdRef.current === latestRequestIdRef.current &&
			lastCheckContentRef.current === content;

		if (!isCurrent) {
			resetNumberCheck();
			if (
				sourcePostId > 0 &&
				content.trim() !== '' &&
				!debounceRef.current &&
				(lastCheckContentRef.current !== content ||
					pendingDeferredCheckRef.current)
			) {
				pendingDeferredCheckRef.current = false;
				startNumberCheck(content);
			}
			return;
		}

		setAttributes({ numberCheck: checkResult });
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [checkResult, content, sourcePostId]);

	const updateContent = (value: string) => {
		invalidatePendingNumberCheck();
		setAttributes({ content: value, numberCheck: null });
		if (debounceRef.current) {
			clearTimeout(debounceRef.current);
		}
		if (sourcePostId > 0 && value.trim() !== '') {
			debounceRef.current = setTimeout(() => {
				debounceRef.current = undefined;
				startNumberCheck(value);
			}, DEBOUNCE_MS);
		} else {
			lastCheckContentRef.current = null;
		}
	};

	useEffect(
		() => () => {
			if (debounceRef.current) {
				clearTimeout(debounceRef.current);
			}
		},
		[]
	);

	const PreviewComponent = PREVIEW_COMPONENTS[platform] ?? TwitterPreview;
	// MediaDropZone is a JS component — cast to bypass prop inference errors
	const MediaDropZoneField = MediaDropZone as React.ComponentType<any>;

	const blockProps = useBlockProps({
		className: `prc-social-message prc-social-message--${platform}`,
	});

	let platformTextProp: Record<string, string>;
	if (platform === 'twitter') {
		platformTextProp = { tweetText: content };
	} else if (platform === 'threads') {
		platformTextProp = { title: content };
	} else {
		platformTextProp = { postText: content };
	}

	const previewProps = {
		...PRC_DEFAULTS,
		title: '',
		description: '',
		url: linkUrl,
		image: mediaUrl || undefined,
		showLabel: false,
		isEditable: true,
		isSelected,
		charLimit,
		numberCheck,
		editableCallbacks: {
			onContentChange: updateContent,
		},
		...platformTextProp,
	};

	return (
		<div {...blockProps}>
			<InspectorControls>
				<MessageInspectorAI
					platform={platform}
					postId={sourcePostId}
					aiAdditionalInstructions={aiAdditionalInstructions}
					setAttributes={setAttributes}
					onApply={(option) => {
						if (debounceRef.current) {
							clearTimeout(debounceRef.current);
						}
						invalidatePendingNumberCheck();
						lastCheckContentRef.current = option.content;
						setAttributes({
							content: option.content,
							linkUrl: option.linkUrl ?? '',
							numberCheck: option.numberCheck ?? null,
						});
					}}
				/>
				<PanelBody
					title={__('Message', 'prc-social-builder')}
					initialOpen
				>
					<TextareaControl
						label={__('Content', 'prc-social-builder')}
						value={content}
						onChange={updateContent}
						help={
							<span
								style={{
									display: 'flex',
									alignItems: 'center',
									gap: '4px',
								}}
							>
								<CharacterCounter
									current={content.length}
									limit={charLimit}
								/>
								{isChecking ? (
									<Spinner style={{ margin: 0 }} />
								) : (
									<AINumberCheckBadge
										numberCheck={numberCheck ?? undefined}
									/>
								)}
							</span>
						}
						rows={5}
					/>
					<MediaDropZoneField
						attachmentId={mediaId || false}
						allowedTypes={
							THREAD_PLATFORMS[platform]?.mediaTypes?.filter(
								(t): t is string => typeof t === 'string'
							) ?? ['image']
						}
						onUpdate={(media: {
							id: number;
							url: string;
							type?: string;
						}) =>
							setAttributes({
								mediaId: media.id,
								mediaUrl: media.url,
								mediaType: media.type ?? 'image',
							})
						}
						onClear={() =>
							setAttributes({
								mediaId: 0,
								mediaUrl: '',
								mediaType: 'image',
							})
						}
					/>
				</PanelBody>
			</InspectorControls>
			<StyledComponentContext cacheKey="prc-social-message-preview">
				<PreviewComponent {...previewProps} />
			</StyledComponentContext>
		</div>
	);
}
