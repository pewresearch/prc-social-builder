import { __ } from '@wordpress/i18n';

interface PlatformLimits {
	/** Hard limit the network enforces. Drives the editor counter. */
	max: number;
	/** Length server-side generation aims for. Never above `max`. */
	target: number;
}

interface LocalizedLimits {
	default: PlatformLimits;
	social: Record<string, PlatformLimits>;
	story: Record<string, PlatformLimits>;
}

/**
 * Localized from PHP (`Platform_Limits::get_editor_payload()`) on the
 * `prc-social-builder-limits` handle, which every Social Builder editor script
 * depends on.
 */
declare const prcSocialBuilderLimits: LocalizedLimits | undefined;

const FALLBACK_LIMIT: PlatformLimits = { max: 280, target: 280 };

function getLimits(): LocalizedLimits {
	const localized =
		typeof prcSocialBuilderLimits === 'undefined'
			? undefined
			: prcSocialBuilderLimits;
	return {
		default: localized?.default ?? FALLBACK_LIMIT,
		social: localized?.social ?? {},
		story: localized?.story ?? {},
	};
}

const LIMITS = getLimits();
const SOCIAL_LIMITS = LIMITS.social;
const STORY_LIMITS = LIMITS.story;
const DEFAULT_LIMITS = LIMITS.default;

function limitFor(
	limits: Record<string, PlatformLimits>,
	platform: string
): PlatformLimits {
	return limits[platform] ?? limits.default ?? DEFAULT_LIMITS;
}

export interface PlatformConfig {
	name: string;
	key: string;
	charLimit: number;
	generationTarget: number;
	supportsThreads: boolean;
	mediaTypes: readonly string[];
	supportsLinkPreview: boolean;
	aspectRatio?: string;
}

export const SOCIAL_PLATFORMS: Record<string, PlatformConfig> = {
	twitter: {
		name: __('Twitter / X', 'prc-social-builder'),
		key: 'twitter',
		charLimit: limitFor(SOCIAL_LIMITS, 'twitter').max,
		generationTarget: limitFor(SOCIAL_LIMITS, 'twitter').target,
		supportsThreads: false,
		mediaTypes: ['image', 'gif', 'video'] as const,
		supportsLinkPreview: true,
	},
	facebook: {
		name: __('Facebook', 'prc-social-builder'),
		key: 'facebook',
		charLimit: limitFor(SOCIAL_LIMITS, 'facebook').max,
		generationTarget: limitFor(SOCIAL_LIMITS, 'facebook').target,
		supportsThreads: false,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: true,
	},
	threads: {
		name: __('Threads', 'prc-social-builder'),
		key: 'threads',
		charLimit: limitFor(SOCIAL_LIMITS, 'threads').max,
		generationTarget: limitFor(SOCIAL_LIMITS, 'threads').target,
		supportsThreads: false,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: false,
	},
	bluesky: {
		name: __('Bluesky', 'prc-social-builder'),
		key: 'bluesky',
		charLimit: limitFor(SOCIAL_LIMITS, 'bluesky').max,
		generationTarget: limitFor(SOCIAL_LIMITS, 'bluesky').target,
		supportsThreads: false,
		mediaTypes: ['image'] as const,
		supportsLinkPreview: true,
	},
	linkedin: {
		name: __('LinkedIn', 'prc-social-builder'),
		key: 'linkedin',
		charLimit: limitFor(SOCIAL_LIMITS, 'linkedin').max,
		generationTarget: limitFor(SOCIAL_LIMITS, 'linkedin').target,
		supportsThreads: false,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: true,
	},
} as const;

export const STORY_PLATFORMS: Record<string, PlatformConfig> = {
	instagram: {
		name: __('Instagram', 'prc-social-builder'),
		key: 'instagram',
		charLimit: limitFor(STORY_LIMITS, 'instagram').max,
		generationTarget: limitFor(STORY_LIMITS, 'instagram').target,
		supportsThreads: false,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: false,
		aspectRatio: '9:16',
	},
	facebook: {
		name: __('Facebook', 'prc-social-builder'),
		key: 'facebook',
		charLimit: limitFor(STORY_LIMITS, 'facebook').max,
		generationTarget: limitFor(STORY_LIMITS, 'facebook').target,
		supportsThreads: false,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: false,
		aspectRatio: '9:16',
	},
	tiktok: {
		name: __('TikTok', 'prc-social-builder'),
		key: 'tiktok',
		charLimit: limitFor(STORY_LIMITS, 'tiktok').max,
		generationTarget: limitFor(STORY_LIMITS, 'tiktok').target,
		supportsThreads: false,
		mediaTypes: ['video'] as const,
		supportsLinkPreview: false,
		aspectRatio: '9:16',
	},
	youtube: {
		name: __('YouTube', 'prc-social-builder'),
		key: 'youtube',
		charLimit: limitFor(STORY_LIMITS, 'youtube').max,
		generationTarget: limitFor(STORY_LIMITS, 'youtube').target,
		supportsThreads: false,
		mediaTypes: ['video'] as const,
		supportsLinkPreview: false,
		aspectRatio: '16:9',
	},
} as const;

export function getCharLimit(platform: string): number {
	return (
		SOCIAL_PLATFORMS[platform]?.charLimit ??
		STORY_PLATFORMS[platform]?.charLimit ??
		DEFAULT_LIMITS.max
	);
}

export function getCharCountColor(current: number, limit: number): string {
	const ratio = current / limit;
	if (ratio >= 1) return '#EF4444';
	if (ratio >= 0.8) return '#F59E0B';
	return '#22C55E';
}
