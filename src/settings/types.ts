export interface ThreadCounts {
	twitter: number;
	facebook: number;
	threads: number;
	bluesky: number;
	linkedin: number;
}

export interface NetworkInstructions {
	twitter: string;
	facebook: string;
	threads: string;
	bluesky: string;
	linkedin: string;
	instagram: string;
	tiktok: string;
	youtube: string;
}

export interface StoryFieldPrompts {
	caption: string;
	overlay_text: string;
	media_descriptions: string;
}

export interface SystemPrompts {
	'generate-thread': string;
	'generate-message': string;
	'generate-story': StoryFieldPrompts;
}

export interface StoryFieldDefaults {
	caption: string;
	overlay_text: string;
	media_descriptions: string;
}

export type FlatPromptKey = 'generate-thread' | 'generate-message';

export type FormatInstructionKey =
	| 'generate-thread'
	| 'generate-message'
	| 'generate-story';

export interface Settings {
	enable_neutrality_pass: boolean;
	thread_counts: ThreadCounts;
	network_instructions: NetworkInstructions;
	system_prompts: SystemPrompts;
}

export interface ApiResponse {
	settings: Settings;
	defaults: Omit<SystemPrompts, 'generate-story'>;
	format_instructions: Record<FormatInstructionKey, string>;
	story_field_defaults: StoryFieldDefaults;
}

export interface SettingsStoreState {
	settings: Settings;
	defaults: Omit<SystemPrompts, 'generate-story'>;
	formatInstructions: Record<FormatInstructionKey, string>;
	storyFieldDefaults: StoryFieldDefaults;
	isLoaded: boolean;
}
