/**
 * External Dependencies
 */
import { Icon } from '@prc/icons';

/**
 * WordPress Dependencies
 */
import { __ } from '@wordpress/i18n';
import type { BlockVariation } from '@wordpress/blocks';

/**
 * Internal Dependencies
 */
import { THREAD_PLATFORMS } from '../editor-ui/constants';

const variations: BlockVariation[] = Object.entries(THREAD_PLATFORMS).map(
	([key, config]) => ({
		name: `thread-${key}`,
		title: `${config.name} ${__('Thread', 'prc-social-builder')}`,
		description: `${__('A social thread for', 'prc-social-builder')} ${config.name}.`,
		icon: () => <Icon icon={key} library="brands" />,
		attributes: { platform: key },
		isDefault: key === 'twitter',
		scope: ['inserter', 'transform'] as const,
		isActive: (blockAttributes: Record<string, string>) =>
			blockAttributes.platform === key,
	})
);

export default variations;
