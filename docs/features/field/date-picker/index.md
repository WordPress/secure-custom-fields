# Date Picker Field

The **Date Picker** field provides an interactive calendar interface for selecting dates within the WordPress admin. It ensures dates are entered consistently, displayed clearly to editors, and returned in predictable formats for developers.

This documentation follows WordPress.org guidelines and is written for **both non-technical editors and developers**, using a **movie website** as a consistent real-world example.

---

## What is it?

The Date Picker field is a **single-value date input field** that allows editors to select a date from a visual calendar instead of typing it manually.

By separating how a date is **displayed** from how it is **stored and returned**, the Date Picker field ensures:
- A user-friendly editing experience
- Consistent date formatting
- Reliable date handling in templates and logic

---

## How does it work?

When a Date Picker field is added to a field group:

1. SCF displays a calendar input in the WordPress admin.
2. Editors choose a date using the calendar interface.
3. The selected date is shown in a human-readable format.
4. The value is stored internally according to the configured return format.
5. Developers retrieve the date in a predictable format for rendering or logic.

Editors interact with dates visually, while developers work with standardized values.

---

## What is it for?

Use the Date Picker field whenever content is associated with a **specific date**.

### Movie website examples

On a movie website, Date Picker fields are commonly used for:

- **Movie release date**
- **Premiere date**
- **Festival screening date**
- **Streaming availability date**
- **Limited-time promotions**

Dates selected through the Date Picker field can be used for display, sorting, filtering, or conditional behavior.

---

## Key Features

- Interactive calendar interface
- Customizable display and return formats
- Configurable week start day
- Date range restrictions
- Multiple display formats for editors
- Predictable, structured date values

---

## Usage

### Adding a Date Picker field (for editors)

1. Go to **SCF → Field Groups**.
2. Create or edit a field group.
3. Click **Add Field**.
4. Select **Date Picker** as the field type.
5. Configure display and return formats.
6. Assign the field group to the desired location (for example, the Movie post type).
7. Save the field group.

Editors will see a calendar control when editing a movie.

---

## Settings

### Display Format
Controls how the date appears to editors in the admin interface.

Example:
```
d/m/Y
```

This setting affects **visual presentation only**.

---

### Return Format
Defines how the date value is stored and returned to templates:
- **Formatted string** (e.g. `Y-m-d`)
- **Custom format** for integration needs

Developers should choose a format that works well with sorting and comparisons.

---

### Week Starts On
Sets which day the calendar week begins on (for example, Monday or Sunday).

---

### First Day
Defines the first day shown in the calendar view.  
This improves usability for editors in different regions.

---

## Best practices for editors

- Always select dates using the calendar, not manual input.
- Follow project conventions for date usage (release vs premiere).
- Double-check dates for time-sensitive content.
- Update dates carefully after publication.

---

## Next Steps

After implementing Date Picker fields:

- Display formatted dates consistently across movie pages.
- Use dates for sorting upcoming or archived movies.
- Combine with conditional logic (e.g., hide content before release date).
- Standardize date formats across all content types.

---

## For Developers

### Registering a Date Picker field for movies

The following example registers a Date Picker field for selecting a movie release date on a `movie` post type.

```php
<?php
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'    => 'group_movie_dates',
			'title'  => 'Movie Dates',
			'fields' => array(
				array(
					'key'            => 'field_movie_release_date',
					'label'          => 'Release Date',
					'name'           => 'movie_release_date',
					'type'           => 'date_picker',
					'display_format' => 'd/m/Y',
					'return_format'  => 'Y-m-d',
					'first_day'      => 1,
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

### Rendering the date in a movie template

```php
<?php
$date = get_field( 'movie_release_date' );

if ( $date ) :
	?>
	<time datetime="<?php echo esc_attr( $date ); ?>">
		<?php echo esc_html( date_i18n( 'F j, Y', strtotime( $date ) ) ); ?>
	</time>
	<?php
endif;
```

---

### Using dates in logic

```php
<?php
$release_date = get_field( 'movie_release_date' );
$today        = current_time( 'Y-m-d' );

if ( $release_date && $release_date > $today ) {
	// Movie has not been released yet.
}
```

---

### Data integrity tips

- Store dates in a sortable format such as `Y-m-d`.
- Always use WordPress date functions for localization.
- Avoid mixing date formats across fields.
- Document how dates are used in logic and templates.

---

## Summary

The Date Picker field provides a reliable and user-friendly way to manage dates in SCF. It simplifies date input for editors while giving developers consistent, predictable values. For movie websites and other time-based content, it is an essential tool for managing releases, events, and schedules.

---

*Last updated: 2026-01-19*
