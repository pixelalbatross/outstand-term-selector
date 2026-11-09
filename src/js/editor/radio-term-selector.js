/**
 * Radio term selector.
 *
 * Lets editors select one term per post. Hierarchical taxonomies show child
 * terms indented under their parent.
 *
 * RadioControl renders a flat list of options, so the tree uses native radio
 * inputs with the core RadioControl and taxonomy panel class names. The inputs
 * share one name, so they behave as one radio group.
 */

/**
 * WordPress dependencies
 */
import { speak } from '@wordpress/a11y';
import {
	Button,
	Flex,
	FlexItem,
	SearchControl,
	Spinner,
	TextControl,
	TreeSelect,
} from '@wordpress/components';
import { useDebounce, useInstanceId } from '@wordpress/compose';
import { store as coreStore } from '@wordpress/core-data';
import { useDispatch, useSelect } from '@wordpress/data';
import { store as editorStore } from '@wordpress/editor';
import { useMemo, useState } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

const TERMS_QUERY = {
	per_page: -1,
	orderby: 'name',
	order: 'asc',
	_fields: 'id,name,parent',
	context: 'view',
};

const MIN_TERMS_COUNT_FOR_FILTER = 8;

const EMPTY_ARRAY = [];

/**
 * Normalize a string for a case-insensitive and accent-insensitive search.
 *
 * @param {string} value String to normalize.
 * @return {string} Normalized string.
 */
const normalize = ( value ) =>
	value
		.normalize( 'NFD' )
		.replace( /[\u0300-\u036f]/g, '' )
		.toLowerCase();

/**
 * Build a tree of terms from a flat list.
 *
 * A term whose parent is not in the list is a root term.
 *
 * @param {Array} flatTerms Terms with `id`, `name`, and `parent`.
 * @return {Array} Root terms, each with a `children` array.
 */
const buildTermsTree = ( flatTerms ) => {
	const nodes = new Map(
		flatTerms.map( ( term ) => [ term.id, { ...term, children: [] } ] )
	);

	const tree = [];

	nodes.forEach( ( node ) => {
		const parent = nodes.get( node.parent );

		if ( parent ) {
			parent.children.push( node );
		} else {
			tree.push( node );
		}
	} );

	return tree;
};

/**
 * Move the root term that holds the selected term to the top of the tree.
 *
 * @param {Array}  tree       Root terms.
 * @param {number} selectedId Selected term ID.
 * @return {Array} Sorted root terms.
 */
const sortBySelected = ( tree, selectedId ) => {
	const hasSelection = ( node ) =>
		node.id === selectedId || node.children.some( hasSelection );

	return [ ...tree ].sort(
		( a, b ) => Number( hasSelection( b ) ) - Number( hasSelection( a ) )
	);
};

/**
 * Keep the terms that match a search, and the parents of those terms.
 *
 * @param {Array}  tree   Root terms.
 * @param {string} search Search text.
 * @return {Array} Matching root terms.
 */
const filterTree = ( tree, search ) => {
	const needle = normalize( search );

	const match = ( node ) => {
		const children = node.children.map( match ).filter( Boolean );

		if ( children.length || normalize( node.name ).includes( needle ) ) {
			return { ...node, children };
		}

		return null;
	};

	return tree.map( match ).filter( Boolean );
};

/**
 * Count the terms in a tree.
 *
 * @param {Array} tree Root terms.
 * @return {number} Number of terms.
 */
const countTerms = ( tree ) =>
	tree.reduce(
		( count, node ) => count + 1 + countTerms( node.children ),
		0
	);

/**
 * Find a term by name and parent.
 *
 * @param {Array}  terms  Terms with `id`, `name`, and `parent`.
 * @param {string} parent Parent term ID, or an empty string for no parent.
 * @param {string} name   Term name.
 * @return {Object|undefined} Matching term.
 */
const findTerm = ( terms, parent, name ) =>
	terms.find(
		( term ) =>
			( ( ! term.parent && ! parent ) ||
				parseInt( term.parent, 10 ) === parseInt( parent, 10 ) ) &&
			term.name.toLowerCase() === name.toLowerCase()
	);

/**
 * Radio term selector panel.
 *
 * @param {Object}  props           Component props.
 * @param {string}  props.slug      Taxonomy slug.
 * @param {boolean} props.showClear Whether to show the "Clear" button.
 * @return {Element|null} Panel content.
 */
