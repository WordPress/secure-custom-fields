# Post Content Placeholders

Post content placeholders let you reuse supported Secure Custom Fields values inside Gutenberg post content without copying the same text into multiple places.

## Placeholder syntax

Use the exact placeholder format:

```text
[[field_name]]
```

Example:

```text
Welcome to [[movie_title]]
```

Only exact placeholders are replaced. The field name may contain letters, numbers, underscores, and hyphens.

## Where placeholders work in v1

Version 1 is intentionally narrow.

Placeholders are replaced only when all of the following are true:

- The content is rendered on the frontend
- The current view is a singular post for the current post being rendered
- The placeholder is inside that post's main content
- The placeholder appears in a Gutenberg Paragraph block (`core/paragraph`) or Heading block (`core/heading`)

Placeholders are not replaced in unsupported contexts such as:

- Excerpts
- REST API content responses
- Feeds
- Admin screens
- Live editor substitution while typing
- Widgets, comments, term content, user content, or option pages

If a placeholder is used anywhere outside this narrow frontend block scope, the raw placeholder text may remain visible.

## Field requirements

A field must be explicitly allowed before its value can be used in a placeholder.

Enable the field setting:

- **Allow Access to Value in Editor UI** (`allow_in_bindings`)

If that setting is off, or the field has not been explicitly enabled for bindings exposure, the placeholder outputs an empty string on the frontend.

This opt-in requirement helps prevent placeholders from exposing field values that were not intended for public display.

## Supported field types

Post content placeholders support a limited set of field types in v1:

- Text
- Textarea
- Number
- Range
- Email
- URL
- Select, when it resolves to a single value
- Radio
- Button Group
- Date Picker
- Date Time Picker
- Time Picker
- WYSIWYG

Unsupported, structured, or non-scalar field values are not rendered. This includes cases such as repeaters, groups, galleries, files, images, relationships, or select fields configured to return multiple values.

## Output behavior

- Matching supported placeholders are replaced at render time
- Saved `post_content` stays exactly as authored
- Missing fields render as an empty string
- Empty field values render as an empty string
- Unsupported fields render as an empty string
- Supported fields that return non-scalar values also render as an empty string
- Malformed placeholders stay unchanged so you can spot the original text

Silent empty output is the expected v1 behavior for missing, empty, unsupported, or not-exposed fields.

Examples that stay literal:

```text
[[movie title]]
[[movie_title]
[movie_title]
[[movie_title|upper]]
```

## HTML and sanitization

Placeholder output is sanitized before it is inserted into rendered content.

Plain text values are output safely. HTML-bearing values, such as WYSIWYG content, are limited to inline-safe HTML.

Inline formatting like these may be preserved:

- `<strong>`
- `<em>`
- `<a>`
- `<code>`
- `<br>`

Block-level or layout HTML is not supported in placeholders. Markup such as paragraphs, headings, lists, tables, embeds, forms, iframes, and scripts is stripped before output.

Because of this, WYSIWYG fields work best for short inline content rather than full rich content blocks. If a field value depends on structural HTML for its meaning or layout, placeholders are not a good fit in v1.

## Deactivation behavior

Placeholders do not change the content stored in the database.

If Secure Custom Fields is deactivated, placeholder replacement stops and the original raw placeholder text, such as `[[movie_title]]`, remains visible in the post content.

## Example workflow

1. Create a supported post field such as `movie_title`
2. Enable **Allow Access to Value in Editor UI** for that field
3. Add a value to the field on a post
4. Insert `[[movie_title]]` into a Paragraph or Heading block
5. Save the post and view it on the frontend

## Notes for testing

To verify the feature:

1. Save the post with a supported placeholder in a Paragraph or Heading block.
2. View the frontend singular post.
3. Confirm the placeholder is replaced there.
4. Reopen the post in Gutenberg and confirm the editor still stores the raw placeholder text, such as `[[field_name]]`.

Malformed placeholders should remain visible as entered, and unsupported or not-exposed fields should render nothing on the frontend.