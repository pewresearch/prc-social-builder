import { createSettingsStore } from '@prc/components';
import type {
	Settings,
	SettingsStoreState,
	ApiResponse,
	StoryFieldPrompts,
	FormatInstructionKey,
	FlatPromptKey,
	StoryFieldDefaults,
	SystemPrompts,
} from './types';

export const STORE_NAME = 'prc/social-builder-settings';

const DEFAULT_STORY_FIELD_PROMPTS: StoryFieldPrompts = {
	caption: '',
	overlay_text: '',
	media_descriptions: '',
};

const DEFAULT_SETTINGS: Settings = {
	enable_neutrality_pass: true,
	network_instructions: {
		twitter: '',
		facebook: '',
		threads: '',
		bluesky: '',
		linkedin: '',
		instagram: '',
		tiktok: '',
		youtube: '',
	},
	system_prompts: {
		'generate-social-copy': '',
		'generate-story': DEFAULT_STORY_FIELD_PROMPTS,
	},
};

const DEFAULT_STATE: SettingsStoreState = {
	settings: DEFAULT_SETTINGS,
	defaults: {
		'generate-social-copy': '',
	},
	formatInstructions: {
		'generate-social-copy': '',
		'generate-story': '',
	},
	storyFieldDefaults: {
		caption: '',
		overlay_text: '',
		media_descriptions: '',
	},
	isLoaded: false,
};

export const store = createSettingsStore<
	Settings,
	SettingsStoreState,
	ApiResponse
>({
	name: STORE_NAME,
	defaultState: DEFAULT_STATE,
	mapResponseToState: (_state, response) => ({
		defaults: response.defaults,
		formatInstructions: response.format_instructions,
		storyFieldDefaults: response.story_field_defaults,
	}),
	extraSelectors: {
		getDefaults(
			state: SettingsStoreState
		): Pick<SystemPrompts, FlatPromptKey> {
			return state.defaults;
		},
		getFormatInstructions(
			state: SettingsStoreState
		): Record<FormatInstructionKey, string> {
			return state.formatInstructions;
		},
		getStoryFieldDefaults(state: SettingsStoreState): StoryFieldDefaults {
			return state.storyFieldDefaults;
		},
	},
});
