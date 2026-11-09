<?php

namespace Outstand\WP\TermSelector;

defined( 'ABSPATH' ) || exit;

use WP_Taxonomy;

/**
 * Resolves which taxonomies swap their block editor panel for one of the
 * plugin's term selectors:
 *
 * - The hierarchical (checkbox tree) term selector, for non-hierarchical
 *   taxonomies.
 * - The radio term selector, for flat and hierarchical taxonomies. It lets
 *   editors select one term per post.
 *
 * Opt-in only. The plugin never applies a selector to every taxonomy on a
 * site: flat taxonomies exist precisely so editors can invent terms, and
 * silently removing that ability everywhere would break editorial workflows
 * the site owner never asked us to touch.
 */
class TermSelector extends BaseModule {

	/**
	 * Name of the JavaScript global carrying the resolved taxonomy list.
	 *
	 * @var string
	 */
	const SCRIPT_VAR = 'outstandTermSelector';

	/**
	 * Memoized list of taxonomy slugs that use the hierarchical term selector.
	 *
	 * @var ?array
	 */
	private ?array $taxonomies = null;

	/**
	 * Memoized list of taxonomy slugs that use the radio term selector.
	 *
	 * @var ?array
	 */
	private ?array $radio_taxonomies = null;

	/**
	 * {@inheritDoc}
	 */
	public function register(): void {
		// Runs after Assets has enqueued the bundle on the same hook.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_settings' ], 20 );
	}

	/**
	 * Get the taxonomy slugs that should use the hierarchical term selector.
	 *
	 * Resolution has two opt-in paths:
	 *
	 * 1. A `use_hierarchical_selector` argument passed to `register_taxonomy()`,
	 *    for consumers who own the registration.
	 * 2. The `outstand_term_selector_taxonomies` filter, for consumers who do
	 *    not own the registration (a third-party CPT/taxonomy plugin).
	 *
	 * Whatever the filter returns is validated again, so a taxonomy that is
	 * hierarchical, hidden from the editor, or absent from REST can never be
	 * forced through.
	 *
	 * A taxonomy that also uses the radio term selector is removed from this
	 * list, because the radio term selector has precedence.
	 *
	 * @return array List of taxonomy slugs.
	 */
	public function get_taxonomies(): array {

		if ( ! is_null( $this->taxonomies ) ) {
			return $this->taxonomies;
		}

		$slugs = $this->collect( 'use_hierarchical_selector', false );

		/**
		 * Filters the taxonomies that use the hierarchical term selector.
		 *
		 * Use this to opt taxonomies in or out when you do not control the
		 * `register_taxonomy()` call. Slugs that are not registered, are
		 * already hierarchical, are hidden from the editor, or are not exposed
		 * to the REST API are discarded after this filter runs.
		 *
		 * @since 1.0.0
		 *
		 * @param array $slugs List of taxonomy slugs.
		 */
		$filtered = apply_filters( 'outstand_term_selector_taxonomies', $slugs );

		$validated = $this->validate( $filtered, false );

		$this->taxonomies = array_values( array_diff( $validated, $this->get_radio_taxonomies() ) );

		return $this->taxonomies;
	}

	/**
	 * Get the taxonomy slugs that should use the radio term selector.
	 *
	 * Resolution has two opt-in paths:
	 *
	 * 1. A `use_radio_selector` argument passed to `register_taxonomy()`, for
	 *    consumers who own the registration.
	 * 2. The `outstand_term_selector_radio_taxonomies` filter, for consumers
	 *    who do not own the registration.
	 *
	 * Flat and hierarchical taxonomies are both allowed. Whatever the filter
	 * returns is validated again, so a taxonomy that is hidden from the editor
	 * or absent from REST can never be forced through.
	 *
	 * @return array List of taxonomy slugs.
	 */
	public function get_radio_taxonomies(): array {

		if ( ! is_null( $this->radio_taxonomies ) ) {
			return $this->radio_taxonomies;
		}

		$slugs = $this->collect( 'use_radio_selector', true );

		/**
		 * Filters the taxonomies that use the radio term selector.
		 *
		 * Use this to opt taxonomies in or out when you do not control the
		 * `register_taxonomy()` call. Slugs that are not registered, are
		 * hidden from the editor, or are not exposed to the REST API are
		 * discarded after this filter runs.
		 *
		 * @since 1.1.0
		 *
		 * @param array $slugs List of taxonomy slugs.
		 */
		$filtered = apply_filters( 'outstand_term_selector_radio_taxonomies', $slugs );

		$this->radio_taxonomies = $this->validate( $filtered, true );

		return $this->radio_taxonomies;
	}

