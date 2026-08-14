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

export type FlatPromptKey = 'generate-social-copy';

export interface SystemPrompts {
	'generate-social-copy': string;
	'generate-story': StoryFieldPrompts;
}

export interface StoryFieldDefaults {
	caption: string;
	overlay_text: string;
	media_descriptions: string;
}

export type FormatInstructionKey = 'generate-story' | 'generate-social-copy';

export interface Settings {
	enable_neutrality_pass: boolean;
	network_instructions: NetworkInstructions;
	system_prompts: SystemPrompts;
}

export interface ApiResponse {
	settings: Settings;
	defaults: Pick<SystemPrompts, FlatPromptKey>;
	format_instructions: Record<FormatInstructionKey, string>;
	story_field_defaults: StoryFieldDefaults;
}

export interface SettingsStoreState {
	settings: Settings;
	defaults: Pick<SystemPrompts, FlatPromptKey>;
	formatInstructions: Record<FormatInstructionKey, string>;
	storyFieldDefaults: StoryFieldDefaults;
	isLoaded: boolean;
	[key: string]: unknown;
}
