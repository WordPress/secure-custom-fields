/**
 * Experiments TypeScript
 *
 * @package    Secure Custom Fields
 * @subpackage Admin
 * @since      6.4.2
 */

// Define interfaces for our data structures
interface ExperimentsData {
  [key: string]: boolean; // Just store enabled status
}

interface ScfExperiments {
  data: ExperimentsData;
}

// Declare global types
declare global {
  interface Window {
    scfExperiments?: ScfExperiments;
    SCF?: {
      experiments?: {
        data: ExperimentsData;
        init: () => void;
        isEnabled: (name: string) => boolean;
      };
    };
  }
}

// Create a module to avoid global scope augmentation issues
export {};

// Initialize the SCF namespace if it doesn't exist
if (typeof window.SCF === 'undefined') {
  window.SCF = {};
}

// Initialize experiments module
window.SCF.experiments = {
  data: {},
  init: function(): void {
    // Get experiments data from localized script
    this.data = window.scfExperiments ? window.scfExperiments.data : {};
    
    // Log experiments data to console for debugging
    console.log('SCF Experiments initialized:', this.data);
  },
  isEnabled: function(name: string): boolean {
    return !!this.data[name];
  }
};

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', () => {
  if (window.SCF?.experiments) {
    window.SCF.experiments.init();
  }
});

// Log initial state
console.log("SCF Experiments module loaded");
