import { __ } from '@wordpress/i18n';
import {
	PluginDocumentSettingPanel,
	store as editorStore,
} from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback, useRef } from '@wordpress/element';
import {
	BaseControl,
	Button,
	Flex,
	FlexItem,
	FlexBlock,
	ExternalLink,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { closeSmall } from '@wordpress/icons';
import { WPEntitySearch } from '@prc/components';

interface AssociatedPost {
	key: string;
	postId: number;
	title: string;
}

export default function AssociatedPostsPanel() {
	const { editPost } = useDispatch('core/editor');

	const items: AssociatedPost[] = useSelect(
		(select) =>
			(select(editorStore) as any).getEditedPostAttribute(
				'associatedPostsOrdered'
			) ?? [],
		[]
	);

	const itemsRef = useRef(items);
	itemsRef.current = items;

	const writeItems = useCallback(
		(nextItems: AssociatedPost[]) => {
			itemsRef.current = nextItems;
			editPost({ associatedPostsOrdered: nextItems });
		},
		[editPost]
	);

	const handleSelect = useCallback(
		(entity: {
			entityId: number;
			entityName: string;
			entityUrl: string;
		}) => {
			const postId = entity.entityId;
			const current = Array.isArray(itemsRef.current)
				? [...itemsRef.current]
				: [];
			const alreadyAdded = current.some((item) => item.postId === postId);
			if (alreadyAdded) {
				return;
			}
			writeItems([
				...current,
				{
					key: `post_${postId}`,
					postId,
					title: entity.entityName,
				},
			]);
		},
		[writeItems]
	);

	const handleRemove = useCallback(
		(index: number) => {
			const current = Array.isArray(itemsRef.current)
				? [...itemsRef.current]
				: [];
			current.splice(index, 1);
			writeItems([...current]);
		},
		[writeItems]
	);

	const safeItems = Array.isArray(items) ? items : [];

	return (
		<PluginDocumentSettingPanel
			name="prc-social-builder-associated-posts"
			title={__('Associated Posts', 'prc-social-builder')}
		>
			<BaseControl
				help={__(
					'Select post(s) that are used as context to generate messages and media.',
					'prc-social-builder'
				)}
			>
				<WPEntitySearch
					placeholder={__('Search for a post…', 'prc-social-builder')}
					entityType="postType"
					entitySubType={[
						'post',
						'short-read',
						'fact-sheet',
						'feature',
						'quiz',
						'page',
					]}
					entityStatus={['publish', 'draft']}
					onSelect={handleSelect}
					clearOnSelect={true}
					showExcerpt={false}
					showUrl={true}
				/>
			</BaseControl>
			{safeItems.length > 0 && (
				<ul
					style={{
						margin: '8px 0 0',
						padding: 0,
						listStyle: 'none',
					}}
				>
					{safeItems.map((item, index) => (
						<li
							key={item.key}
							style={{
								borderTop: '1px solid #e0e0e0',
								padding: '6px 0',
							}}
						>
							<Flex align="center" gap={2}>
								<FlexBlock>
									<VStack>
										<span style={{ fontSize: '13px' }}>
											{item.title ||
												`Post #${item.postId}`}
										</span>
										<ExternalLink
											href={`${window?.prcPlatform?.siteUrl}/?p=${item.postId}`}
											rel="noreferrer noopener"
										>
											{__(
												'View Post',
												'prc-social-builder'
											)}
										</ExternalLink>
									</VStack>
								</FlexBlock>
								<FlexItem>
									<Button
										icon={closeSmall}
										label={__(
											'Remove',
											'prc-social-builder'
										)}
										isSmall
										onClick={() => handleRemove(index)}
									/>
								</FlexItem>
							</Flex>
						</li>
					))}
				</ul>
			)}
			{safeItems.length === 0 && (
				<p
					style={{
						color: '#757575',
						marginTop: '8px',
						fontSize: '13px',
					}}
				>
					{__(
						'No posts associated with this social package.',
						'prc-social-builder'
					)}
				</p>
			)}
		</PluginDocumentSettingPanel>
	);
}
