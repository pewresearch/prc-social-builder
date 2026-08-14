import { __ } from '@wordpress/i18n';
import { AINumberCheckBadge } from '@prc/components';

export interface SocialCopyMessage {
	additionalInstructions: string;
	copy: string;
	platform: string;
	id?: number ;
	numberCheck?: {
		valid: boolean;
		flagged: string[];
	};
	error?: boolean;
}

interface SocialCopyMessageItemProps {
	message: SocialCopyMessage;
}

export function SocialCopyMessageItem({ message }: SocialCopyMessageItemProps) {
	return (
		<span style={{ fontSize: '13px', lineHeight: '1.5' }}>
			<strong style={{ display: 'block', marginBottom: '2px', textTransform: 'capitalize' }}>
				Social Copy for {message.platform}
			</strong>
			<em style={{ display: 'block', marginBottom: '2px' }}>
				{__('Number Check?', 'prc-social-builder')}{' '}
				<AINumberCheckBadge numberCheck={message.numberCheck} />
			</em>
			{message.copy}
		</span>
	);
}