export default function RadioTermSelector( { slug, showClear = true } ) {
	const [ adding, setAdding ] = useState( false );
	const [ formName, setFormName ] = useState( '' );
	const [ formParent, setFormParent ] = useState( '' );
	const [ showForm, setShowForm ] = useState( false );
	const [ search, setSearch ] = useState( '' );

	const groupName = useInstanceId(
		RadioTermSelector,
		'outstand-radio-term-selector'
	);

	const debouncedSpeak = useDebounce( speak, 500 );

	const {
		hasCreateAction,
		hasAssignAction,
		terms,
		loading,
		availableTerms,
		taxonomy,
	} = useSelect(
		( select ) => {
			const { getCurrentPost, getEditedPostAttribute } =
				select( editorStore );
			const { getEntityRecord, getEntityRecords, isResolving } =
				select( coreStore );

			const _taxonomy = getEntityRecord( 'root', 'taxonomy', slug );
			const post = getCurrentPost();

			return {
				hasCreateAction: _taxonomy
					? !! post._links?.[
							'wp:action-create-' + _taxonomy.rest_base
					  ]
					: false,
				hasAssignAction: _taxonomy
					? !! post._links?.[
							'wp:action-assign-' + _taxonomy.rest_base
					  ]
					: false,
				terms: _taxonomy
					? getEditedPostAttribute( _taxonomy.rest_base ) ||
					  EMPTY_ARRAY
					: EMPTY_ARRAY,
				loading: isResolving( 'getEntityRecords', [
					'taxonomy',
					slug,
					TERMS_QUERY,
				] ),
				availableTerms:
					getEntityRecords( 'taxonomy', slug, TERMS_QUERY ) ||
					EMPTY_ARRAY,
				taxonomy: _taxonomy,
			};
		},
		[ slug ]
	);

	const { editPost } = useDispatch( editorStore );
	const { saveEntityRecord } = useDispatch( coreStore );
	const { createErrorNotice } = useDispatch( noticesStore );

	// A post can hold several terms from before radio mode. The first one
	// shows as selected until the editor changes the selection.
	const selectedId = terms[ 0 ];

	const termsTree = useMemo(
		() => sortBySelected( buildTermsTree( availableTerms ), selectedId ),
		// Sort only when the terms load, so the list does not move when the
		// editor selects a term.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ availableTerms ]
	);

	if ( ! hasAssignAction ) {
		return null;
	}

	const onUpdateTerms = ( termIds ) => {
		editPost( { [ taxonomy.rest_base ]: termIds } );
	};

	const onSearch = ( value ) => {
		setSearch( value );

		const resultCount = countTerms( filterTree( termsTree, value ) );

		debouncedSpeak(
			sprintf(
				/* translators: %d: number of results. */
				_n(
					'%d result found.',
					'%d results found.',
					resultCount,
					'outstand-term-selector'
				),
				resultCount
			),
			'assertive'
		);
	};

	const labelWithFallback = (
		labelProperty,
		fallbackIsCategory,
		fallbackIsNotCategory
	) =>
		taxonomy?.labels?.[ labelProperty ] ??
		( slug === 'category' ? fallbackIsCategory : fallbackIsNotCategory );

	const onAddTerm = async ( event ) => {
		event.preventDefault();

		if ( formName === '' || adding ) {
			return;
		}

		const existingTerm = findTerm( availableTerms, formParent, formName );

		if ( existingTerm ) {
			onUpdateTerms( [ existingTerm.id ] );
			setFormName( '' );
			setFormParent( '' );
			return;
		}

		setAdding( true );

		let newTerm;

		try {
			newTerm = await saveEntityRecord(
				'taxonomy',
				slug,
				{
					name: formName,
					parent: formParent ? formParent : undefined,
				},
				{ throwOnError: true }
			);
		} catch ( error ) {
			createErrorNotice( error.message, { type: 'snackbar' } );
			return;
		} finally {
			setAdding( false );
		}

		speak(
			sprintf(
				/* translators: %s: term name. */
				_x( '%s added', 'term', 'outstand-term-selector' ),
				labelWithFallback(
					'singular_name',
					__( 'Category', 'outstand-term-selector' ),
					__( 'Term', 'outstand-term-selector' )
				)
			),
			'assertive'
		);

		setFormName( '' );
		setFormParent( '' );
		onUpdateTerms( [ newTerm.id ] );
	};

	const renderTerms = ( nodes ) =>
		nodes.map( ( node ) => {
			const inputId = `${ groupName }-${ node.id }`;

			return (
				<div
					key={ node.id }
					className="editor-post-taxonomies__hierarchical-terms-choice"
				>
					<div className="components-radio-control__option">
						<input
							id={ inputId }
							className="components-radio-control__input"
							type="radio"
							name={ groupName }
							value={ node.id }
							checked={ node.id === selectedId }
							onChange={ () => onUpdateTerms( [ node.id ] ) }
						/>
						<label
							className="components-radio-control__label"
							htmlFor={ inputId }
						>
							{ decodeEntities( node.name ) }
						</label>
					</div>
					{ !! node.children.length && (
						<div className="editor-post-taxonomies__hierarchical-terms-subchoices">
							{ renderTerms( node.children ) }
						</div>
					) }
				</div>
			);
		} );

	const newTermButtonLabel = labelWithFallback(
		'add_new_item',
		__( 'Add Category', 'outstand-term-selector' ),
		__( 'Add Term', 'outstand-term-selector' )
	);
	const newTermLabel = labelWithFallback(
		'new_item_name',
		__( 'Add Category', 'outstand-term-selector' ),
		__( 'Add Term', 'outstand-term-selector' )
	);
	const parentSelectLabel = labelWithFallback(
		'parent_item',
		__( 'Parent Category', 'outstand-term-selector' ),
		__( 'Parent Term', 'outstand-term-selector' )
	);
	const searchLabel =
		taxonomy?.labels?.search_items ??
		__( 'Search Terms', 'outstand-term-selector' );
	const groupLabel =
		taxonomy?.name ?? __( 'Terms', 'outstand-term-selector' );

	const showSearch = availableTerms.length >= MIN_TERMS_COUNT_FOR_FILTER;

	// Flat taxonomies cannot store a parent, and the REST API rejects a new
	// term that has one.
	const showParentSelect =
		!! taxonomy?.hierarchical && !! availableTerms.length;

	return (
		<Flex
			className="outstand-radio-term-selector"
			direction="column"
			gap="4"
		>
			{ showSearch && ! loading && (
				<SearchControl
					label={ searchLabel }
					placeholder={ searchLabel }
					value={ search }
					onChange={ onSearch }
				/>
			) }
			{ loading && (
				<Flex
					justify="center"
					style={ {
						// Match the SearchControl height to prevent a layout shift.
						height: '40px',
					} }
				>
					<Spinner />
				</Flex>
			) }
			<div
				className="editor-post-taxonomies__hierarchical-terms-list"
				role="radiogroup"
				aria-label={ groupLabel }
			>
				{ renderTerms(
					search !== '' ? filterTree( termsTree, search ) : termsTree
				) }
			</div>
			{ showClear && !! terms.length && (
				<FlexItem>
					<Button
						__next40pxDefaultSize
						variant="link"
						onClick={ () => onUpdateTerms( [] ) }
					>
						{ __( 'Clear', 'outstand-term-selector' ) }
					</Button>
				</FlexItem>
			) }
			{ ! loading && hasCreateAction && (
				<FlexItem>
					<Button
						__next40pxDefaultSize
						variant="link"
						className="editor-post-taxonomies__hierarchical-terms-add"
						aria-expanded={ showForm }
						onClick={ () => setShowForm( ! showForm ) }
					>
						{ newTermButtonLabel }
					</Button>
				</FlexItem>
			) }
			{ showForm && (
				<form onSubmit={ onAddTerm }>
					<Flex direction="column" gap="4">
						<TextControl
							__next40pxDefaultSize
							className="editor-post-taxonomies__hierarchical-terms-input"
							label={ newTermLabel }
							value={ formName }
							onChange={ setFormName }
							required
						/>
						{ showParentSelect && (
							<TreeSelect
								__next40pxDefaultSize
								label={ parentSelectLabel }
								noOptionLabel={ `— ${ parentSelectLabel } —` }
								onChange={ setFormParent }
								selectedId={ formParent }
								tree={ termsTree }
							/>
						) }
						<FlexItem>
							<Button
								__next40pxDefaultSize
								variant="secondary"
								type="submit"
								className="editor-post-taxonomies__hierarchical-terms-submit"
							>
								{ newTermButtonLabel }
							</Button>
						</FlexItem>
					</Flex>
				</form>
			) }
		</Flex>
	);
}