	/**
	 * Get which radio taxonomies show the "Clear" button.
	 *
	 * @return array Map of taxonomy slug to a boolean.
	 */
	public function get_radio_clear(): array {

		$show_clear = [];

		foreach ( $this->get_radio_taxonomies() as $taxonomy ) {

			/**
			 * Filters whether the radio term selector shows the "Clear" button.
			 *
			 * The "Clear" button removes all terms of the taxonomy from the
			 * post. Return false so that an editor can change the term but
			 * cannot remove it.
			 *
			 * @since 1.1.0
			 *
			 * @param bool   $show     Whether to show the "Clear" button. Default true.
			 * @param string $taxonomy Taxonomy slug.
			 */
			$show_clear[ $taxonomy ] = (bool) apply_filters( 'outstand_term_selector_radio_show_clear', true, $taxonomy );
		}

		return $show_clear;
	}

	/**
	 * Pass the resolved taxonomy lists to the editor bundle.
	 *
	 * @return void
	 */
	public function enqueue_settings(): void {

		if ( ! wp_script_is( Assets::HANDLE, 'enqueued' ) ) {
			return;
		}

		$settings = [
			'taxonomies'      => $this->get_taxonomies(),
			'radioTaxonomies' => $this->get_radio_taxonomies(),
			// An object, so that an empty map encodes as `{}` in JSON.
			'radioClear'      => (object) $this->get_radio_clear(),
		];

		wp_add_inline_script(
			Assets::HANDLE,
			sprintf(
				'var %1$s = %2$s;',
				self::SCRIPT_VAR,
				wp_json_encode( $settings )
			),
			'before'
		);
	}

	/**
	 * Collect the slugs of supported taxonomies that set a registration argument.
	 *
	 * @param  string $argument           Name of the `register_taxonomy()` argument.
	 * @param  bool   $allow_hierarchical Whether hierarchical taxonomies are supported.
	 * @return array
	 */
	private function collect( string $argument, bool $allow_hierarchical ): array {

		$slugs = [];

		foreach ( get_taxonomies( [], 'objects' ) as $taxonomy ) {

			if ( ! $this->is_supported( $taxonomy, $allow_hierarchical ) ) {
				continue;
			}

			if ( empty( $taxonomy->$argument ) ) {
				continue;
			}

			$slugs[] = $taxonomy->name;
		}

		return $slugs;
	}

	/**
	 * Whether a taxonomy can use a term selector.
	 *
	 * @param  WP_Taxonomy $taxonomy           Taxonomy object.
	 * @param  bool        $allow_hierarchical Whether hierarchical taxonomies are supported.
	 * @return bool
	 */
	private function is_supported( WP_Taxonomy $taxonomy, bool $allow_hierarchical ): bool {

		// The hierarchical term selector only makes sense for flat taxonomies;
		// hierarchical ones already render a checkbox tree.
		if ( ! $allow_hierarchical && $taxonomy->hierarchical ) {
			return false;
		}

		// The block editor panel is driven by REST and by the editor UI flags.
		if ( ! $taxonomy->show_in_rest ) {
			return false;
		}

		if ( ! $taxonomy->show_ui ) {
			return false;
		}

		return true;
	}

	/**
	 * Reduce a list of slugs to registered, supported taxonomies.
	 *
	 * @param  mixed $slugs              List of taxonomy slugs.
	 * @param  bool  $allow_hierarchical Whether hierarchical taxonomies are supported.
	 * @return array
	 */
	private function validate( $slugs, bool $allow_hierarchical ): array {

		if ( ! is_array( $slugs ) ) {
			return [];
		}

		$validated = [];

		foreach ( $slugs as $slug ) {

			if ( ! is_string( $slug ) ) {
				continue;
			}

			$taxonomy = get_taxonomy( $slug );

			if ( ! $taxonomy instanceof WP_Taxonomy ) {
				continue;
			}

			if ( ! $this->is_supported( $taxonomy, $allow_hierarchical ) ) {
				continue;
			}

			$validated[] = $taxonomy->name;
		}

		return array_values( array_unique( $validated ) );
	}
}
