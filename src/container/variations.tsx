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
import { SOCIAL_PLATFORMS } from '../editor-ui/constants';

const variations: BlockVariation[] = Object.entries(SOCIAL_PLATFORMS).map(
	([key, config]) => ({
		name: `social-copy-${key}`,
		title: `${config.name} ${__('Post', 'prc-social-builder')}`,
		description: `${__('Social Copy for', 'prc-social-builder')} ${config.name}.`,
		icon: () => <Icon icon={key} library="brands" />,
		attributes: { platform: key },
		isDefault: key === 'twitter',
		scope: ['inserter', 'transform'] as const,
		isActive: (blockAttributes: Record<string, string>) =>
			blockAttributes.platform === key,
	})
);

export default variations;
