import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import {
	useBlockProps,
	RichText,
	MediaUpload,
	MediaUploadCheck,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	Button,
	TextareaControl,
	PanelBody,
	ComboboxControl,
	Notice,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { edit as editIcon } from '@wordpress/icons';
import {
	STORY_PLATFORMS,
	getCharLimit,
	getCharCountColor,
} from '../editor-ui/constants';
import { StoryInspectorAI } from './inspector-ai';

interface AssociatedPost {
	key: string;
	postId: number;
	title: string;
}

interface StoryAttributes {
	platform: string;
	sourcePostId: number;
	aiAdditionalInstructions: string;
	mediaId: number;
	mediaUrl: string;
	mediaType: string;
	caption: string;
	overlayText: string;
	duration: number;
}

interface EditProps {
	attributes: StoryAttributes;
	setAttributes: (attrs: Partial<StoryAttributes>) => void;
}

export default function Edit({ attributes, setAttributes }: EditProps) {
	const {
		platform,
		sourcePostId,
		aiAdditionalInstructions,
		mediaId,
		mediaUrl,
		mediaType,
		caption,
		overlayText,
	} = attributes;
	const platformConfig = STORY_PLATFORMS[platform];
	const isVertical = platformConfig?.aspectRatio === '9:16';
	const charLimit = getCharLimit(platform);
	const allowedMediaTypes = platformConfig?.mediaTypes?.includes('video')
		? ['image', 'video']
		: ['image'];

	const associatedPosts: AssociatedPost[] = useSelect(
		(select) =>
			(select(editorStore) as any).getEditedPostAttribute(
				'associatedPostsOrdered'
			) ?? [],
		[]
	);

	// Auto-select the first associated post when none is chosen yet.
	useEffect(() => {
		if (sourcePostId === 0 && associatedPosts.length > 0) {
			setAttributes({ sourcePostId: associatedPosts[0].postId });
		}
	}, [sourcePostId, associatedPosts]);

	const comboOptions = associatedPosts.map((p) => ({
		value: String(p.postId),
		label: p.title,
	}));

	const blockProps = useBlockProps({
		className: `prc-social-story prc-social-story--${platform}`,
	});

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={__('Source Post', 'prc-social-builder')}
					initialOpen
				>
					{associatedPosts.length === 0 ? (
						<Notice status="warning" isDismissible={false}>
							{__(
								'No associated posts found. Add posts via the Associated Posts panel in the document settings.',
								'prc-social-builder'
							)}
						</Notice>
					) : (
						<ComboboxControl
							__nextHasNoMarginBottom
							label={__(
								'Generate content from',
								'prc-social-builder'
							)}
							help={__(
								'Choose which associated post to use for AI generation.',
								'prc-social-builder'
							)}
							value={
								sourcePostId > 0 ? String(sourcePostId) : null
							}
							options={comboOptions}
							onChange={(val) =>
								setAttributes({
									sourcePostId: val ? Number(val) : 0,
								})
							}
						/>
					)}
				</PanelBody>
				<StoryInspectorAI
					platform={platform}
					sourcePostId={sourcePostId}
					aiAdditionalInstructions={aiAdditionalInstructions}
					setAttributes={setAttributes}
				/>
				<PanelBody
					title={__('Caption', 'prc-social-builder')}
					initialOpen
				>
					<TextareaControl
						__nextHasNoMarginBottom
						label={__('Caption text', 'prc-social-builder')}
						value={caption}
						onChange={(value: string) =>
							setAttributes({ caption: value })
						}
						help={
							<span
								style={{
									color: getCharCountColor(
										caption.length,
										charLimit
									),
								}}
							>
								{caption.length}/{charLimit}
							</span>
						}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div className="prc-social-story__header">
					<span className="prc-social-story__platform-label">
						{platformConfig?.name ?? platform}{' '}
						{__('Story', 'prc-social-builder')}
					</span>
				</div>

				<div
					className="prc-social-story__phone-frame"
					style={{ aspectRatio: isVertical ? '9 / 16' : '16 / 9' }}
				>
					{mediaUrl ? (
						<div className="prc-social-story__media">
							{mediaType === 'video' ? (
								<video src={mediaUrl} controls />
							) : (
								<img src={mediaUrl} alt="" />
							)}
							<div className="prc-social-story__overlay-text">
								<RichText
									tagName="span"
									value={overlayText}
									onChange={(value: string) =>
										setAttributes({ overlayText: value })
									}
									placeholder={__(
										'Overlay text...',
										'prc-social-builder'
									)}
									allowedFormats={['core/bold']}
								/>
							</div>
							<MediaUploadCheck>
								<MediaUpload
									onSelect={(media: {
										id: number;
										url: string;
										type?: string;
									}) =>
										setAttributes({
											mediaId: media.id,
											mediaUrl: media.url,
											mediaType:
												media.type === 'video'
													? 'video'
													: 'image',
										})
									}
									allowedTypes={allowedMediaTypes}
									value={mediaId}
									render={({
										open,
									}: {
										open: () => void;
									}) => (
										<Button
											className="prc-social-story__change-media"
											icon={editIcon}
											label={__(
												'Change media',
												'prc-social-builder'
											)}
											onClick={open}
											variant="primary"
											size="small"
										/>
									)}
								/>
							</MediaUploadCheck>
						</div>
					) : (
						<MediaUploadCheck>
							<MediaUpload
								onSelect={(media: {
									id: number;
									url: string;
									type?: string;
								}) =>
									setAttributes({
										mediaId: media.id,
										mediaUrl: media.url,
										mediaType:
											media.type === 'video'
												? 'video'
												: 'image',
									})
								}
								allowedTypes={allowedMediaTypes}
								value={mediaId}
								render={({ open }: { open: () => void }) => (
									<div
										className="prc-social-story__placeholder"
										onClick={open}
										role="button"
										tabIndex={0}
										onKeyDown={(e: React.KeyboardEvent) =>
											e.key === 'Enter' && open()
										}
									>
										<span>
											{__(
												'Click to add media',
												'prc-social-builder'
											)}
										</span>
									</div>
								)}
							/>
						</MediaUploadCheck>
					)}
				</div>
			</div>
		</>
	);
}
