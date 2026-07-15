import { createSettingsStore } from '@prc/components';
import type {
	Settings,
	SettingsStoreState,
	ApiResponse,
	StoryFieldPrompts,
	FormatInstructionKey,
	SystemPrompts,
	StoryFieldDefaults,
} from './types';

export const STORE_NAME = 'prc/social-builder-settings';

const DEFAULT_STORY_FIELD_PROMPTS: StoryFieldPrompts = {
	caption: '',
	overlay_text: '',
	media_descriptions: '',
};

const DEFAULT_SETTINGS: Settings = {
	enable_neutrality_pass: true,
	thread_counts: {
		twitter: 4,
		facebook: 1,
		threads: 4,
		bluesky: 4,
		linkedin: 1,
	},
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
		'generate-thread': '',
		'generate-message': '',
		'generate-story': DEFAULT_STORY_FIELD_PROMPTS,
	},
};

const DEFAULT_STATE: SettingsStoreState = {
	settings: DEFAULT_SETTINGS,
	defaults: {
		'generate-thread': '',
		'generate-message': '',
	},
	formatInstructions: {
		'generate-thread': '',
		'generate-message': '',
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
		): Omit<SystemPrompts, 'generate-story'> {
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
