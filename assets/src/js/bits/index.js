/**
 * SCF inline tokens (Bits) editor entry.
 *
 * Adds a RichText toolbar button to paragraph, heading, and list-item blocks
 * that opens a picker modal for inserting a `scf-field-bit` span. The shape
 * is persisted in post content and resolved server-side at render time.
 *
 * Configuration arrives via `window.scfFieldBits` (see class-scf-bits-editor.php).
 */

import { attachBitToolbar, renderBitSpan } from './toolbar.js';

if (
	typeof window !== 'undefined' &&
	Array.isArray( window.scfFieldBits?.bits )
) {
	attachBitToolbar();
}

export { renderBitSpan };
