/**
 * Admin experiments.
 *
 * @package    Secure Custom Fields
 * @since      6.4.3
 */

// Define interfaces for our data structures
interface ExperimentsData {
  [key: string]: boolean; // Just store enabled status
}

// Define the experiments object structure
interface ExperimentsObject {
  [key: string]: boolean | ((name: string) => boolean);
  isEnabled: (name: string) => boolean;
}

// Define a more complete ACF interface
interface ACF {
  experiments?: ExperimentsObject;
  [key: string]: any;
}

// Declare global types
declare global {
  interface Window {
    acf: ACF; // Ensure acf is available on window
    acfExperiments?: ExperimentsData; // Variable created by wp_localize_script
  }
  var acf: ACF;
  var acfExperiments: ExperimentsData | undefined; // Variable created by wp_localize_script
}

// Create a module to avoid global scope augmentation issues
export {};

(function() {
  // End the function if acf doesn't exist
  if (typeof acf !== 'object' || acf === null) {
    return;
  }

  // Add experiments to acf object
  acf.experiments = {
    isEnabled: function(name: string): boolean {
      return this.hasOwnProperty(name) && this[name] === true;
    }
  };

  // Initialize when document is ready
  document.addEventListener('DOMContentLoaded', function() {
    if (!acf.experiments) return;
    
    // Check if acfExperiments exists (created by wp_localize_script)
    if (typeof acfExperiments === 'undefined') {
      return;
    }
    
    // Use the experiments data from wp_localize_script
    if (acfExperiments && acfExperiments.data) {
      // Copy all experiment flags directly to the experiments object
      Object.assign(acf.experiments, acfExperiments.data);
    }
  });

})();
