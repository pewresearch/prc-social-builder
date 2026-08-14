import { registerBlockType } from '@wordpress/blocks';
import metadata from './block.json';
import Edit from './edit';
import Save from './save';
import variations from './variations';
import './style.scss';
import './editor.scss';

registerBlockType(metadata.name, {
	edit: Edit,
	save: Save,
	variations,
});
