import { __ } from '@wordpress/i18n';

export function FrontendReport() {
	return (
		<div className="prc-social-builder-frontend-report">
			<h4>{__('Social Package Status', 'prc-social-builder')}</h4>
			<p>
				{__(
					'Social package information will appear here.',
					'prc-social-builder'
				)}
			</p>
		</div>
	);
}

export default FrontendReport;
