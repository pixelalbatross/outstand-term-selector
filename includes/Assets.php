<?php

namespace Outstand\WP\TermSelector;

class Assets extends BaseModule {
	use GetAssetInfo;

	/**
	 * Script and style handle.
	 *
	 * @var string
	 */
	const HANDLE = 'outstand-term-selector-editor';

	/**
	 * Taxonomy resolver.
	 *
	 * @var TermSelector
	 */
	private TermSelector $term_selector;

	/**
	 * Constructor.
	 *
	 * @param TermSelector $term_selector Taxonomy resolver.
	 */
	public function __construct( TermSelector $term_selector ) {
		$this->term_selector = $term_selector;
	}

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		$this->setup_asset_vars(
			dist_path: OUTSTAND_TERM_SELECTOR_DIST_PATH,
			fallback_version: OUTSTAND_TERM_SELECTOR_VERSION
		);

		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_scripts' ] );
	}

	/**
	 * Enqueue the editor bundle and its stylesheet.
	 *
	 * @return void
	 */
	public function enqueue_block_editor_scripts(): void {

		// Nothing opted in, so the bundle cannot change anything: skip the request.
		$taxonomies = $this->term_selector->get_taxonomies();

		if ( empty( $taxonomies ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			OUTSTAND_TERM_SELECTOR_DIST_URL . 'js/editor.js',
			$this->get_asset_info( 'editor', 'dependencies' ),
			$this->get_asset_info( 'editor', 'version' ),
			true
		);

		wp_enqueue_style(
			self::HANDLE,
			OUTSTAND_TERM_SELECTOR_DIST_URL . 'js/editor.css',
			[],
			$this->get_asset_info( 'editor', 'version' )
		);

		wp_style_add_data( self::HANDLE, 'rtl', 'replace' );

		wp_set_script_translations(
			self::HANDLE,
			'outstand-term-selector',
			OUTSTAND_TERM_SELECTOR_PATH . 'languages'
		);
	}
}
