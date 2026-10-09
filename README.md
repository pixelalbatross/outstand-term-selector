# Outstand Term Selector

Use the hierarchical (checkbox tree) term selector for non-hierarchical taxonomies.

## Why

WordPress renders a free-text tag input for non-hierarchical taxonomies. That is
the right control for open vocabularies, where editors are meant to invent terms.
It is the wrong control for a **curated vocabulary** — a fixed list of regions,
difficulty levels, or programme types — where editors should pick from what
already exists, since typing a near-match into a tag input creates a
near-duplicate term on save.

This plugin swaps in the core `PostTaxonomiesHierarchicalTermSelector` component
(the checkbox tree used by categories) for the taxonomies you opt in. Editors
tick existing terms; the parent dropdown in its add-new form is hidden, because
flat taxonomies cannot store a parent.

### Adding terms

The selector keeps its "Add new" form for users who can create terms in the
taxonomy (its `edit_terms` capability, which by default editors and
administrators have). Creating a term is a deliberate action, but it is still
possible. To fully lock the vocabulary, map `edit_terms` to a capability your
editors lack when registering the taxonomy:

```php
register_taxonomy(
	'region',
	'post',
	[
		// ...
		'capabilities' => [
			'edit_terms' => 'manage_options',
		],
	]
);
```

Editors without that capability can still assign existing terms; the "Add new"
link does not render for them.

## Opt-in only

**The plugin never applies the hierarchical selector to every non-hierarchical
taxonomy on a site.** Flat taxonomies exist precisely so editors can add terms
freely; changing that everywhere would break editorial workflows the site owner
never asked us to touch. A taxonomy is only affected if it opts in explicitly,
through one of the two paths below.

A taxonomy is additionally ignored unless it is non-hierarchical, `show_ui`, and
`show_in_rest` — the component is pointless otherwise, and the block editor
panel it replaces only exists under those conditions.

### 1. At registration

If you own the `register_taxonomy()` call, pass `use_hierarchical_selector`:

```php
register_taxonomy(
	'region',
	'post',
	[
		'hierarchical'              => false,
		'show_ui'                   => true,
		'show_in_rest'              => true,
		'use_hierarchical_selector' => true,
	]
);
```

### 2. Via filter

If the taxonomy is registered by someone else — a third-party CPT plugin, a
theme, or core — use the `outstand_term_selector_taxonomies` filter. It receives
the resolved list of slugs and can add to it or remove from it:

```php
add_filter(
	'outstand_term_selector_taxonomies',
	function ( $slugs ) {
		$slugs[] = 'post_tag';
		return array_values( array_diff( $slugs, [ 'region' ] ) );
	}
);
```

Slugs returned by the filter are validated again, so an unregistered,
hierarchical, `show_ui => false`, or non-REST taxonomy can never be forced
through.

## Requirements

- WordPress 6.7+
- PHP 8.2+

## Development

```sh
composer install
npm install
npm run build          # → build/js/editor.js + build/js/editor.asset.php
```

Linting:

```sh
composer run lint      # phpcs (PixelAlbatross)
npm run lint:js
```

Tests (WordPress integration tests inside `@wordpress/env`, Docker required):

```sh
npm run test:setup     # starts wp-env (ports 8997 / 8998)
npm run test:unit
```

Before tagging a release, rebuild the committed `vendor/` without dev
dependencies:

```sh
composer update --no-dev -o
```

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
