<?php
/**
 * Tests for the taxonomy opt-in resolution.
 *
 * @package Outstand\WP\TermSelector\Tests\Unit
 */

namespace Outstand\WP\TermSelector\Tests\Unit;

use Outstand\WP\TermSelector\TermSelector;

/**
 * TermSelector test case.
 */
class TermSelectorTest extends \WP_UnitTestCase {

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
	}

	/**
	 * {@inheritDoc}
	 */
	public function tear_down(): void {

		foreach ( $this->registered as $taxonomy ) {
			unregister_taxonomy( $taxonomy );
		}

		$this->registered = [];

		remove_all_filters( 'outstand_term_selector_taxonomies' );
		remove_all_filters( 'outstand_term_selector_radio_taxonomies' );
		remove_all_filters( 'outstand_term_selector_radio_show_clear' );

		parent::tear_down();
	}

	/**
	 * Taxonomies opting in at registration time are collected.
	 *
	 * @return void
	 */
	public function test_registration_property_opts_a_taxonomy_in(): void {

		$this->register_taxonomy( 'ost_flat_in', [ 'use_hierarchical_selector' => true ] );

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [ 'ost_flat_in' ], $taxonomies );
	}

	/**
	 * Nothing is collected when no taxonomy opts in.
	 *
	 * @return void
	 */
	public function test_nothing_is_collected_without_opt_in(): void {

		$this->register_taxonomy( 'ost_flat_out' );

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * Hierarchical taxonomies are excluded even when they opt in.
	 *
	 * @return void
	 */
	public function test_hierarchical_taxonomy_is_excluded(): void {

		$this->register_taxonomy(
			'ost_tree',
			[
				'hierarchical'              => true,
				'use_hierarchical_selector' => true,
			]
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertNotContains( 'ost_tree', $taxonomies );
	}

	/**
	 * Taxonomies hidden from REST are excluded even when they opt in.
	 *
	 * @return void
	 */
	public function test_taxonomy_without_rest_support_is_excluded(): void {

		$this->register_taxonomy(
			'ost_no_rest',
			[
				'show_in_rest'              => false,
				'use_hierarchical_selector' => true,
			]
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertNotContains( 'ost_no_rest', $taxonomies );
	}

	/**
	 * Taxonomies hidden from the admin UI are excluded even when they opt in.
	 *
	 * @return void
	 */
	public function test_taxonomy_without_ui_is_excluded(): void {

		$this->register_taxonomy(
			'ost_no_ui',
			[
				'show_ui'                   => false,
				'use_hierarchical_selector' => true,
			]
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertNotContains( 'ost_no_ui', $taxonomies );
	}

	/**
	 * The filter can add a taxonomy that did not opt in at registration.
	 *
	 * @return void
	 */
	public function test_filter_can_add_a_taxonomy(): void {

		$this->register_taxonomy( 'ost_third_party' );

		add_filter(
			'outstand_term_selector_taxonomies',
			function ( $slugs ) {
				$slugs[] = 'ost_third_party';
				return $slugs;
			}
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [ 'ost_third_party' ], $taxonomies );
	}

	/**
	 * The filter can remove a taxonomy that opted in at registration.
	 *
	 * @return void
	 */
	public function test_filter_can_remove_a_taxonomy(): void {

		$this->register_taxonomy( 'ost_kept', [ 'use_hierarchical_selector' => true ] );
		$this->register_taxonomy( 'ost_dropped', [ 'use_hierarchical_selector' => true ] );

		add_filter(
			'outstand_term_selector_taxonomies',
			function ( $slugs ) {
				return array_values( array_diff( $slugs, [ 'ost_dropped' ] ) );
			}
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [ 'ost_kept' ], $taxonomies );
	}

	/**
	 * The filter cannot force through a hierarchical taxonomy.
	 *
	 * @return void
	 */
	public function test_filter_cannot_force_a_hierarchical_taxonomy(): void {

		$this->register_taxonomy( 'ost_forced_tree', [ 'hierarchical' => true ] );

		add_filter(
			'outstand_term_selector_taxonomies',
			function ( $slugs ) {
				$slugs[] = 'ost_forced_tree';
				return $slugs;
			}
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * The filter cannot force through a taxonomy hidden from the admin UI.
	 *
	 * @return void
	 */
	public function test_filter_cannot_force_a_taxonomy_without_ui(): void {

		$this->register_taxonomy( 'ost_forced_no_ui', [ 'show_ui' => false ] );

		add_filter(
			'outstand_term_selector_taxonomies',
			function ( $slugs ) {
				$slugs[] = 'ost_forced_no_ui';
				return $slugs;
			}
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * The filter cannot force through a taxonomy hidden from REST.
	 *
	 * @return void
	 */
	public function test_filter_cannot_force_a_taxonomy_without_rest_support(): void {

		$this->register_taxonomy( 'ost_forced_no_rest', [ 'show_in_rest' => false ] );

		add_filter(
			'outstand_term_selector_taxonomies',
			function ( $slugs ) {
				$slugs[] = 'ost_forced_no_rest';
				return $slugs;
			}
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * Slugs of unregistered taxonomies returned by the filter are discarded.
	 *
	 * @return void
	 */
	public function test_filter_unregistered_slug_is_discarded(): void {

		$this->register_taxonomy( 'ost_real', [ 'use_hierarchical_selector' => true ] );

		add_filter(
			'outstand_term_selector_taxonomies',
			function ( $slugs ) {
				$slugs[] = 'ost_not_registered';
				return $slugs;
			}
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [ 'ost_real' ], $taxonomies );
	}

	/**
	 * Non-string entries returned by the filter are discarded.
	 *
	 * @return void
	 */
	public function test_filter_non_string_entries_are_discarded(): void {

		$this->register_taxonomy( 'ost_string', [ 'use_hierarchical_selector' => true ] );

		add_filter(
			'outstand_term_selector_taxonomies',
			function ( $slugs ) {
				return array_merge( $slugs, [ 42, null, [ 'ost_string' ], false ] );
			}
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [ 'ost_string' ], $taxonomies );
	}

	/**
	 * A filter that returns something other than an array opts everything out.
	 *
	 * @return void
	 */
	public function test_filter_non_array_return_yields_empty_list(): void {

		$this->register_taxonomy( 'ost_overridden', [ 'use_hierarchical_selector' => true ] );

		add_filter( 'outstand_term_selector_taxonomies', '__return_false' );

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * Duplicate slugs returned by the filter are collapsed.
	 *
	 * @return void
	 */
	public function test_filter_duplicate_slugs_are_collapsed(): void {

		$this->register_taxonomy( 'ost_twice', [ 'use_hierarchical_selector' => true ] );

		add_filter(
			'outstand_term_selector_taxonomies',
			function ( $slugs ) {
				$slugs[] = 'ost_twice';
				return $slugs;
			}
		);

		$taxonomies = ( new TermSelector() )->get_taxonomies();

		$this->assertSame( [ 'ost_twice' ], $taxonomies );
	}

	/**
	 * Flat taxonomies opting in to radio mode at registration time are collected.
	 *
	 * @return void
	 */
	public function test_radio_registration_property_opts_a_flat_taxonomy_in(): void {

		$this->register_taxonomy( 'ost_radio_flat', [ 'use_radio_selector' => true ] );

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [ 'ost_radio_flat' ], $taxonomies );
	}

	/**
	 * Hierarchical taxonomies can opt in to radio mode.
	 *
	 * @return void
	 */
	public function test_radio_registration_property_opts_a_hierarchical_taxonomy_in(): void {

		$this->register_taxonomy(
			'ost_radio_tree',
			[
				'hierarchical'       => true,
				'use_radio_selector' => true,
			]
		);

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [ 'ost_radio_tree' ], $taxonomies );
	}

	/**
	 * Nothing is collected for radio mode when no taxonomy opts in.
	 *
	 * @return void
	 */
	public function test_radio_nothing_is_collected_without_opt_in(): void {

		$this->register_taxonomy( 'ost_radio_out' );
		$this->register_taxonomy( 'ost_checkbox_only', [ 'use_hierarchical_selector' => true ] );

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * Taxonomies hidden from REST are excluded from radio mode even when they opt in.
	 *
	 * @return void
	 */
	public function test_radio_taxonomy_without_rest_support_is_excluded(): void {

		$this->register_taxonomy(
			'ost_radio_no_rest',
			[
				'show_in_rest'       => false,
				'use_radio_selector' => true,
			]
		);

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * Taxonomies hidden from the admin UI are excluded from radio mode even when they opt in.
	 *
	 * @return void
	 */
	public function test_radio_taxonomy_without_ui_is_excluded(): void {

		$this->register_taxonomy(
			'ost_radio_no_ui',
			[
				'show_ui'            => false,
				'use_radio_selector' => true,
			]
		);

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * The radio filter can add a hierarchical taxonomy that did not opt in at registration.
	 *
	 * @return void
	 */
	public function test_radio_filter_can_add_a_hierarchical_taxonomy(): void {

		$this->register_taxonomy( 'ost_radio_third_party', [ 'hierarchical' => true ] );

		add_filter(
			'outstand_term_selector_radio_taxonomies',
			function ( $slugs ) {
				$slugs[] = 'ost_radio_third_party';
				return $slugs;
			}
		);

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [ 'ost_radio_third_party' ], $taxonomies );
	}

	/**
	 * The radio filter can remove a taxonomy that opted in at registration.
	 *
	 * @return void
	 */
	public function test_radio_filter_can_remove_a_taxonomy(): void {

		$this->register_taxonomy( 'ost_radio_kept', [ 'use_radio_selector' => true ] );
		$this->register_taxonomy( 'ost_radio_dropped', [ 'use_radio_selector' => true ] );

		add_filter(
			'outstand_term_selector_radio_taxonomies',
			function ( $slugs ) {
				return array_values( array_diff( $slugs, [ 'ost_radio_dropped' ] ) );
			}
		);

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [ 'ost_radio_kept' ], $taxonomies );
	}

	/**
	 * The radio filter cannot force through unsupported or unregistered taxonomies.
	 *
	 * @return void
	 */
	public function test_radio_filter_discards_unsupported_slugs(): void {

		$this->register_taxonomy( 'ost_radio_forced_no_ui', [ 'show_ui' => false ] );
		$this->register_taxonomy( 'ost_radio_forced_no_rest', [ 'show_in_rest' => false ] );

		add_filter(
			'outstand_term_selector_radio_taxonomies',
			function () {
				return [ 'ost_radio_forced_no_ui', 'ost_radio_forced_no_rest', 'ost_radio_not_registered', 42 ];
			}
		);

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * A radio filter that returns something other than an array opts everything out.
	 *
	 * @return void
	 */
	public function test_radio_filter_non_array_return_yields_empty_list(): void {

		$this->register_taxonomy( 'ost_radio_overridden', [ 'use_radio_selector' => true ] );

		add_filter( 'outstand_term_selector_radio_taxonomies', '__return_false' );

		$taxonomies = ( new TermSelector() )->get_radio_taxonomies();

		$this->assertSame( [], $taxonomies );
	}

	/**
	 * A taxonomy that opts in to both modes uses only radio mode.
	 *
	 * @return void
	 */
	public function test_radio_mode_has_precedence_over_the_hierarchical_selector(): void {

		$this->register_taxonomy(
			'ost_both',
			[
				'use_hierarchical_selector' => true,
				'use_radio_selector'        => true,
			]
		);
		$this->register_taxonomy( 'ost_checkbox', [ 'use_hierarchical_selector' => true ] );

		$term_selector = new TermSelector();

		$this->assertSame( [ 'ost_checkbox' ], $term_selector->get_taxonomies() );
		$this->assertSame( [ 'ost_both' ], $term_selector->get_radio_taxonomies() );
	}

	/**
	 * Every radio taxonomy shows the "Clear" button by default.
	 *
	 * @return void
	 */
	public function test_radio_clear_is_shown_by_default(): void {

		$this->register_taxonomy( 'ost_clear_flat', [ 'use_radio_selector' => true ] );
		$this->register_taxonomy(
			'ost_clear_tree',
			[
				'hierarchical'       => true,
				'use_radio_selector' => true,
			]
		);

		$show_clear = ( new TermSelector() )->get_radio_clear();

		$this->assertSame(
			[
				'ost_clear_flat' => true,
				'ost_clear_tree' => true,
			],
			$show_clear
		);
	}

	/**
	 * The filter can hide the "Clear" button for one taxonomy.
	 *
	 * @return void
	 */
	public function test_radio_clear_filter_can_hide_the_button_per_taxonomy(): void {

		$this->register_taxonomy( 'ost_clear_kept', [ 'use_radio_selector' => true ] );
		$this->register_taxonomy( 'ost_clear_hidden', [ 'use_radio_selector' => true ] );

		add_filter(
			'outstand_term_selector_radio_show_clear',
			function ( $show, $taxonomy ) {
				return 'ost_clear_hidden' === $taxonomy ? false : $show;
			},
			10,
			2
		);

		$show_clear = ( new TermSelector() )->get_radio_clear();

		$this->assertSame(
			[
				'ost_clear_kept'   => true,
				'ost_clear_hidden' => false,
			],
			$show_clear
		);
	}

	/**
	 * The filter runs only for radio taxonomies.
	 *
	 * @return void
	 */
	public function test_radio_clear_ignores_other_taxonomies(): void {

		$this->register_taxonomy( 'ost_clear_checkbox', [ 'use_hierarchical_selector' => true ] );
		$this->register_taxonomy( 'ost_clear_none' );

		$show_clear = ( new TermSelector() )->get_radio_clear();

		$this->assertSame( [], $show_clear );
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
