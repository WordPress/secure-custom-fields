# Secure Custom Fields TypeScript

This directory contains TypeScript files for the Secure Custom Fields plugin.

## Experiments

The experiments functionality allows for feature flags in the WordPress admin. This enables gradual rollout of new features and A/B testing.

### Usage

To check if an experiment is enabled in your TypeScript code:

```typescript
// Check if SCF and experiments are available
if (typeof window.SCF !== 'undefined' && 
    typeof window.SCF.experiments !== 'undefined' && 
    typeof window.SCF.experiments.isEnabled !== 'undefined') {
  
  // Check if a specific experiment is enabled
  if (window.SCF.experiments.isEnabled('experiment_name')) {
    // Experiment is enabled, implement feature
  } else {
    // Experiment is disabled, use default behavior
  }
}
```

### Adding New Experiments

To add a new experiment:

1. Create a new experiment class in `includes/admin/experiments/`
2. Register the experiment in `SCF_Admin_Experiments::register_experiments()`
3. The experiment will automatically be available in JavaScript via `window.SCF.experiments.isEnabled('experiment_name')`

## TypeScript Setup

The TypeScript files are compiled to JavaScript using the WordPress build process. The compiled files are placed in the `assets/build/js/` directory.

### Key Files

- `_acf-experiments.ts`: Core experiments functionality
- `acf-experiments.ts`: Main entry point for experiments
- `_acf-experiment-example.ts`: Example of using experiments in TypeScript

## Implementation Details

The experiments functionality is implemented using:

1. PHP: Registers experiments and their enabled status
2. JavaScript: Makes the enabled status available via `window.SCF.experiments.isEnabled()`
3. TypeScript: Provides type safety and better developer experience

## Future Improvements

- Move more JavaScript code to TypeScript
- Add unit tests for TypeScript code
- Implement more sophisticated experiment tracking 