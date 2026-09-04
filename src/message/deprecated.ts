/**
 * WordPress Dependencies
 */
import { createBlock } from '@wordpress/blocks';

/**
 * Internal Dependencies
 */
import { splitCopyIntoParagraphs } from './message-text';

const v1 = {
	attributes: {
		content: {
			type: 'string',
			default: '',
		},
		mediaId: {
			type: 'number',
			default: 0,
		},
		mediaUrl: {
			type: 'string',
			default: '',
		},
		mediaType: {
			type: 'string',
			default: 'image',
		},
		linkUrl: {
			type: 'string',
			default: '',
		},
		position: {
			type: 'number',
			default: 0,
		},
		aiAdditionalInstructions: {
			type: 'string',
			default: '',
		},
		numberCheck: {
			type: 'object',
			default: null,
		},
	},
	isEligible(attributes: Record<string, unknown>, innerBlocks: unknown[]) {
		return (
			innerBlocks.length === 0 && typeof attributes.content === 'string'
		);
	},
	migrate(
		attributes: { content?: string } & Record<string, unknown>
	): [Record<string, unknown>, ReturnType<typeof createBlock>[]] {
		const { content = '', ...rest } = attributes;
		const paragraphs = splitCopyIntoParagraphs(String(content)).map(
			(paragraphContent) =>
				createBlock('core/paragraph', { content: paragraphContent })
		);
		return [rest, paragraphs];
	},
	save() {
		return null;
	},
};

export default [v1];
