/**
 * The News Log - Main Entry Point
 * Imports and initializes all application modules
 */

// Import configuration
import { CONFIG } from './config.js';

// Import modules
import { initApp } from './core.js';
import { initAutoRefresh } from './content-refresh.js';

// Start the application when document is ready
document.addEventListener('DOMContentLoaded', function() {
  console.log('The News Log - Initializing application');
  
  // Initialize core functionality
  initApp();
  
  // Set up auto-refresh if enabled
  if (CONFIG.ENABLE_AUTO_REFRESH) {
    initAutoRefresh();
  }
  
  console.log('The News Log - Application initialized');
});

// Export any globals that might be needed outside the module system
window.NewsLog = {
  version: '1.1.0',
  lastInitialized: new Date()
};