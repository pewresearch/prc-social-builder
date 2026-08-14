import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, ComboboxControl, Notice, ToggleControl, CheckboxControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { SOCIAL_PLATFORMS } from '../editor-ui/constants';
import { ContainerInspectorAI } from './inspector-ai';
import { SelectReportChildren } from './include-reports';


interface AssociatedPost {
	key: string;
	postId: number;
	title: string;
}

interface EditProps {
	attributes: {
		platform: string;
		sourcePostId: number;
		includeReportChildren: boolean;
		unselectedReportChildren: string;
		aiAdditionalInstructions: string;
		aiRequestedEdits: string;
	};
	setAttributes: (
		attrs: Partial<{
			platform: string;
			sourcePostId: number;
			includeReportChildren: boolean;
			unselectedReportChildren: string;
			aiAdditionalInstructions: string;
			aiRequestedEdits: string;
		}>
	) => void;
	clientId: string;
}

export default function Edit({
	attributes,
	setAttributes,
	clientId,
}: EditProps) {
	const { platform, sourcePostId, aiAdditionalInstructions, aiRequestedEdits, includeReportChildren, unselectedReportChildren } = attributes;
	const platformConfig = SOCIAL_PLATFORMS[platform];

	const innerBlockCount = useSelect(
		(select) =>
			(
				select(blockEditorStore) as {
					getBlockCount: (id: string) => number;
				}
			).getBlockCount(clientId),
		[clientId]
	);

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
		className: `prc-social-container prc-social-container--${platform}`,
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'prc-social-container__messages' },
		{
			template: [['prc-social/message', {}]],
			templateLock: 'all',
		}
	);

	return (
		<div {...blockProps}>
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
						<>
							<ComboboxControl
								__nextHasNoMarginBottom
								label={__(
									'Generate content from',
									'prc-social-builder'
								)}
								help={__(
									'Choose which associated post to use for AI generation and other features.',
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
							 
							 <SelectReportChildren
								sourcePostId={sourcePostId}
								includeReportChildren={includeReportChildren}
								unselectedReportChildren={unselectedReportChildren}
								setIncludeChildren={setAttributes}
								setUnselectedChildren={setAttributes}
							/>
						</>
					)}
				</PanelBody>
				<ContainerInspectorAI
					platform={platform}
					clientId={clientId}
					sourcePostId={sourcePostId}
					includeReportChildren={includeReportChildren}
					unselectedReportChildren={unselectedReportChildren}
					aiAdditionalInstructions={aiAdditionalInstructions}
					aiRequestedEdits={aiRequestedEdits}
					setAttributes={setAttributes}
					innerBlockCount={innerBlockCount}
				/>
			</InspectorControls>
			<div className="prc-social-container__header">
				<span className="prc-social-container__platform-label">
					{platformConfig?.name ?? platform}
				</span>
				<span className="prc-social-container__char-limit">
					{__('Char limit:', 'prc-social-builder')}{' '}
					{platformConfig?.charLimit}
				</span>
			</div>
			<div {...innerBlocksProps} />
		</div>
	);
}
