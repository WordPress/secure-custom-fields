# Admin Commands Global Functions

## `acf_commands_init()`

Initializes SCF commands integration

* This function handles the integration with WordPress Commands (Cmd+K / Ctrl+K),
providing navigation commands for SCF admin pages and custom post types.
* The implementation follows these principles:

1. Only loads in screens where WordPress commands are available.
2. Performs capability checks to ensure users only see commands they can access.
3. Core administrative commands are only shown to users with SCF admin capabilities.
4. Custom post type commands are conditionally shown based on edit_posts capability
for each specific post type.
5. Post types must have UI enabled (show_ui setting) to appear in commands.

* @since SCF 6.5.0

---
