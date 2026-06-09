import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as editorStore } from '@wordpress/editor';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEntityRecords, store as coreDataStore } from '@wordpress/core-data';
import {
	Button,
	Notice,
	Spinner,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';

interface SocialPackageRecord {
	id: number;
	title?: { rendered?: string };
	status?: string;
}

export default function SocialPackagesContent() {
	const postId = useSelect(
		(select) => select(editorStore).getCurrentPostId(),
		[]
	);

	const { saveEntityRecord } = useDispatch(coreDataStore);
	const [isCreating, setIsCreating] = useState(false);
	const [error, setError] = useState<string | null>(null);

	const { records: packages, isResolving } = useEntityRecords(
		'postType',
		'social-package',
		postId
			? {
					associated_post_id: postId,
					per_page: 10,
					status: ['draft', 'publish'],
				}
			: {}
	);

	const handleCreate = async () => {
		if (!postId || isCreating) {
			return;
		}

		setIsCreating(true);
		setError(null);

		try {
			const newPackage = (await saveEntityRecord(
				'postType',
				'social-package',
				{
					title: `Social Package for Post #${postId}`,
					status: 'draft',
					meta: { _prc_associated_posts: [postId] },
				},
				{ throwOnError: true }
			)) as { id?: number };

			if (newPackage?.id) {
				window.open(
					addQueryArgs('post.php', {
						post: newPackage.id,
						action: 'edit',
					}),
					'_blank'
				);
				return;
			}

			setError(
				__(
					'Could not create social package. Please try again.',
					'prc-social-builder'
				)
			);
		} catch (createError) {
			const fallback = __(
				'Could not create social package. Please try again.',
				'prc-social-builder'
			);
			const message =
				createError &&
				typeof createError === 'object' &&
				'message' in createError &&
				typeof createError.message === 'string'
					? createError.message
					: createError instanceof Error
						? createError.message
						: fallback;
			setError(message);
		} finally {
			setIsCreating(false);
		}
	};

	return (
		<VStack spacing="3">
			{isResolving && <Spinner />}
			{!isResolving && (packages as SocialPackageRecord[])?.length ? (
				<ul style={{ margin: 0, padding: 0, listStyle: 'none' }}>
					{(packages as SocialPackageRecord[]).map((pkg) => (
						<li key={pkg.id} style={{ marginBottom: '8px' }}>
							<strong>
								{pkg.title?.rendered || `Package #${pkg.id}`}
							</strong>
							<br />
							<span
								style={{ fontSize: '12px', color: '#757575' }}
							>
								{pkg.status === 'publish'
									? __('Published', 'prc-social-builder')
									: __('Draft', 'prc-social-builder')}
							</span>{' '}
							<Button
								variant="link"
								href={addQueryArgs('post.php', {
									post: pkg.id,
									action: 'edit',
								})}
								target="_blank"
							>
								{__('Edit', 'prc-social-builder')}
							</Button>
						</li>
					))}
				</ul>
			) : null}
			{!isResolving && !(packages as SocialPackageRecord[])?.length ? (
				<p style={{ color: '#757575' }}>
					{__(
						'No social packages linked to this post.',
						'prc-social-builder'
					)}
				</p>
			) : null}
			{error && (
				<Notice
					status="error"
					isDismissible
					onRemove={() => setError(null)}
				>
					{error}
				</Notice>
			)}
			<Button
				variant="secondary"
				onClick={handleCreate}
				disabled={isCreating || !postId}
				isBusy={isCreating}
				style={{ width: '100%', justifyContent: 'center' }}
				__next40pxDefaultSize
			>
				{isCreating
					? __('Creating…', 'prc-social-builder')
					: __('+ Create Social Package', 'prc-social-builder')}
			</Button>
		</VStack>
	);
}
