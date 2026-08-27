# Clone Field

The **Clone** field allows you to **reuse existing fields or entire field groups** in multiple locations. It helps maintain consistency, reduce duplication, and centralize changes, making it especially valuable for large or long‑term projects.

This documentation follows WordPress.org guidelines and is written for **both non-technical editors and developers**, using a **movie website** as a consistent real‑world example.

---

## What is it?

The Clone field is a **field reuse mechanism**. Instead of creating the same fields repeatedly, you can reference existing fields or field groups and display them wherever they are needed.

Cloned fields:
- Share the same configuration as the original
- Stay synchronized when the original is updated
- Can optionally use prefixed labels and names to avoid conflicts

This makes the Clone field ideal for enforcing standardized content structures.

---

## How does it work?

When a Clone field is added to a field group:

1. You select one or more existing fields or field groups.
2. SCF renders those fields as if they were defined locally.
3. Any changes to the original field configuration are reflected everywhere it is cloned.
4. Values are stored and retrieved like normal fields, respecting prefixes if applied.

From an editor’s perspective, cloned fields behave exactly like regular fields. From a developer’s perspective, they reduce maintenance and improve consistency.

---

## What is it for?

Use the Clone field when you want to **reuse common field sets** across multiple content types or sections.

### Movie website examples

On a movie website, Clone fields are commonly used for:

- **SEO metadata**  
  Title override, meta description, social image

- **Credits blocks**  
  Director, Producer, Cast, Runtime

- **Media sections**  
  Poster image, trailer URL, gallery

- **Call‑to‑action blocks**  
  Primary button text and link

Instead of redefining these fields for every post type, they can be defined once and reused everywhere.

---

## Key Features

- Reuse existing field configurations
- Clone individual fields or entire field groups
- Maintain synchronized settings across all cloned instances
- Prefix labels to avoid editor confusion
- Prefix field names to prevent data conflicts
- Reduce duplication and long‑term maintenance cost

---

## Usage

### Adding a Clone field (for editors)

1. Go to **SCF → Field Groups**.
2. Edit a field group.
3. Click **Add Field**.
4. Select **Clone** as the field type.
5. Choose the fields or field groups to clone.
6. Configure display and prefix settings.
7. Save the field group.

Editors will see the cloned fields rendered inline, just like regular fields.

---

## Settings

### Select Fields
Choose which existing fields or field groups should be cloned. Multiple selections are supported.

### Display
Controls how cloned fields appear:
- **Seamless** – cloned fields appear as part of the current group
- **Grouped** – cloned fields are visually grouped together

### Prefix Label
Adds a prefix to field labels (for example, “SEO: Title”), helping editors understand context.

### Prefix Name
Adds a prefix to field names to avoid naming collisions when the same field is cloned multiple times.

---

## Best practices for editors

- Treat cloned fields like normal fields when editing content.
- Follow naming and labeling conventions provided by your team.
- Do not worry about configuration changes—those are managed centrally.
- If something looks duplicated, it is likely intentional reuse.

---

## Next Steps

After implementing Clone fields:

- Identify other repeated field sets and refactor them into reusable groups.
- Standardize SEO, media, and CTA blocks across all movie content.
- Document shared field groups so editors understand their purpose.
- Periodically review cloned structures to ensure they remain relevant.

---

## For Developers

### Creating reusable field groups

A common pattern is to define reusable field groups (for example, SEO fields) and then clone them where needed.

```php
<?php
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	// Reusable SEO field group
	acf_add_local_field_group(
		array(
			'key'    => 'group_movie_seo',
			'title'  => 'SEO Fields',
			'fields' => array(
				array(
					'key'   => 'field_seo_title',
					'label' => 'SEO Title',
					'name'  => 'seo_title',
					'type'  => 'text',
				),
				array(
					'key'   => 'field_seo_description',
					'label' => 'SEO Description',
					'name'  => 'seo_description',
					'type'  => 'textarea',
				),
			),
		)
	);
} );
```

---

### Cloning the field group into a Movie post type

```php
<?php
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'    => 'group_movie_content',
			'title'  => 'Movie Content',
			'fields' => array(
				array(
					'key'           => 'field_clone_movie_seo',
					'label'         => 'SEO',
					'name'          => '',
					'type'          => 'clone',
					'clone'         => array(
						'group_movie_seo',
					),
					'display'       => 'seamless',
					'prefix_label'  => 1,
					'prefix_name'   => 0,
				),
			),
			'location' => array(
				array(
					array(
						'param'    => 'post_type',
						'operator' => '==',
						'value'    => 'movie',
					),
				),
			),
		)
	);
} );
```

---

### Retrieving cloned field values

Cloned fields are retrieved exactly like normal fields:

```php
<?php
$seo_title = get_field( 'seo_title' );
$seo_desc  = get_field( 'seo_description' );
```

If `prefix_name` is enabled, use the prefixed field name accordingly.

---

### Data integrity tips

- Use **Prefix Name** when cloning the same fields multiple times.
- Keep reusable field groups small and focused.
- Avoid deeply nested clones to reduce complexity.
- Treat cloned fields as shared dependencies.

---

## Summary

The Clone field is a powerful tool for building **scalable, maintainable field architectures** in SCF. By reusing field configurations instead of duplicating them, you ensure consistency, reduce errors, and make long‑term maintenance significantly easier—especially for structured content such as movie websites.

---

*Last updated: 2026-01-19*
