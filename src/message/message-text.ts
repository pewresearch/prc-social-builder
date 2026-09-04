export const PARAGRAPH_BLOCK = 'core/paragraph';

export interface MessageInnerBlock {
	name?: string;
	blockName?: string;
	attributes?: Record<string, unknown> & {
		content?: string;
	};
	attrs?: Record<string, unknown> & {
		content?: string;
	};
	innerHTML?: string;
	innerBlocks?: MessageInnerBlock[];
}

/**
 * Publishable copy. Paragraphs win. Legacy `content` is only for empty inner blocks.
 *
 * @param block Message block or nothing.
 */
export function getMessageCopy(block?: MessageInnerBlock | null): string {
	if (!block) {
		return '';
	}
	const innerBlocks = block.innerBlocks ?? [];
	if (innerBlocks.length > 0) {
		return getPublishableText(innerBlocks);
	}
	const legacy = block.attributes?.content ?? block.attrs?.content ?? '';
	return String(legacy).trim();
}

export function getPublishableText(innerBlocks: MessageInnerBlock[]): string {
	return innerBlocks
		.filter((child) => getBlockName(child) === PARAGRAPH_BLOCK)
		.map((child) => plainTextFromParagraph(child))
		.filter((text) => text !== '')
		.join('\n\n');
}

export function splitCopyIntoParagraphs(text: string): string[] {
	const trimmed = text.trim();
	if (trimmed === '') {
		return [''];
	}
	const chunks = trimmed
		.split(/\n{2,}/)
		.map((chunk) => chunk.trim())
		.filter((chunk) => chunk !== '')
		.map((chunk) => escapeParagraphText(chunk).replace(/\n/g, '<br>'));
	return chunks.length > 0 ? chunks : [''];
}

function getBlockName(block: MessageInnerBlock): string {
	return block.name ?? block.blockName ?? '';
}

function plainTextFromParagraph(block: MessageInnerBlock): string {
	const html = String(
		block.attributes?.content ??
			block.attrs?.content ??
			block.innerHTML ??
			''
	);
	return decodeParagraphEntities(
		html.replace(/<br\s*\/?>\n?/gi, '\n').replace(/<[^>]+>/g, '')
	).trim();
}

function escapeParagraphText(text: string): string {
	return text
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;')
		.replace(/'/g, '&#039;');
}

function decodeParagraphEntities(text: string): string {
	return text
		.replace(/&nbsp;/gi, ' ')
		.replace(/&#039;/g, "'")
		.replace(/&apos;/g, "'")
		.replace(/&quot;/g, '"')
		.replace(/&lt;/g, '<')
		.replace(/&gt;/g, '>')
		.replace(/&amp;/g, '&');
}
