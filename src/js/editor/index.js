/**
 * Outstand Term Selector — editor entry.
 *
 * Swaps the block editor taxonomy panel for the taxonomies that opted in on
 * the server:
 *
 * - `taxonomies` get the core hierarchical (checkbox tree) term selector in
 *   place of the free-text tag input.
 * - `radioTaxonomies` get the radio term selector, which lets editors select
 *   one term per post. `radioClear` sets which of them show the "Clear"
 *   button.
 *
 * Opt-in only — empty lists leave every taxonomy panel untouched.
 *
 * Source compiles to build/js/editor.js via wp-scripts. Enqueued by the Assets
 * module with the handle "outstand-term-selector-editor".
 */

/**
 * WordPress dependencies
 */
import { PostTaxonomiesHierarchicalTermSelector } from '@wordpress/editor';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import RadioTermSelector from './radio-term-selector';
import './editor.css';

const settings = window.outstandTermSelector || {};
const slugs = Array.isArray( settings.taxonomies ) ? settings.taxonomies : [];
const radioSlugs = Array.isArray( settings.radioTaxonomies )
	? settings.radioTaxonomies
	: [];
const radioClear =
	settings.radioClear && typeof settings.radioClear === 'object'
		? settings.radioClear
		: {};

if ( slugs.length || radioSlugs.length ) {
	addFilter(
		'editor.PostTaxonomyType',
		'outstand/term-selector',
		( OriginalComponent ) => ( props ) => {
			if ( radioSlugs.includes( props.slug ) ) {
				return (
					<RadioTermSelector
						{ ...props }
						showClear={ radioClear[ props.slug ] !== false }
					/>
				);
			}

			if ( slugs.includes( props.slug ) ) {
				return (
					<div className="outstand-term-selector">
						<PostTaxonomiesHierarchicalTermSelector { ...props } />
					</div>
				);
			}

			return <OriginalComponent { ...props } />;
		}
	);
}
