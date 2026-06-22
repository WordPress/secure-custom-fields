# oEmbed Field

The oEmbed field allows embedding external content from various providers like YouTube, Vimeo, and Twitter. It automatically handles the embedding process using WordPress's oEmbed functionality.

## Key Features

- Support for multiple providers
- Automatic embed handling
- Preview capability
- Width/height control
- WordPress oEmbed integration

## Settings

- Width - Maximum width of embedded content
- Height - Maximum height of embedded content
- Preview Size - Display size in admin

## Availability

The live URL preview and search (which resolves a pasted URL into embed HTML on the fly) uses WordPress's registered oEmbed provider allowlist for anonymous visitors and users without the standard WordPress content-authoring capability (`edit_posts`). Users with content-authoring capability can also use WordPress oEmbed discovery. Already-stored oEmbed values continue to render normally for all viewers regardless of login state.
