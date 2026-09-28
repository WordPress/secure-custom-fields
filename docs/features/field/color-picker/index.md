# Color Picker Field

The Color Picker field provides an interactive interface for selecting colors. It supports both RGB and RGBA color formats and includes a visual color picker with opacity control.

## Key Features

- Visual color selection interface
- RGB and RGBA color support
- Opacity/transparency control
- Default color presets
- Hex color input

## Settings

- Default Value - Set a default color
- Return Format - Choose between string, array, or rgba format
- Enable Opacity - Allow transparency selection

## Native Input

When the native pickers beta feature is enabled, this field uses the browser's built-in color input for plain hex values. The stored value is unchanged. The browser input has no transparency, palette or color wheel support, so fields using Enable Transparency, a custom palette or a non-hex value keep the existing picker.
