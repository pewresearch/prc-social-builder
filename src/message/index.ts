import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import deprecated from './deprecated';
import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, {
	edit: Edit,
	save: Save,
	deprecated,
});
