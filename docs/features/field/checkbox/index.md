# Checkbox Field

The **Checkbox** field allows users to select **one or multiple options** from a predefined list. It provides a flexible and intuitive way to assign multiple attributes to a piece of content, while maintaining structured and consistent data.

This documentation follows WordPress.org guidelines and is written for **both non-technical editors and developers**, using a **movie website** as a real-world example.

---

## What is it?

The Checkbox field is a **multi-choice selection field**. Each option is displayed as an individual checkbox, allowing editors to select **any number of applicable values**, including none or all.

Each selected option is stored as part of the field’s value and can later be retrieved and used in templates, conditional logic, or integrations.

---

## How does it work?

When a Checkbox field is added to a field group:

1. SCF displays a list of checkboxes in the WordPress admin.
2. Each checkbox represents a predefined option.
3. Editors can select multiple options simultaneously.
4. The selected values are saved when the post is updated.
5. The values are returned in a structured format based on field settings.

SCF manages all data handling automatically, ensuring consistent behavior for both editors and developers.

---

## What is it for?

Use the Checkbox field when **multiple attributes can apply at the same time**.

### Movie website examples

On a movie website, Checkbox fields are ideal for:

- **Movie genres**  
  Action, Drama, Comedy, Science Fiction, Thriller

- **Content flags**  
  Featured, Award Winner, Editor’s Pick

- **Audience notes**  
  Family Friendly, Contains Violence, Mature Themes

- **Availability options**  
  Streaming Available, Cinema Release, Home Media

These selections can later be used for filtering, labeling, or conditional display.

---

## Key Features

- Allows multiple selections
- Clear checkbox-based interface
- Customizable option labels
- Optional “Select All / Deselect All” toggle
- Structured and predictable stored values

---

## Usage

### Adding a Checkbox field (for editors)

1. Go to **SCF → Field Groups**.
2. Create or edit a field group.
3. Click **Add Field**.
4. Select **Checkbox** as the field type.
5. Define the available **Choices**.
6. Configure layout and return options.
7. Assign the field group to the desired location (for example, the Movie post type).
8. Save the field group.

Editors will see a list of checkboxes when editing a movie.

---

## Settings

### Choices
Defines the available options. Each choice consists of a stored value and a label.

Example (Movie Genres):
```
action   : Action
drama    : Drama
comedy   : Comedy
scifi    : Science Fiction
thriller : Thriller
```

### Default Value
Specifies which options are preselected for new content.

### Return Format
Controls how the value is returned:
- **Value** – returns stored values (recommended)
- **Label** – returns human-readable labels
- **Both** – returns both value and label

### Layout
Controls how checkboxes are displayed:
- Vertical (recommended for readability)
- Horizontal (best for short lists)

### Toggle
Adds a **Select All / Deselect All** control, useful for long option lists.

### Allow Custom
Allows editors to add custom values not defined in the choices list.  
Use with caution, as it reduces data consistency.

---

## Best practices for editors

- Select only options that truly apply to the movie.
- Avoid using checkboxes as free-form notes.
- Follow established editorial guidelines.
- If unsure about an option, consult internal documentation.

---

## Next Steps

After implementing Checkbox fields:

- Use selected values to filter or group movie listings.
- Display visual badges based on selections.
- Combine with Button Group fields for mixed configurations.
- Review and clean up unused options periodically.

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
			'key'    => 'group_movie_genres',
			'title'  => 'Movie Genres',
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

---

### Rendering Checkbox values in a movie template

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

---

### Using Checkbox values in logic

```php
<?php
$genres = (array) get_field( 'movie_genres' );

if ( in_array( 'scifi', $genres, true ) ) {
	// Apply science-fiction-specific visuals or effects.
}

if ( in_array( 'thriller', $genres, true ) ) {
	// Display content warnings or mood indicators.
}
```

---

### Data integrity tips

- Treat checkbox values as **enumerated data**.
- Avoid changing stored values after publication.
- Use strict comparisons when checking values.
- Prefer taxonomies if options need global reuse.

---

*Last updated: 2026-01-19*
