/**
 * The News Log - Content Refresh Module
 * Handles auto-refresh functionality and content updates
 */

// Import configuration and dependencies
import { CONFIG } from './config.js';
import { 
  highlightElement, 
  updateRefreshStatus, 
  setRefreshButtonLoading 
} from './ui-components.js';
import { 
  updateRelativeTimes, 
  initLoadMoreButton 
} from './article-utils.js';

// State variables
let isRefreshing = false;
let refreshInterval = null;

/**
 * Initialize scroll-aware refresh functionality
 * Pauses auto-refresh when user is reading content
 */
function initScrollAwareRefresh() {
  // Initialize the scroll state
  window.isScrolledDown = false;
  
  // Track when the user is reading content (scrolled down)
  window.addEventListener('scroll', function() {
    // Consider user as "reading" when scrolled more than threshold
    const previousState = window.isScrolledDown;
    window.isScrolledDown = window.scrollY > CONFIG.READING_SCROLL_THRESHOLD;
    
    // Only update if the state changed
    if (previousState !== window.isScrolledDown) {
      updateRefreshStatus(window.isScrolledDown);
    }
  });
}

/**
 * Initialize automatic content refresh functionality
 * - Pauses refresh when user is reading (scrolled down)
 * - Initial check after defined delay
 * - Recurring checks at regular intervals
 */
function initAutoRefresh() {
  // Only set up auto-refresh on the homepage
  if (window.location.pathname.includes('about')) {
    return;
  }
  
  // Set up initial delayed check
  const initialTimer = setTimeout(function() {
    if (!isRefreshing && !window.isScrolledDown) {
      checkForContentUpdates();
      
      // Set up recurring checks
      if (!refreshInterval) {
        refreshInterval = setInterval(function() {
          if (!isRefreshing && !window.isScrolledDown) {
            checkForContentUpdates();
          }
        }, CONFIG.AUTO_REFRESH_INTERVAL);
      }
    }
  }, CONFIG.INITIAL_REFRESH_DELAY);
  
  // Clean up timers when page is unloaded
  window.addEventListener('beforeunload', function() {
    clearTimeout(initialTimer);
    clearInterval(refreshInterval);
  });
}

/**
 * Initialize manual refresh button functionality
 */
function initManualRefresh() {
  const refreshButton = document.getElementById('manual-refresh');
  
  if (!refreshButton) return;
  
  refreshButton.addEventListener('click', function() {
    // Show loading state
    setRefreshButtonLoading(true);
    
    // Perform content refresh (pass callback to reset button)
    checkForContentUpdates(function() {
      setRefreshButtonLoading(false);
    });
  });
}

/**
 * Check for updated content and refresh the page if needed
 * Will not refresh content if user is scrolled down reading
 * 
 * @param {Function} callback - Optional callback function after refresh completes
 */
function checkForContentUpdates(callback) {
  // Only check for fresh content on the homepage
  if (window.location.pathname.includes('about')) {
    if (callback) callback();
    return;
  }
  
  // Don't refresh if user is reading (scrolled down)
  if (window.isScrolledDown && !callback) {
    // If this is an automatic refresh, skip it when user is reading
    // But still allow manual refreshes (callback is provided)
    return;
  }
  
  // Add loading animation to refresh button if exists
  setRefreshButtonLoading(true);
  
  // Set refreshing state
  isRefreshing = true;
  
  // Create a timestamp for cache-busting
  const timestamp = new Date().getTime();
  
  // Use fetch API to get the latest data
  fetch('index.php?refresh=' + timestamp, {
      headers: {
          'X-Requested-With': 'XMLHttpRequest'
      }
  })
  .then(response => response.text())
  .then(html => processContentUpdate(html, callback))
  .catch(error => handleRefreshError(error, callback));
}

/**
 * Process new content from a fetch response
 * 
 * @param {string} html - HTML response from server
 * @param {Function} callback - Optional callback function
 */
function processContentUpdate(html, callback) {
  // Extract just the articles container from the response
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, 'text/html');
  const freshArticles = doc.getElementById('articles-container');
  
  if (freshArticles) {
    // Get current container
    const currentContainer = document.getElementById('articles-container');
    
    // Compare content - only update if different
    if (currentContainer.innerHTML !== freshArticles.innerHTML) {
      updateContentContainer(currentContainer, freshArticles);
    }
  }
  
  // Reset state
  isRefreshing = false;
  setRefreshButtonLoading(false);
  
  // Call callback function if provided
  if (callback) callback();
}

/**
 * Update the content container with new articles
 * 
 * @param {Element} currentContainer - Current articles container
 * @param {Element} freshArticles - New articles container from fetch
 */
function updateContentContainer(currentContainer, freshArticles) {
  // Store current top articles for comparison
  const currentArticleIds = Array.from(
    currentContainer.querySelectorAll('.article-card a')
  ).map(a => a.href);
  
  // Save current scroll position
  const scrollPosition = window.scrollY;
  
  // Update content
  currentContainer.innerHTML = freshArticles.innerHTML;
  
  // Highlight new articles
  highlightNewArticles(currentContainer, currentArticleIds);
  
  // Update the last updated time
  updateLastUpdated(freshArticles);
  
  // Restore scroll position if user was reading
  if (window.isScrolledDown) {
    window.scrollTo(0, scrollPosition);
  }
  
  // Re-initialize functionality for new content
  updateRelativeTimes();
  initLoadMoreButton();
}

/**
 * Highlight articles that are new since last update
 * 
 * @param {Element} container - Articles container
 * @param {Array} previousIds - List of previous article URLs
 */
function highlightNewArticles(container, previousIds) {
  const newArticles = Array.from(container.querySelectorAll('.article-card a'));
  
  newArticles.forEach(articleLink => {
    if (!previousIds.includes(articleLink.href)) {
      // This is a new article, add highlight
      const articleCard = articleLink.closest('.article-card');
      if (articleCard) {
        highlightElement(articleCard);
      }
    }
  });
}

/**
 * Update the "last updated" timestamp
 * 
 * @param {Element} freshContent - Freshly fetched content
 */
function updateLastUpdated(freshContent) {
  const lastUpdated = freshContent.ownerDocument.getElementById('last-updated');
  const currentLastUpdated = document.getElementById('last-updated');
  
  if (lastUpdated && currentLastUpdated) {
    currentLastUpdated.textContent = lastUpdated.textContent;
    currentLastUpdated.setAttribute(
      'data-timestamp', 
      lastUpdated.getAttribute('data-timestamp')
    );
    
    // Highlight the timestamp
    highlightElement(currentLastUpdated);
  }
}

/**
 * Handle errors during content refresh
 * 
 * @param {Error} error - Error object
 * @param {Function} callback - Optional callback function
 */
function handleRefreshError(error, callback) {
  console.error('Error checking for updates:', error);
  
  // Reset state
  isRefreshing = false;
  setRefreshButtonLoading(false);
  
  // Call callback function if provided
  if (callback) callback();
}

// Export functions for use by other modules
export {
  initScrollAwareRefresh,
  initAutoRefresh,
  initManualRefresh,
  checkForContentUpdates
};