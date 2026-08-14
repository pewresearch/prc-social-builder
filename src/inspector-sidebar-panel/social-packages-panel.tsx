import { useEffect, useState, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as editorStore } from '@wordpress/editor';
import { useSelect } from '@wordpress/data';
import { useEntityRecords } from '@wordpress/core-data';
import {
	Button,
	Notice,
	Spinner,
	Modal,
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { addQueryArgs } from '@wordpress/url';
import { useAISuggest } from '@prc/components';

interface SocialPackageRecord {
	id: number;
	title?: { rendered?: string };
	status?: string;
}

export default function SocialPackagesContent() {
	const postInfo = useSelect((select) => {
		const editInfo = select(editorStore);
		return {
			postId: editInfo.getCurrentPostId(),
			title: editInfo.getEditedPostAttribute('title') || '',
		};
	}, []);
	const [error, setError] = useState<string | null>(null);
	const [refreshKey, setRefreshKey] = useState(postInfo.postId);
	const [isOpen, setOpen] = useState(false);
	const [isPackageLoading, setIsPackageLoading] = useState(false);
	const openModal = () => setOpen(true);
	const closeModal = () => setOpen(false);
	const { records: packages, isResolving } = useEntityRecords(
		'postType',
		'social-package',
		postInfo.postId
			? {
					associated_post_id: postInfo.postId,
					per_page: 10,
					status: ['draft', 'publish'],
					_refresh: refreshKey,
				}
			: {}
	);
	const {
		isLoading,
		error: socialError,
		result,
		fetch,
		reset,
		dismissError,
	} = useAISuggest<{ newPostId: number }>({
		abilityName: 'prc-social-builder/add-social-package',
	});

	// Get permissions
	useEffect(() => {
		if (
			typeof Notification !== 'undefined' &&
			Notification.permission === 'default'
		) {
			Notification.requestPermission();
		}
	}, []);

	// Update error to display social error
	useEffect(() => {
		if (socialError) {
			setError(socialError);
			setIsPackageLoading(false);
		}
		if (error) {
			sendAlert(false);
			setIsPackageLoading(false);
		}
	}, [error, socialError, reset]);

	// If a new social post has been created, open the post,
	// 	refresh the list of posts and reset our fetch function
	useEffect(() => {
		if (result?.newPostId) {
			setRefreshKey(result.newPostId);
			setIsPackageLoading(false);
			sendAlert(true);
			reset();
		}
	}, [result, reset]);
	const packageTitle =
		'' !== postInfo.title.trim()
			? postInfo.title
			: `Post #${postInfo.postId}`;

	const sendAlert = async (success: boolean) => {
		const text = success
			? `SUCCESS: Created "Social Package for ${packageTitle}"`
			: `ERROR: Failed to create a social package for "${packageTitle}"`;
		if (!document.hasFocus()) {
			if (
				typeof Notification !== 'undefined' &&
				Notification.permission === 'granted'
			) {
				const img = '/wp-content/images/symbol-alt.svg';
				const notif = new Notification('Social Package Builder', {
					body: text,
					icon: img,
				});
				notif.onclick = function () {
					window.parent.parent.focus();
				};
			} else {
				alert(text);
			}
		}
	};

	const handleOpenPackage = () => {
		window.open(
			addQueryArgs('post.php', {
				post: refreshKey,
				action: 'edit',
			}),
			'_blank'
		);
	};

	const handleCreate = () => {
		setError(null);
		openModal();
		setIsPackageLoading(true);
		fetch({
			isDefaultList: true,
			blankTemplate: false,
			associatedPostId: postInfo.postId,
			title: `Social Package for ${packageTitle}`,
			content: [
				{
					contentType: 'wp-post',
					postId: postInfo.postId,
				},
			],
		});
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
			<Button
				variant="secondary"
				onClick={handleCreate}
				disabled={isLoading || !postInfo.postId}
				style={{ width: '100%', justifyContent: 'center' }}
			>
				{isLoading
					? __('Creating…', 'prc-social-builder')
					: __('+ Create Social Package', 'prc-social-builder')}
			</Button>
			{isOpen && (
				<Modal
					title="Social Package Builder"
					onRequestClose={closeModal}
					isDismissible={false}
					shouldCloseOnClickOutside={false}
					shouldCloseOnEsc={false}
					style={{ maxWidth: '400px' }}
				>
					{isLoading && (
						<p>
							{' '}
							<Spinner /> Creating Social Package...{' '}
						</p>
					)}
					{error && (
						<Notice status="error" isDismissible={false}>
							{error}
						</Notice>
					)}
					{!error && !isLoading && !socialError && (
						<p>
							Created{' '}
							<strong>Social Package for "{packageTitle}"</strong>
						</p>
					)}

					<Button
						variant="primary"
						style={{
							width: '48%',
							justifyContent: 'center',
							margin: '0 2% 0 0',
						}}
						disabled={isPackageLoading}
						isBusy={isPackageLoading}
						onClick={error ? handleCreate : handleOpenPackage}
					>
						{isPackageLoading
							? isLoading
								? __('Creating…', 'prc-social-builder')
								: __('Getting Link...', 'prc-social-builder')
							: error
								? __('Retry', 'prc-social-builder')
								: __('Open Package', 'prc-social-builder')}
					</Button>
					<Button
						variant="secondary"
						style={{
							width: '48%',
							justifyContent: 'center',
							margin: '0 0 0 2%',
						}}
						disabled={isLoading}
						isBusy={isLoading}
						onClick={closeModal}
					>
						Close
					</Button>
				</Modal>
			)}
		</VStack>
	);
}
