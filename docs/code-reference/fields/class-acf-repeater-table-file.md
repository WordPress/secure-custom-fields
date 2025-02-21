# ACF_Repeater_Table

ACF_Repeater_Table

* Helper class for rendering repeater tables.

## Properties

### `$field`

The main field array used to render the repeater.

* @var array

### `$sub_fields`

An array containing the subfields used in the repeater.

* @var array

### `$value`

The value(s) of the repeater field.

* @var array

### `$show_add`

If we should show the "Add Row" button.

* @var boolean

### `$show_remove`

If we should show the "Remove Row" button.

* @var boolean

### `$show_order`

If we should show the order of the fields.

* @var boolean

## Methods

### `__construct`

Constructs the ACF_Repeater_Table class.

* @param array $field The main field array for the repeater being rendered.

### `setup`

Sets up the field for rendering.

* @since ACF 6.0.0
* @return void

### `prepare_value`

Prepares the repeater values for rendering.

* @since ACF 6.0.0
* @return array

### `render`

Renders the full repeater table.

* @since ACF 6.0.0
* @return void

### `thead`

Renders the table head.

* @since ACF 6.0.0
* @return void

### `rows`

Renders or returns rows for the repeater field table.

* @since ACF 6.0.0
* @param boolean $should_return If we should return the rows or render them.
* @return array|void

### `row`

Renders an individual row.

* @since ACF 6.0.0
* @param integer $i      The row number.
* @param array   $row    An array containing the row values.
* @param boolean $should_return If we should return the row or render it.
* @return string|void

### `row_handle`

Renders the row handle at the start of each row.

* @since ACF 6.0.0
* @param integer $i The current row number.
* @return void

### `row_actions`

Renders the actions displayed at the end of each row.

* @since ACF 6.0.0
* @return void

### `table_actions`

Renders the actions displayed underneath the table.

* @since ACF 6.0.0
* @return void

### `pagination`

Renders the table pagination.
Mostly lifted from the WordPress core WP_List_Table class.

* @since ACF 6.0.0
* @return void
