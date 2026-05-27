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

The live URL preview and search (which resolves a pasted URL into embed HTML on the fly) is reserved for authenticated users with the standard WordPress content-authoring capability (`edit_posts`) — typically Authors, Editors, and Administrators. Anonymous and front-end visitors do not see the live preview populate when typing a URL into an oEmbed input. Already-stored oEmbed values continue to render normally for all viewers regardless of login state.
