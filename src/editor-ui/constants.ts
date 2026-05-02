import { __ } from '@wordpress/i18n';

export interface PlatformConfig {
	name: string;
	key: string;
	charLimit: number;
	supportsThreads: boolean;
	mediaTypes: readonly string[];
	supportsLinkPreview: boolean;
	aspectRatio?: string;
}

export const THREAD_PLATFORMS: Record<string, PlatformConfig> = {
	twitter: {
		name: __('Twitter / X', 'prc-social-builder'),
		key: 'twitter',
		charLimit: 280,
		supportsThreads: true,
		mediaTypes: ['image', 'gif', 'video'] as const,
		supportsLinkPreview: true,
	},
	facebook: {
		name: __('Facebook', 'prc-social-builder'),
		key: 'facebook',
		charLimit: 63206,
		supportsThreads: false,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: true,
	},
	threads: {
		name: __('Threads', 'prc-social-builder'),
		key: 'threads',
		charLimit: 500,
		supportsThreads: true,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: false,
	},
	bluesky: {
		name: __('Bluesky', 'prc-social-builder'),
		key: 'bluesky',
		charLimit: 300,
		supportsThreads: true,
		mediaTypes: ['image'] as const,
		supportsLinkPreview: true,
	},
} as const;

export const STORY_PLATFORMS: Record<string, PlatformConfig> = {
	instagram: {
		name: __('Instagram', 'prc-social-builder'),
		key: 'instagram',
		charLimit: 2200,
		supportsThreads: false,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: false,
		aspectRatio: '9:16',
	},
	facebook: {
		name: __('Facebook', 'prc-social-builder'),
		key: 'facebook',
		charLimit: 2200,
		supportsThreads: false,
		mediaTypes: ['image', 'video'] as const,
		supportsLinkPreview: false,
		aspectRatio: '9:16',
	},
	tiktok: {
		name: __('TikTok', 'prc-social-builder'),
		key: 'tiktok',
		charLimit: 2200,
		supportsThreads: false,
		mediaTypes: ['video'] as const,
		supportsLinkPreview: false,
		aspectRatio: '9:16',
	},
	youtube: {
		name: __('YouTube', 'prc-social-builder'),
		key: 'youtube',
		charLimit: 5000,
		supportsThreads: false,
		mediaTypes: ['video'] as const,
		supportsLinkPreview: false,
		aspectRatio: '16:9',
	},
} as const;

export function getCharLimit(platform: string): number {
	return (
		THREAD_PLATFORMS[platform]?.charLimit ??
		STORY_PLATFORMS[platform]?.charLimit ??
		280
	);
}

export function getCharCountColor(current: number, limit: number): string {
	const ratio = current / limit;
	if (ratio >= 1) return '#EF4444';
	if (ratio >= 0.8) return '#F59E0B';
	return '#22C55E';
}
