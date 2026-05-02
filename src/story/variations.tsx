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
import { STORY_PLATFORMS } from '../editor-ui/constants';

const variations: BlockVariation[] = Object.entries(STORY_PLATFORMS).map(
	([key, config]) => ({
		name: `story-${key}`,
		title: `${config.name} ${__('Story', 'prc-social-builder')}`,
		description: `${__('A social story for', 'prc-social-builder')} ${config.name}.`,
		icon: () => <Icon icon={key} library="brands" />,
		attributes: { platform: key },
		isDefault: key === 'instagram',
		scope: ['inserter', 'transform'] as const,
		isActive: (blockAttributes: Record<string, string>) =>
			blockAttributes.platform === key,
	})
);

export default variations;
