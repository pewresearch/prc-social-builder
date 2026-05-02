import { createReduxStore, register } from '@wordpress/data';
import type {
	Settings,
	SettingsStoreState,
	ApiResponse,
	ThreadCounts,
	NetworkInstructions,
	StoryFieldPrompts,
	StoryFieldDefaults,
	FlatPromptKey,
	FormatInstructionKey,
	SystemPrompts,
} from './types';

export const STORE_NAME = 'prc/social-builder-settings';

const DEFAULT_STORY_FIELD_PROMPTS: StoryFieldPrompts = {
	caption: '',
	overlay_text: '',
	media_descriptions: '',
};

const DEFAULT_SETTINGS: Settings = {
	thread_counts: { twitter: 4, facebook: 1, threads: 4, bluesky: 4 },
	network_instructions: {
		twitter: '',
		facebook: '',
		threads: '',
		bluesky: '',
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

const actions = {
	setFromResponse(response: ApiResponse) {
		return {
			type: 'SET_FROM_RESPONSE' as const,
			response,
		};
	},
	updateThreadCount(platform: keyof ThreadCounts, value: number) {
		return {
			type: 'UPDATE_THREAD_COUNT' as const,
			platform,
			value,
		};
	},
	updateNetworkInstruction(
		platform: keyof NetworkInstructions,
		value: string
	) {
		return {
			type: 'UPDATE_NETWORK_INSTRUCTION' as const,
			platform,
			value,
		};
	},
	updateSystemPrompt(key: FlatPromptKey, value: string) {
		return {
			type: 'UPDATE_SYSTEM_PROMPT' as const,
			key,
			value,
		};
	},
	resetSystemPrompt(key: FlatPromptKey) {
		return {
			type: 'RESET_SYSTEM_PROMPT' as const,
			key,
		};
	},
	updateStoryFieldPrompt(field: keyof StoryFieldPrompts, value: string) {
		return {
			type: 'UPDATE_STORY_FIELD_PROMPT' as const,
			field,
			value,
		};
	},
	resetStoryFieldPrompt(field: keyof StoryFieldPrompts) {
		return {
			type: 'RESET_STORY_FIELD_PROMPT' as const,
			field,
		};
	},
};

type Action = ReturnType<(typeof actions)[keyof typeof actions]>;

function reducer(
	state: SettingsStoreState = DEFAULT_STATE,
	action: Action
): SettingsStoreState {
	switch (action.type) {
		case 'SET_FROM_RESPONSE':
			return {
				...state,
				settings: action.response.settings,
				defaults: action.response.defaults,
				formatInstructions: action.response.format_instructions,
				storyFieldDefaults: action.response.story_field_defaults,
				isLoaded: true,
			};
		case 'UPDATE_THREAD_COUNT':
			return {
				...state,
				settings: {
					...state.settings,
					thread_counts: {
						...state.settings.thread_counts,
						[action.platform]: action.value,
					},
				},
			};
		case 'UPDATE_NETWORK_INSTRUCTION':
			return {
				...state,
				settings: {
					...state.settings,
					network_instructions: {
						...state.settings.network_instructions,
						[action.platform]: action.value,
					},
				},
			};
		case 'UPDATE_SYSTEM_PROMPT':
			return {
				...state,
				settings: {
					...state.settings,
					system_prompts: {
						...state.settings.system_prompts,
						[action.key]: action.value,
					},
				},
			};
		case 'RESET_SYSTEM_PROMPT':
			return {
				...state,
				settings: {
					...state.settings,
					system_prompts: {
						...state.settings.system_prompts,
						[action.key]: '',
					},
				},
			};
		case 'UPDATE_STORY_FIELD_PROMPT':
			return {
				...state,
				settings: {
					...state.settings,
					system_prompts: {
						...state.settings.system_prompts,
						'generate-story': {
							...state.settings.system_prompts['generate-story'],
							[action.field]: action.value,
						},
					},
				},
			};
		case 'RESET_STORY_FIELD_PROMPT':
			return {
				...state,
				settings: {
					...state.settings,
					system_prompts: {
						...state.settings.system_prompts,
						'generate-story': {
							...state.settings.system_prompts['generate-story'],
							[action.field]: '',
						},
					},
				},
			};
		default:
			return state;
	}
}

const selectors = {
	getSettings(state: SettingsStoreState): Settings {
		return state.settings;
	},
	getThreadCounts(state: SettingsStoreState): ThreadCounts {
		return state.settings.thread_counts;
	},
	getNetworkInstructions(state: SettingsStoreState): NetworkInstructions {
		return state.settings.network_instructions;
	},
	getSystemPrompts(state: SettingsStoreState): SystemPrompts {
		return state.settings.system_prompts;
	},
	getStoryPrompts(state: SettingsStoreState): StoryFieldPrompts {
		return state.settings.system_prompts['generate-story'];
	},
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
	isLoaded(state: SettingsStoreState): boolean {
		return state.isLoaded;
	},
};

export const store = createReduxStore(STORE_NAME, {
	reducer,
	actions,
	selectors,
});

register(store);
