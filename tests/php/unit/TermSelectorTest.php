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
