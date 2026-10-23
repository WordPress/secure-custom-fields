# Image Field

The **Image** field provides a dedicated interface for uploading and selecting images through the WordPress media library. It offers preview capabilities, validation options, and multiple return formats, making it suitable for both simple image usage and advanced media-driven layouts.

This documentation follows WordPress.org guidelines and is written for **both non-technical editors and developers**, using a **movie website** as a consistent real-world example.

---

## What is it?

The Image field is a **media selection field** that allows editors to upload or choose an image from the WordPress media library. Once selected, the image can be previewed directly in the editor and reused across templates or layouts.

Depending on configuration, the Image field can return:
- An image **ID**
- An image **URL**
- A full **array** of image data (metadata, sizes, alt text)

---

## How does it work?

When an Image field is added to a field group:

1. SCF displays an image selector connected to the WordPress media library.
2. Editors can upload a new image or select an existing one.
3. A preview thumbnail is shown in the editor.
4. Optional validation rules ensure correct image dimensions and file size.
5. The selected image is saved and returned in the configured format.

For editors, the experience is visual and familiar. For developers, the output is structured and predictable.

---

## What is it for?

Use the Image field whenever content requires **visual representation**.

### Movie website examples

On a movie website, Image fields are commonly used for:

- **Movie poster**
- **Hero background image**
- **Director or cast photos**
- **Featured banners**
- **Promotional artwork**

Images selected through the Image field can be reused consistently across listings, single pages, and promotional sections.

---

## Key Features

- Full WordPress media library integration
- Live image preview in the editor
- Image dimension validation (width and height)
- File size restrictions
- Support for multiple return formats
- Controlled image source selection

---

## Usage

### Adding an Image field (for editors)

1. Go to **SCF → Field Groups**.
2. Create or edit a field group.
3. Click **Add Field**.
4. Select **Image** as the field type.
5. Configure preview size and validation options.
6. Assign the field group to the desired location (for example, the Movie post type).
7. Save the field group.

Editors will see an image upload/select control when editing a movie.

---

## Settings

### Preview Size
Controls the thumbnail size shown in the editor preview (for example, `thumbnail`, `medium`, `large`).

### Library
Restricts image selection:
- **All** – any image from the media library
- **Uploaded** – only images uploaded to the current post

### Min / Max Width & Height
Defines minimum and maximum image dimensions to ensure consistent layouts.

### File Size Restrictions
Limits the maximum allowed file size for uploads, helping with performance and storage control.

### Return Format
Defines how the image value is returned:
- **Array** – full image data (recommended for flexibility)
- **URL** – direct image URL
- **ID** – attachment ID (best for performance and advanced usage)

---

## Best practices for editors

- Upload images at the recommended dimensions.
- Use descriptive alt text for accessibility.
- Avoid excessively large images to improve performance.
- Reuse existing images when possible to reduce duplication.

---

## Next Steps

After implementing Image fields:

- Use consistent image sizes across movie content.
- Combine with Color Picker fields for visual theming.
- Add fallback images in templates for missing content.
- Audit media usage periodically to remove unused assets.

---

## For Developers

### Registering an Image field for movies

The following example registers an Image field for selecting a movie poster on a `movie` post type.

```php
<?php
add_action( 'acf/init', function () {

	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		array(
			'key'    => 'group_movie_images',
			'title'  => 'Movie Images',
			'fields' => array(
				array(
					'key'           => 'field_movie_poster',
					'label'         => 'Movie Poster',
					'name'          => 'movie_poster',
					'type'          => 'image',
					'preview_size'  => 'medium',
					'library'       => 'all',
					'return_format' => 'id',
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

### Rendering the image in a movie template

```php
<?php
$image_id = get_field( 'movie_poster' );

if ( $image_id ) :
	?>
	<div class="movie-poster">
		<?php echo wp_get_attachment_image( $image_id, 'large' ); ?>
	</div>
	<?php
endif;
```

---

### Handling different return formats

**Array format example:**
```php
<?php
$image = get_field( 'movie_poster' );

if ( is_array( $image ) ) {
	echo esc_url( $image['url'] );
}
```

**URL format example:**
```php
<?php
<?php
$image_url = get_field( 'movie_poster' );

if ( $image_url ) {
	echo esc_url( $image_url );
}
```

---

### Data integrity tips

- Prefer returning **ID** for performance and flexibility.
- Validate image dimensions at upload time.
- Always escape output when rendering images.
- Provide fallbacks for missing images.

---

## Summary

The Image field is a core building block for media-rich content in SCF. It empowers editors with a familiar media workflow while giving developers precise control over how images are stored and rendered. For movie websites and other visual projects, it ensures consistency, performance, and maintainability.

---

*Last updated: 2026-01-19*
