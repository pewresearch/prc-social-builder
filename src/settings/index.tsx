import { createRoot } from '@wordpress/element';
import SettingsApp from './app';

document.addEventListener('DOMContentLoaded', () => {
	const container = document.getElementById(
		'prc-social-builder-settings-admin'
	);
	if (container) {
		const root = createRoot(container);
		root.render(<SettingsApp />);
	}
});
