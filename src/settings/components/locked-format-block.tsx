import { __ } from '@wordpress/i18n';

export default function LockedFormatBlock({
	instruction,
}: {
	instruction: string;
}) {
	return (
		<div className="social-builder-settings__locked-format">
			<p className="social-builder-settings__locked-format-label">
				{__('Always appended — locked', 'prc-social-builder')}
			</p>
			<pre className="social-builder-settings__locked-format-pre">
				{instruction}
			</pre>
		</div>
	);
}
