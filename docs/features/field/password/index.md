# Password Field

The Password field provides a masked text input. Characters typed into the field are hidden in the editor, but the value is stored as plain text, like a Text field.

## Key Features

- Masked input
- Custom placeholder text
- Prepend/append options

## Settings

- Placeholder - Input placeholder text
- Prepend - Text before the input
- Append - Text after the input

## Storage and Output

The Password field masks input only. It does not encrypt, hash or hide the stored value:

- `get_field()` and `the_field()` return the value as plain text, so any template or block that prints it shows it to everyone who can view the page.
- When the field group has Show in REST API enabled, the value is included in REST API responses.
- Front-end forms created with `acf_form()` include the stored value in the input.
- The value is excluded from JSON-LD output.

Don't use the Password field to store secrets such as credentials, API keys or access tokens.
