/**
 * Outstand Term Selector — editor entry.
 *
 * Swaps the free-text tag input WordPress renders for non-hierarchical
 * taxonomies with the core hierarchical (checkbox tree) term selector, for the
 * taxonomies that opted in on the server. Opt-in only — an empty list leaves
 * every taxonomy panel untouched.
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
import './editor.css';

const settings = window.outstandTermSelector || {};
const slugs = Array.isArray( settings.taxonomies ) ? settings.taxonomies : [];

if ( slugs.length ) {
	addFilter(
		'editor.PostTaxonomyType',
		'outstand/term-selector',
		( OriginalComponent ) => ( props ) => {
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
