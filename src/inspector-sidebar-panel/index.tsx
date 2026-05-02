import { useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import AssociatedPostsPanel from './associated-posts-panel';

export default function InspectorSidebarPanel() {
	const postType = useSelect(
		(select) => select(editorStore).getCurrentPostType(),
		[]
	);

	if (postType === 'social-package') {
		return <AssociatedPostsPanel />;
	}

	return null;
}
