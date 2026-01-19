# Checkbox Field

The **Checkbox** field allows editors to select one or multiple predefined values from a list of options. It is ideal for storing flexible, multi-value data while keeping the editing experience simple and controlled.

Throughout this document, examples are based on a **movie website**, where editors manage metadata such as genres, features, and content flags for films.

---

## What is it?

The Checkbox field is a selectable input field that presents a list of choices, each with its own label and value. Editors can select **zero, one, or multiple options** depending on how the field is configured.

Each selected option is stored as part of the field’s value and can be retrieved and used in templates, conditional logic, or integrations.

---

## How does it work?

When a Checkbox field is added to a field group:

- SCF renders a list of checkboxes in the WordPress admin.
- Each checkbox represents a predefined choice (label → value).
- Editors can select multiple options simultaneously.
- The selected values are stored as an **array** (or a structured format, depending on settings).

SCF automatically handles saving, sanitizing, and retrieving the selected values so developers can work with predictable data structures.

---

## What is it for?

Use the Checkbox field when:

- Multiple attributes can apply at the same time.
- You want to restrict values to a known set.
- Content requires classification without using taxonomies.
- Field values drive conditional behavior in templates or UI.

### Movie website use cases

On a movie website, Checkbox fields are commonly used for:

- **Movie genres** (Action, Drama, Comedy, Sci‑Fi)
- **Content flags** (Featured, Recommended, Editors’ Pick)
- **Audience suitability** (Kids Friendly, Violence, Mature Themes)
- **Distribution formats** (Streaming, Blu‑ray, Cinema Release)

---

## Usage

### Add a Checkbox field in the admin

1. Go to **SCF → Field Groups**.
2. Create or edit a field group assigned to the **Movie** post type.
3. Click **Add Field**.
4. Select **Checkbox** as the field type.
5. Define the available **Choices**.
6. Configure layout and return options.
7. Save the field group.

### Example: Movie genres

**Choices**
```
action   : Action
drama    : Drama
comedy   : Comedy
scifi    : Science Fiction
thriller : Thriller
```

### Field settings

Key settings available for the Checkbox field:

- **Choices**  
  Defines the available options (one per line or via array in code).

- **Default Value**  
  Automatically preselects options for new movies.

- **Layout**  
  Vertical (recommended for long lists) or horizontal.

- **Toggle**  
  Adds a “Select All / Deselect All” control.

- **Return Format**  
  - Value
  - Label
  - Both (array containing value and label)

- **Allow Custom**  
  Allows editors to add custom values beyond predefined choices.

### Best practices for movie data

- Use **stable values** (`action`, `drama`) and human-friendly labels.
- Avoid enabling “Allow Custom” for genres to keep data consistent.
- Keep the list concise; use taxonomies if the list becomes large or global.
- Document how genres are used in filters or templates.

---

## Next Steps

- Combine Checkbox fields with conditional logic (e.g., show “Awards” section only if “Featured” is checked).
- Create standardized Checkbox sets for all movie-related content.
- Review existing movies when adding or removing options.
- Migrate to taxonomies if querying by genre becomes complex.

---

## For Developers

### Registering a Checkbox field for movies

The following example registers a Checkbox field for selecting movie genres on a custom post type called `movie`.

```php
<?php
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'    => 'group_movie_details',
			'title'  => 'Movie Details',
			'fields' => array(
				array(
					'key'     => 'field_movie_genres',
					'label'   => 'Genres',
					'name'    => 'movie_genres',
					'type'    => 'checkbox',
					'choices' => array(
						'action'   => 'Action',
						'drama'    => 'Drama',
						'comedy'   => 'Comedy',
						'scifi'    => 'Science Fiction',
						'thriller' => 'Thriller',
					),
					'layout'        => 'vertical',
					'return_format' => 'value',
					'toggle'        => 1,
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

### Rendering genres on a movie page

In a theme template (e.g., `single-movie.php`), you can display the selected genres like this:

```php
<?php
$genres = get_field( 'movie_genres' );

if ( is_array( $genres ) && ! empty( $genres ) ) :
	?>
	<section class="movie-genres">
		<h3>Genres</h3>
		<ul>
			<?php foreach ( $genres as $genre ) : ?>
				<li><?php echo esc_html( ucfirst( $genre ) ); ?></li>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php
endif;
```

### Using genres for conditional logic

Checkbox values are well suited for conditional behavior:

```php
<?php
$genres = (array) get_field( 'movie_genres' );

if ( in_array( 'scifi', $genres, true ) ) {
	// Load sci‑fi specific visuals or effects.
}

if ( in_array( 'thriller', $genres, true ) ) {
	// Display content warnings or mood indicators.
}
```

### Data integrity tips

- Treat checkbox values as **enumerated data**, not free text.
- Do not change stored values after movies are published.
- Use strict comparisons when checking values.
- Keep values lowercase and immutable.

---

*Last updated: 2026-01-19*
