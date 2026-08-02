<?php

namespace Outstand\WP\TermSelector;

use WP_Taxonomy;

/**
 * Resolves which non-hierarchical taxonomies swap their free-text tag input for
 * the hierarchical (checkbox tree) term selector in the block editor.
 *
 * Opt-in only. The plugin never applies the hierarchical selector to every
 * non-hierarchical taxonomy on a site: flat taxonomies exist precisely so
 * editors can invent terms, and silently removing that ability everywhere
 * would break editorial workflows the site owner never asked us to touch.
 */
class TermSelector extends BaseModule {

	/**
	 * Name of the JavaScript global carrying the resolved taxonomy list.
	 *
	 * @var string
	 */
	const SCRIPT_VAR = 'outstandTermSelector';

	/**
	 * Memoized list of taxonomy slugs.
	 *
	 * @var ?array
	 */
	private ?array $taxonomies = null;

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
	 * @return array List of taxonomy slugs.
	 */
	public function get_taxonomies(): array {

		if ( ! is_null( $this->taxonomies ) ) {
			return $this->taxonomies;
		}

		$slugs = [];

		foreach ( get_taxonomies( [], 'objects' ) as $taxonomy ) {

			if ( ! $this->is_supported( $taxonomy ) ) {
				continue;
			}

			if ( empty( $taxonomy->use_hierarchical_selector ) ) {
				continue;
			}

			$slugs[] = $taxonomy->name;
		}

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

		$this->taxonomies = $this->validate( $filtered );

		return $this->taxonomies;
	}

	/**
	 * Pass the resolved taxonomy list to the editor bundle.
	 *
	 * @return void
	 */
	public function enqueue_settings(): void {

		if ( ! wp_script_is( Assets::HANDLE, 'enqueued' ) ) {
			return;
		}

		$settings = [
			'taxonomies' => $this->get_taxonomies(),
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
	 * Whether a taxonomy can use the hierarchical term selector.
	 *
	 * @param  WP_Taxonomy $taxonomy Taxonomy object.
	 * @return bool
	 */
	private function is_supported( WP_Taxonomy $taxonomy ): bool {

		// The component only makes sense for flat taxonomies; hierarchical ones
		// already render a checkbox tree.
		if ( $taxonomy->hierarchical ) {
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
	 * @param  mixed $slugs List of taxonomy slugs.
	 * @return array
	 */
	private function validate( $slugs ): array {

		if ( ! is_array( $slugs ) ) {
			return [];
		}

		$validated = [];

		foreach ( $slugs as $slug ) {

			if ( ! is_string( $slug ) ) {
				continue;
			}

			$taxonomy = get_taxonomy( $slug );

			if ( ! $taxonomy ) {
				continue;
			}

			if ( ! $this->is_supported( $taxonomy ) ) {
				continue;
			}

			$validated[] = $taxonomy->name;
		}

		return array_values( array_unique( $validated ) );
	}
}
