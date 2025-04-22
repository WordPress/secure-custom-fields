# Secure Custom Fields - Experiments System

This directory contains the experiments system for Secure Custom Fields. The experiments system allows you to enable and manage experimental features in a controlled manner.

## Structure

- `class-scf-admin-experiment.php` - Base experiment class that all experiments should extend
- `class-scf-admin-experiment-editor-sidebar.php` - Experiment for moving field group elements to the editor sidebar

## Creating a New Experiment

To create a new experiment:

1. Create a new class file in this directory following the naming convention `class-scf-admin-experiment-{name}.php`
2. Extend the `SCF_Admin_Experiment` class
3. Implement the required methods:
   - `initialize()` - Set up experiment properties (name, title, description)
   - `setup_experiment()` - Add hooks and functionality when the experiment is enabled

Example:

```php
class SCF_Admin_Experiment_My_Experiment extends SCF_Admin_Experiment {
    public function initialize() {
        $this->name        = 'my-experiment';
        $this->title       = __( 'My Experiment', 'secure-custom-fields' );
        $this->description = __( 'Description of my experiment', 'secure-custom-fields' );

        if ( $this->is_enabled() ) {
            add_action( 'admin_init', array( $this, 'setup_experiment' ) );
        }
    }

    public function setup_experiment() {
        // Add hooks and functionality when the experiment is enabled
        // This is where you implement the actual experiment functionality
    }
}
```

## Registering an Experiment

To register your experiment, add the following to your plugin's main file:

```php
scf_register_admin_experiment( 'SCF_Admin_Experiment_My_Experiment' );
```

## How the Experiments System Works

1. **Registration**: Experiments are registered using the `scf_register_admin_experiment()` function.
2. **Admin Menu**: The experiments system adds a submenu item under the ACF Field Groups menu.
3. **Experiment Management**: Users can enable/disable experiments from the admin interface.
4. **Storage**: Experiment settings are stored in the WordPress options table with the prefix `scf_experiment_{name}_enabled`.
5. **Cleanup**: When an experiment is disabled, its settings can be cleaned up using the `cleanup()` method.

## Best Practices

1. Always sanitize and validate user input
2. Use WordPress nonces for form security
3. Follow WordPress coding standards
4. Use descriptive names and comments
5. Keep experiments modular and self-contained
6. Use WordPress options API for storing settings
7. Implement proper error handling
8. Use WordPress admin notice system for feedback 