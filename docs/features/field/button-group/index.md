# Button Group Field

The **Button Group** field in Secure Custom Fields (SCF) provides a clear and user-friendly way to select **one option from a predefined set**. It presents choices as clickable buttons instead of checkboxes or dropdowns, making selections faster, more visual, and less error‑prone.

This documentation uses a **movie website** as a real-world scenario, showing how Button Group fields can be applied effectively by both non-technical editors and developers.

---

## What is it?

The Button Group field is a **single-choice selection field**. Unlike Checkbox fields (which allow multiple selections), a Button Group allows **only one value to be selected at a time**.

Each option is displayed as a button with a clear label, helping editors immediately understand available choices and current selection.

Button Group fields store a **single value**, making them ideal for mutually exclusive states or modes.

---

## How does it work?

When a Button Group field is added to a field group:

1. SCF displays all predefined options as clickable buttons.
2. Editors select exactly **one** option.
3. Selecting a button automatically deselects the others.
4. SCF stores the selected value in the database.
5. The value can later be retrieved and used in templates or logic.

Because only one option can be stored, the returned value is always **predictable and consistent**, simplifying both editorial workflows and development.

---

## What is it for?

Use the Button Group field when content requires a **clear, exclusive choice**.

### Ideal use cases

- Selecting a single mode or state
- Choosing between layout or display variants
- Setting visibility or priority levels
- Replacing dropdowns where clarity is critical

### Movie website examples

On a movie website, Button Group fields are commonly used for:

- **Movie status**  
  Upcoming · Now Showing · Archived

- **Audience rating**  
  G · PG · PG-13 · R

- **Primary format**  
  Streaming · Cinema · Blu-ray

- **Highlight level**  
  Normal · Featured · Editor’s Pick

These values are often used to control labels, badges, filters, or page behavior.

---

## Usage

### Adding a Button Group field (for editors)

1. Go to **SCF → Field Groups**.
2. Create or edit a field group assigned to the **Movie** post type.
3. Click **Add Field**.
4. Select **Button Group** as the field type.
5. Define the available **Choices**.
6. Configure default value and return format.
7. Save the field group.

Editors will see a row of buttons when editing a movie, with one active selection at all times.

---

### Defining choices

Each choice consists of a **stored value** and a **label**.

Example (Movie Status):

```
upcoming  : Upcoming
showing   : Now Showing
archived  : Archived
```

- The value (`upcoming`) is stored in the database.
- The label (`Upcoming`) is shown to editors.

---

### Field settings explained

#### Choices
Defines the available options. Button Groups work best with **small, focused sets** (typically 2–5 options).

#### Default Value
Specifies which option is preselected for new content. This helps guide editors and avoids empty states.

#### Return Format
Controls what is returned when retrieving the field:
- **Value** – returns the stored value (recommended)
- **Label** – returns the human-readable label
- **Both** – returns value and label together

#### Layout
Controls how buttons are displayed:
- **Horizontal** (default and recommended)
- **Vertical** (useful for longer labels)

---

### Best practices for editors

- Choose the option that best represents the movie’s current state.
- Avoid changing values after publication unless intentionally updating behavior.
- Follow editorial guidelines to ensure consistency.
- If unsure which option to select, consult internal documentation.

---

## Next Steps

After implementing Button Group fields:

- Use the selected value to display badges or labels.
- Apply conditional logic to adjust layouts or features.
- Combine with Checkbox fields for advanced configurations.
- Audit values periodically to ensure relevance.

---

## For Developers

### Registering a Button Group field for movies

The following example registers a Button Group field for selecting a movie’s release status.

```php
<?php
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'    => 'group_movie_status',
			'title'  => 'Movie Status',
			'fields' => array(
				array(
					'key'     => 'field_movie_status',
					'label'   => 'Release Status',
					'name'    => 'movie_status',
					'type'    => 'button_group',
					'choices' => array(
						'upcoming' => 'Upcoming',
						'showing'  => 'Now Showing',
						'archived' => 'Archived',
					),
					'default_value' => 'upcoming',
					'return_format' => 'value',
					'layout'        => 'horizontal',
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

### Rendering the Button Group value

In a movie template (`single-movie.php`):

```php
<?php
$status = get_field( 'movie_status' );

if ( $status ) :
	?>
	<span class="movie-status movie-status--<?php echo esc_attr( $status ); ?>">
		<?php echo esc_html( ucfirst( $status ) ); ?>
	</span>
	<?php
endif;
```

---

### Using Button Group values in logic

Button Group fields are ideal for conditional logic because only one value is possible:

```php
<?php
$status = get_field( 'movie_status' );

if ( 'upcoming' === $status ) {
	// Show release countdown.
}

if ( 'showing' === $status ) {
	// Enable ticket purchase.
}

if ( 'archived' === $status ) {
	// Hide promotional elements.
}
```

---

### Data integrity and maintenance

- Treat Button Group values as **enumerated states**.
- Keep values short, lowercase, and stable.
- Avoid reusing values for different meanings.
- Prefer Button Group over Checkbox when only one option should exist.

---

## Summary

The Button Group field provides a clean, intuitive way to manage **single-choice metadata** in SCF. Its visual clarity benefits non-technical editors, while its predictable output simplifies development. For structured content such as movie websites, Button Group fields are an excellent choice for managing states, modes, and primary classifications.

---

*Last updated: 2026-01-19*
