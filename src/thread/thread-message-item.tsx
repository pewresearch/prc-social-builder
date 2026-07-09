import { __ } from '@wordpress/i18n';
import { AINumberCheckBadge } from '@prc/components';

export interface ThreadMessage {
	content: string;
	position: number;
	linkUrl?: string;
	numberCheck?: {
		valid: boolean;
		flagged: string[];
	};
}

interface ThreadMessageItemProps {
	message: ThreadMessage;
}

export function ThreadMessageItem({ message }: ThreadMessageItemProps) {
	return (
		<span style={{ fontSize: '13px', lineHeight: '1.5' }}>
			<strong style={{ display: 'block', marginBottom: '2px' }}>
				{__('Message', 'prc-social-builder')} {message.position}{' '}
				<AINumberCheckBadge numberCheck={message.numberCheck} />
			</strong>
			{message.content}
		</span>
	);
}
