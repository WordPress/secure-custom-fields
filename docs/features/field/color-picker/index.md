# Color Picker Field

The **Color Picker** field provides an interactive interface for selecting colors within the WordPress admin. It supports **RGB and RGBA** color formats, includes a visual picker with **opacity control**, and allows precise **HEX input**. This makes it ideal for design-related settings where visual accuracy and consistency matter.

This documentation follows WordPress.org guidelines and is written for **both non-technical editors and developers**, using a **movie website** as a consistent real-world example.

---

## What is it?

The Color Picker field is a **single-value design field** that lets editors choose a color visually or by entering a color code. The selected color is stored in a standardized format and can be used to control styling, branding accents, or UI highlights across a site.

Depending on configuration, the field can return:
- A color string (HEX or RGBA)
- A structured array
- An RGBA value with transparency

---

## How does it work?

When a Color Picker field is added to a field group:

1. SCF displays a visual color picker in the WordPress admin.
2. Editors select a color using the picker or enter a HEX value manually.
3. (Optional) Editors adjust opacity if transparency is enabled.
4. The selected color is saved with the post.
5. The value is returned in the configured format and can be used in templates or styles.

For editors, the experience is visual and intuitive. For developers, the output is predictable and easy to integrate.

---

## What is it for?

Use the Color Picker field when content needs **visual customization** without manual CSS editing.

### Movie website examples

On a movie website, Color Picker fields are commonly used for:

- **Hero section accent color**
- **Genre badge colors**
- **Highlight color for featured movies**
- **Overlay color for trailers or posters**
- **Theme accents for special releases**

These colors can be applied dynamically to enhance branding and visual storytelling.

---

## Key Features

- Visual color selection interface
- RGB and RGBA color support
- Opacity / transparency control
- Default color presets
- Direct HEX color input
- Predictable output for templates and styles

---

## Usage

### Adding a Color Picker field (for editors)

1. Go to **SCF → Field Groups**.
2. Create or edit a field group.
3. Click **Add Field**.
4. Select **Color Picker** as the field type.
5. Configure default and return options.
6. Assign the field group to the desired location (for example, the Movie post type).
7. Save the field group.

Editors will see a color picker control when editing a movie.

---

## Settings

### Default Value
Sets a predefined color for new content. This helps maintain visual consistency.

Example:
```
#ff3d00
```

### Return Format
Defines how the color value is returned:
- **String** – HEX or RGBA string (recommended for most cases)
- **Array** – structured data with color components
- **RGBA** – explicit RGBA value including opacity

### Enable Opacity
Allows editors to control transparency using an opacity slider.  
When enabled, RGBA values are returned.

---

## Best practices for editors

- Use colors consistently to represent meaning (e.g., red for featured).
- Avoid excessive color variation across similar content.
- Prefer default values unless a visual change is intentional.
- Use opacity sparingly to maintain readability.

---

## Next Steps

After implementing Color Picker fields:

- Apply selected colors to badges, overlays, or UI elements.
- Combine with conditional logic (e.g., featured movies use custom colors).
- Standardize color usage across movie types.
- Document color meaning for editorial teams.

---

## For Developers

### Registering a Color Picker field for movies

The following example registers a Color Picker field for selecting a highlight color on a `movie` post type.

```php
<?php
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'    => 'group_movie_colors',
			'title'  => 'Movie Visual Settings',
			'fields' => array(
				array(
					'key'           => 'field_movie_highlight_color',
					'label'         => 'Highlight Color',
					'name'          => 'movie_highlight_color',
					'type'          => 'color_picker',
					'default_value' => '#ff3d00',
					'return_format' => 'string',
					'enable_opacity'=> 1,
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

### Rendering the color in a movie template

```php
<?php
$color = get_field( 'movie_highlight_color' );

if ( $color ) :
	?>
	<div class="movie-highlight" style="background-color: <?php echo esc_attr( $color ); ?>;">
		Featured Movie
	</div>
	<?php
endif;
```

---

### Using the color in conditional styling

```php
<?php
$color = get_field( 'movie_highlight_color' );

if ( $color ) {
	// Pass the color to inline styles, CSS variables, or frontend scripts.
}
```

---

### Data integrity tips

- Store colors in a consistent format across the project.
- Prefer HEX for simple use cases and RGBA when transparency is required.
- Avoid using Color Picker fields for non-visual data.
- Document color usage rules for maintainability.

---

## Summary

The Color Picker field empowers editors to control visual accents without touching code, while giving developers clean and predictable color values. For design-driven content such as movie websites, it provides the perfect balance between flexibility, consistency, and maintainability.

---

*Last updated: 2026-01-19*
