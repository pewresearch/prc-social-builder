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
import { THREAD_PLATFORMS } from '../editor-ui/constants';
import { ThreadInspectorAI } from './inspector-ai';
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
	};
	setAttributes: (
		attrs: Partial<{
			platform: string;
			sourcePostId: number;
			includeReportChildren: boolean;
			unselectedReportChildren: string;
			aiAdditionalInstructions: string;
		}>
	) => void;
	clientId: string;
}

export default function Edit({
	attributes,
	setAttributes,
	clientId,
}: EditProps) {
	const { platform, sourcePostId, aiAdditionalInstructions, includeReportChildren, unselectedReportChildren } = attributes;
	const platformConfig = THREAD_PLATFORMS[platform];

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
		className: `prc-social-thread prc-social-thread--${platform}`,
	});

	const innerBlocksProps = useInnerBlocksProps(
		{ className: 'prc-social-thread__messages' },
		{
			template: [['prc-social/message', {}]],
			templateLock: false,
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
				<ThreadInspectorAI
					platform={platform}
					clientId={clientId}
					sourcePostId={sourcePostId}
					includeReportChildren={includeReportChildren}
					unselectedReportChildren={unselectedReportChildren}
					aiAdditionalInstructions={aiAdditionalInstructions}
					setAttributes={setAttributes}
					innerBlockCount={innerBlockCount}
				/>
			</InspectorControls>
			<div className="prc-social-thread__header">
				<span className="prc-social-thread__platform-label">
					{platformConfig?.name ?? platform}
				</span>
				<span className="prc-social-thread__char-limit">
					{__('Char limit:', 'prc-social-builder')}{' '}
					{platformConfig?.charLimit}
				</span>
			</div>
			<div {...innerBlocksProps} />
		</div>
	);
}
