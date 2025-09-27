/**
 * The News Log - UI Components Module
 * Handles UI interactions, animations, and visual elements
 */

// Import configuration
import { CONFIG } from './config.js';

/**
 * Initialize reading progress indicator
 */
function initReadingProgress() {
  const progressBar = document.getElementById('reading-progress-bar');
  const content = document.querySelector('main');
  
  if (!progressBar || !content) return;
  
  // Calculate and update progress on scroll
  window.addEventListener('scroll', function() {
    // Calculate how far down the page the user has scrolled
    const scrollTop = window.scrollY;
    const contentHeight = content.offsetHeight;
    const windowHeight = window.innerHeight;
    const footerHeight = document.querySelector('footer').offsetHeight;
    
    // Calculate scroll percentage, accounting for the footer
    const scrollPercent = (scrollTop / (contentHeight - windowHeight + footerHeight)) * 100;
    
    // Update the progress bar width
    progressBar.style.width = `${Math.min(scrollPercent, 100)}%`;
    
    // Make progress bar visible once scrolling starts
    if (scrollTop > CONFIG.PROGRESS_BAR_THRESHOLD) {
      document.getElementById('reading-progress-container').classList.add('visible');
    } else {
      document.getElementById('reading-progress-container').classList.remove('visible');
    }
  });
}

/**
 * Set up click handlers for navigation links
 */
function handleNavigationClicks() {
  const links = document.querySelectorAll('a[href]:not([target="_blank"])');

  links.forEach((link) => {
    link.addEventListener("click", function(e) {
      // Don't show loading for external links
      if (this.getAttribute("target") === "_blank") return;

      // Hide current content
      const articlesContainer = document.getElementById("articles-container");
      if (articlesContainer) {
        articlesContainer.style.opacity = "0";
        articlesContainer.style.transition = "opacity 0.3s ease";
      }

      // Show loading spinner or skeletons
      showLoading();
    });
  });
}

/**
 * Display loading state with skeleton screens
 */
function showLoading() {
  const loading = document.getElementById("loading");
  if (!loading) return;

  // Check if we're on the about page
  const isAboutPage = window.location.pathname.includes('about');
  
  // Set appropriate loading text
  const loadingText = isAboutPage ? "Loading page" : "Fetching headlines";

  // Replace spinner with improved loading animation
  loading.innerHTML = `
    <div class="loading-dots"></div>
    <p class="loading-text">${loadingText}</p>
  `;
  loading.style.display = "block";

  // Add skeleton screens after the loading indicator
  const articlesContainer = document.getElementById("articles-container");
  if (articlesContainer && !isAboutPage) {
    // Only add skeletons on the homepage, not about page
    // Create 5 skeleton article cards
    let skeletonHTML = '';
    for (let i = 0; i < CONFIG.SKELETON_COUNT; i++) {
      skeletonHTML += `
        <div class="skeleton-card">
          <div class="skeleton-title"></div>
          <div class="skeleton-meta">
            <div class="skeleton-source"></div>
            <div class="skeleton-time"></div>
          </div>
        </div>
      `;
    }
    
    // Only show skeletons if we don't have articles
    if (!articlesContainer.querySelector('.article-card')) {
      articlesContainer.innerHTML = skeletonHTML;
    }
  }
}

/**
 * Add highlight animation to an element
 * 
 * @param {Element} element - Element to highlight
 * @param {number} duration - Animation duration in milliseconds
 */
function highlightElement(element, duration = CONFIG.HIGHLIGHT_DURATION) {
  if (!element) return;
  
  element.classList.add("highlight");
  
  setTimeout(() => {
    element.classList.remove("highlight");
  }, duration);
}

/**
 * Update the refresh status indicator based on scroll position
 * 
 * @param {boolean} isPaused - Whether auto-refresh is paused
 */
function updateRefreshStatus(isPaused) {
  const refreshStatus = document.getElementById('refresh-status');
  if (!refreshStatus) return;
  
  // Initialize status text on first call if not already set
  if (!refreshStatus.dataset.initialized) {
    refreshStatus.textContent = isPaused ? 'Auto-refresh paused while reading' : 'Auto-refresh active';
    refreshStatus.dataset.initialized = 'true';
    refreshStatus.style.display = 'inline-block';
  }
  
  // Update status without changing layout dimensions
  if (isPaused) {
    // Only update text if state has changed
    if (!refreshStatus.classList.contains('paused')) {
      refreshStatus.textContent = 'Auto-refresh paused while reading';
      refreshStatus.classList.add('paused');
    }
  } else {
    // Only update text if state has changed
    if (refreshStatus.classList.contains('paused')) {
      refreshStatus.textContent = 'Auto-refresh active';
      refreshStatus.classList.remove('paused');
    }
  }
  
  // Ensure consistent display properties
  refreshStatus.style.position = 'relative';
  refreshStatus.style.width = refreshStatus.style.width || '200px';
}

/**
 * Set loading state on refresh button
 * 
 * @param {boolean} isLoading - Whether the button should show loading state
 */
function setRefreshButtonLoading(isLoading) {
  const refreshButton = document.getElementById('manual-refresh');
  if (!refreshButton) return;
  
  if (isLoading) {
    refreshButton.classList.add('loading');
  } else {
    refreshButton.classList.remove('loading');
  }
}

// Export functions for use by other modules
export {
  initReadingProgress,
  handleNavigationClicks,
  showLoading,
  highlightElement,
  updateRefreshStatus,
  setRefreshButtonLoading
};