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
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal Dependencies
 */
import { getCharLimit, SOCIAL_PLATFORMS } from '../editor-ui/constants';
import { getMessageCopy, PARAGRAPH_BLOCK } from './message-text';

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

const MESSAGE_TEMPLATE = [
	[
		PARAGRAPH_BLOCK,
		{
			placeholder: __('Write the social message…', 'prc-social-builder'),
		},
	],
] as const;

interface EditProps {
	attributes: {
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
	clientId,
	isSelected,
}: EditProps) {
	const platform = context['prc-social/platform'] || 'twitter';
	const sourcePostId = context['prc-social/sourcePostId'] ?? 0;
	const { mediaId, mediaUrl, linkUrl, numberCheck } = attributes;
	const charLimit = getCharLimit(platform);
	const debounceRef = useRef<ReturnType<typeof setTimeout>>();
	const requestIdRef = useRef(0);
	const activeRequestIdRef = useRef(0);
	const latestRequestIdRef = useRef(0);
	const lastCheckContentRef = useRef<string | null>(null);
	const pendingDeferredCheckRef = useRef(false);
	const previousCopyRef = useRef<string | null>(null);

	const innerBlocks = useSelect(
		(select) =>
			(
				select(blockEditorStore) as {
					getBlocks: (id: string) => Array<{
						name: string;
						attributes?: { content?: string };
						innerBlocks?: unknown[];
					}>;
				}
			).getBlocks(clientId),
		[clientId]
	);
	const copy = getMessageCopy({
		name: 'prc-social/message',
		attributes,
		innerBlocks,
	});

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
			lastCheckContentRef.current === copy;

		if (!isCurrent) {
			resetNumberCheck();
			if (
				sourcePostId > 0 &&
				copy.trim() !== '' &&
				!debounceRef.current &&
				(lastCheckContentRef.current !== copy ||
					pendingDeferredCheckRef.current)
			) {
				pendingDeferredCheckRef.current = false;
				startNumberCheck(copy);
			}
			return;
		}

		setAttributes({ numberCheck: checkResult });
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [checkResult, copy, sourcePostId]);

	useEffect(() => {
		const copyChanged =
			previousCopyRef.current !== null &&
			previousCopyRef.current !== copy;
		previousCopyRef.current = copy;

		if (copyChanged) {
			invalidatePendingNumberCheck();
			setAttributes({ numberCheck: null });
		}

		if (debounceRef.current) {
			clearTimeout(debounceRef.current);
		}
		if (copyChanged && sourcePostId > 0 && copy.trim() !== '') {
			debounceRef.current = setTimeout(() => {
				debounceRef.current = undefined;
				startNumberCheck(copy);
			}, DEBOUNCE_MS);
		} else if (copyChanged) {
			lastCheckContentRef.current = null;
		}

		return () => {
			if (debounceRef.current) {
				clearTimeout(debounceRef.current);
			}
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [copy, sourcePostId]);

	const PreviewComponent = PREVIEW_COMPONENTS[platform] ?? TwitterPreview;
	const MediaDropZoneField = MediaDropZone as React.ComponentType<any>;

	const blockProps = useBlockProps({
		className: `prc-social-message prc-social-message--${platform}`,
	});
	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'prc-social-message__inner' },
		{
			allowedBlocks: [PARAGRAPH_BLOCK, 'tabor/markdown-comment'],
			template: MESSAGE_TEMPLATE,
			templateLock: false,
			defaultBlock: { name: PARAGRAPH_BLOCK },
			directInsert: true,
		}
	);

	let platformTextProp: Record<string, string>;
	if (platform === 'twitter') {
		platformTextProp = { tweetText: copy };
	} else if (platform === 'threads') {
		platformTextProp = { title: copy };
	} else {
		platformTextProp = { postText: copy };
	}

	const previewProps = {
		...PRC_DEFAULTS,
		title: '',
		description: '',
		url: linkUrl,
		image: mediaUrl || undefined,
		showLabel: false,
		isEditable: false,
		isSelected,
		charLimit,
		numberCheck,
		textSlot: <div {...innerBlocksProps} />,
		...platformTextProp,
	};

	return (
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody
					title={__('Message', 'prc-social-builder')}
					initialOpen
				>
					<p
						style={{
							display: 'flex',
							alignItems: 'center',
							gap: '4px',
							marginTop: 0,
						}}
					>
						<CharacterCounter
							current={copy.length}
							limit={charLimit}
						/>
						{isChecking ? (
							<Spinner style={{ margin: 0 }} />
						) : (
							<AINumberCheckBadge
								numberCheck={numberCheck ?? undefined}
							/>
						)}
					</p>
					<MediaDropZoneField
						attachmentId={mediaId || false}
						allowedTypes={
							SOCIAL_PLATFORMS[platform]?.mediaTypes?.filter(
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
