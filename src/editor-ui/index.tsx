import { registerPlugin } from '@wordpress/plugins';
import { useCommand } from '@wordpress/commands';
import { addQueryArgs } from '@wordpress/url';
import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { PanelBody } from '@wordpress/components';
import { addFilter } from '@wordpress/hooks';
import apiFetch from '@wordpress/api-fetch';
import InspectorSidebarPanel from '../inspector-sidebar-panel';
import SocialPackagesContent from '../inspector-sidebar-panel/social-packages-panel';

function SocialBuilderPlugin() {
	const postId = useSelect(
		(select) =>
			(
				select(editorStore) as { getCurrentPostId?: () => number }
			)?.getCurrentPostId?.(),
		[]
	);

	useCommand({
		name: 'prc-social-builder/create-package',
		label: 'Social: Create Package for this post',
		callback: async ({ close }: { close: () => void }) => {
			close();
			const response = (await apiFetch({
				path: '/wp/v2/social-package',
				method: 'POST',
				data: {
					title: `Social Package for Post #${postId}`,
					status: 'draft',
					meta: { _prc_associated_posts: [postId] },
				},
			})) as { id?: number };
			if (response?.id) {
				window.open(
					addQueryArgs('post.php', {
						post: response.id,
						action: 'edit',
					}),
					'_blank'
				);
			}
		},
	});

	useCommand({
		name: 'prc-social-builder/view-packages',
		label: 'Social: View all Social Packages',
		callback: ({ close }: { close: () => void }) => {
			close();
			window.location.href = 'edit.php?post_type=social-package';
		},
	});

	return <InspectorSidebarPanel />;
}

registerPlugin('prc-social-builder', {
	render: SocialBuilderPlugin,
	icon: null,
});

/**
 * Inject Social Packages panel into the SEO sidebar's Social section.
 * Appends a "Social Packages" PanelBody after the existing Social fields
 * provided by prc-schema-seo.
 */
function socialBuilderSEOHook(OriginalPanel: React.ComponentType<object>) {
	return (props: object) => (
		<>
			<OriginalPanel {...props} />
			<PanelBody title="Social Packages" initialOpen={false}>
				<SocialPackagesContent />
			</PanelBody>
		</>
	);
}

addFilter(
	'prc-platform.seo.ui.social',
	'prc-platform/social-builder',
	socialBuilderSEOHook
);
