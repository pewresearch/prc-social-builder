import { __ } from '@wordpress/i18n';
import { createSettingsClient } from '@prc/components';

import { store } from './store';

export const { fetchSettings, saveSettings } = createSettingsClient({
	restPath: '/prc-social-builder/v1/settings',
	store,
	successMessage: __('Settings saved.', 'prc-social-builder'),
});
