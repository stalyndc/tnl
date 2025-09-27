/**
 * The News Log - Core Module
 * Main initialization and coordination of other modules
 */

// Import functionality from other modules
import { CONFIG } from './config.js';
import { 
  initReadingProgress, 
  showLoading, 
  handleNavigationClicks 
} from './ui-components.js';
import { 
  initScrollAwareRefresh, 
  initManualRefresh, 
  checkForContentUpdates 
} from './content-refresh.js';
import { 
  initLoadMoreButton, 
  updateRelativeTimes 
} from './article-utils.js';

/**
 * Initialize all application features
 */
function initApp() {
  // Set up UI components
  initReadingProgress();
  initLoadMoreButton();
  initManualRefresh();
  initScrollAwareRefresh();
  
  // Set up navigation handling
  handleNavigationClicks();
  
  // Set up time updates
  updateRelativeTimes();
  setInterval(updateRelativeTimes, CONFIG.UPDATE_TIMES_INTERVAL);
  
  // Set up last updated highlight observer
  initLastUpdatedHighlight();
  
  // Check for initial loading state
  checkInitialLoadingState();
}

/**
 * Initialize highlight effect for the "Last Updated" element
 */
function initLastUpdatedHighlight() {
  const lastUpdatedElement = document.getElementById("last-updated");
  if (!lastUpdatedElement) return;
  
  // Store initial value
  let lastValue = lastUpdatedElement.textContent;
  
  // Check for changes every few seconds
  setInterval(function() {
    if (lastUpdatedElement.textContent !== lastValue) {
      // Value changed, add highlight class
      lastUpdatedElement.classList.add("highlight");
      
      // Remove highlight class after animation completes
      setTimeout(function() {
        lastUpdatedElement.classList.remove("highlight");
      }, CONFIG.HIGHLIGHT_DURATION);
      
      // Update stored value
      lastValue = lastUpdatedElement.textContent;
    }
  }, CONFIG.CHECK_UPDATES_INTERVAL);
}

/**
 * Check if we need to show the loading state initially
 */
function checkInitialLoadingState() {
  const isAboutPage = window.location.pathname.includes('about');
  
  if (!isAboutPage && 
      document.querySelector('.articles-list') === null && 
      document.querySelector('.error-message') === null && 
      document.querySelector('.empty-message') === null) {
    showLoading();
  } else if (isAboutPage) {
    // Immediately hide loading on about page
    const loading = document.getElementById("loading");
    if (loading) {
      loading.style.display = "none";
    }
    
    // Make sure about content is visible
    const aboutSection = document.querySelector('.about-section');
    if (aboutSection) {
      aboutSection.style.opacity = "1";
    }
  }
}

// Initialize the application when DOM is ready
document.addEventListener("DOMContentLoaded", initApp);

// Export functions that might be needed by other modules
export { initApp };