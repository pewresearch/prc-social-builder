/**
 * External Dependencies
 */
import {
	TwitterPreview,
	FacebookPreview,
	BlueskyPreview,
	ThreadsPreview,
	StyledComponentContext,
	MediaDropZone,
	CharacterCounter,
} from '@prc/components';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextareaControl } from '@wordpress/components';

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
};

const PRC_DEFAULTS = {
	displayName: 'Pew Research Center',
	username: 'pewresearch',
	handle: '@pewresearch.bsky.social',
	profilePicture: '',
	verified: true,
};

interface EditProps {
	attributes: {
		content: string;
		mediaId: number;
		mediaUrl: string;
		mediaType: string;
		linkUrl: string;
		position: number;
		aiAdditionalInstructions: string;
	};
	setAttributes: (attrs: Record<string, unknown>) => void;
	context: {
		'prc-social/platform': string;
		'prc-social/sourcePostId': number;
	};
	clientId: string;
}

export default function Edit({
	attributes,
	setAttributes,
	context,
}: EditProps) {
	const platform = context['prc-social/platform'] || 'twitter';
	const sourcePostId = context['prc-social/sourcePostId'] ?? 0;
	const { content, mediaId, mediaUrl, linkUrl, aiAdditionalInstructions } =
		attributes;
	const charLimit = getCharLimit(platform);

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
					onApply={(option) =>
						setAttributes({
							content: option.content,
							linkUrl: option.linkUrl ?? '',
						})
					}
				/>
				<PanelBody
					title={__('Message', 'prc-social-builder')}
					initialOpen
				>
					<TextareaControl
						label={__('Content', 'prc-social-builder')}
						value={content}
						onChange={(value) => setAttributes({ content: value })}
						help={
							<CharacterCounter
								current={content.length}
								limit={charLimit}
							/>
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
