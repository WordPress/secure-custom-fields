# Using the Password Field

## Basic Setup

1. Create a new Field Group
2. Add a Password field
3. Configure options:
   - Set placeholder text
   - Add prepend/append

## Common Use Cases

- Values that should not be visible on screen while an editor types them, such as access codes shared with site visitors

## Tips

- The field masks input but stores the value as plain text.
- Don't print the value in templates or blocks unless it is meant to be public.
- Leave Show in REST API off for field groups that contain values you don't want exposed.
- Store credentials, API keys and access tokens outside custom fields, for example in `wp-config.php` constants.
