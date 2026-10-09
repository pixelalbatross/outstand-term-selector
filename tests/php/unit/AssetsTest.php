<?php
/**
 * Tests for the editor asset loading and the settings passed to the bundle.
 *
 * @package Outstand\WP\TermSelector\Tests\Unit
 */

namespace Outstand\WP\TermSelector\Tests\Unit;

use Outstand\WP\TermSelector\Assets;
use Outstand\WP\TermSelector\TermSelector;

/**
 * Assets test case.
 */
class AssetsTest extends \WP_UnitTestCase {

	/**
	 * Taxonomies registered by the current test.
	 *
	 * @var array
	 */
	private array $registered = [];

	/**
	 * {@inheritDoc}
	 */
	public function set_up(): void {
		parent::set_up();

		$this->registered = [];

		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {

		foreach ( $this->registered as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}

		$this->registered = [];

		$GLOBALS['wp_scripts'] = null;
		$GLOBALS['wp_styles']  = null;

		parent::tear_down();
	}

	/**
	 * Nothing is enqueued when no taxonomy opts in.
	 *
	 * @return void
	 */
	public function test_nothing_is_enqueued_without_opt_in(): void {

		$this->register_taxonomy( 'ost_assets_out' );

		$this->make_assets( new TermSelector() )->enqueue_block_editor_scripts();

		$this->assertFalse( wp_script_is( Assets::HANDLE, 'enqueued' ) );
		$this->assertFalse( wp_style_is( Assets::HANDLE, 'enqueued' ) );
	}

	/**
	 * The script and stylesheet are enqueued when a taxonomy opts in.
	 *
	 * @return void
	 */
	public function test_script_and_style_are_enqueued_with_opt_in(): void {

		$this->register_taxonomy( 'ost_assets_in', [ 'use_hierarchical_selector' => true ] );

		$this->make_assets( new TermSelector() )->enqueue_block_editor_scripts();

		$this->assertTrue( wp_script_is( Assets::HANDLE, 'enqueued' ) );
		$this->assertTrue( wp_style_is( Assets::HANDLE, 'enqueued' ) );

		$style = wp_styles()->registered[ Assets::HANDLE ];

		$this->assertStringEndsWith( 'build/js/editor.css', $style->src );
		$this->assertSame( 'replace', $style->extra['rtl'] );
	}

	/**
	 * The resolved taxonomy list is printed before the bundle.
	 *
	 * @return void
	 */
	public function test_settings_are_passed_to_the_bundle(): void {

		$this->register_taxonomy( 'ost_assets_settings', [ 'use_hierarchical_selector' => true ] );

		$term_selector = new TermSelector();

		$this->make_assets( $term_selector )->enqueue_block_editor_scripts();
		$term_selector->enqueue_settings();

		$before = wp_scripts()->get_data( Assets::HANDLE, 'before' );

		$this->assertSame(
			[ 'var outstandTermSelector = {"taxonomies":["ost_assets_settings"]};' ],
			array_values( array_filter( $before ) )
		);
	}

	/**
	 * No settings are printed when the bundle is not enqueued.
	 *
	 * @return void
	 */
	public function test_settings_are_skipped_without_the_bundle(): void {

		$this->register_taxonomy( 'ost_assets_no_bundle', [ 'use_hierarchical_selector' => true ] );

		( new TermSelector() )->enqueue_settings();

		$this->assertFalse( wp_scripts()->get_data( Assets::HANDLE, 'before' ) );
	}

	/**
	 * Build an Assets module with its asset variables set, without hooking it.
	 *
	 * @param  TermSelector $term_selector Taxonomy resolver.
	 * @return Assets
	 */
	private function make_assets( TermSelector $term_selector ): Assets {

		$assets = new Assets( $term_selector );

		$assets->setup_asset_vars(
			dist_path: OUTSTAND_TERM_SELECTOR_DIST_PATH,
			fallback_version: OUTSTAND_TERM_SELECTOR_VERSION
		);

		return $assets;
	}

	/**
	 * Register a taxonomy for the duration of the test.
	 *
	 * @param  string $taxonomy Taxonomy slug.
	 * @param  array  $args     Optional. Extra registration arguments.
	 * @return void
	 */
	private function register_taxonomy( string $taxonomy, array $args = [] ): void {

		$defaults = [
			'public'       => true,
			'hierarchical' => false,
			'show_ui'      => true,
			'show_in_rest' => true,
		];

		register_taxonomy( $taxonomy, 'post', array_merge( $defaults, $args ) );

		$this->registered[] = $taxonomy;
	}
}
