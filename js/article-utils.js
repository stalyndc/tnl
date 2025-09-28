/**
 * The News Log - Article Utilities Module
 * Handles article rendering, time formatting, and load more functionality
 */

// Import configuration
import { CONFIG } from './config.js';
import { highlightElement } from './ui-components.js';

/**
 * Initialize the "load more" button functionality
 */
function initLoadMoreButton() {
  const loadMoreButton = document.getElementById('load-more-button');
  
  if (!loadMoreButton) return;
  
  loadMoreButton.addEventListener('click', function() {
    // Get current offset and limit from button data attributes
    const offset = parseInt(loadMoreButton.getAttribute('data-offset'));
    const limit = parseInt(loadMoreButton.getAttribute('data-limit'));
    const total = parseInt(loadMoreButton.getAttribute('data-total'));
    
    // Show loading state
    loadMoreButton.classList.add('loading');
    loadMoreButton.textContent = 'Loading...';
    
    // Fetch more articles using AJAX
    fetchMoreArticles(offset, limit, total, loadMoreButton);
  });
}

/**
 * Fetch additional articles from the server
 * 
 * @param {number} offset - Starting offset for pagination
 * @param {number} limit - Number of articles to fetch
 * @param {number} total - Total number of available articles
 * @param {Element} button - Load more button element
 */
function fetchMoreArticles(offset, limit, total, button) {
  // Add timestamp to prevent caching
  const timestamp = new Date().getTime();
  
  fetch(`api/get-more-articles.php?offset=${offset}&limit=${limit}&t=${timestamp}`, {
    headers: {
      'X-Requested-With': 'XMLHttpRequest'
    }
  })
  .then(response => {
    if (!response.ok) {
      // Create a more detailed error
      return response.text().then(text => {
        console.error('Server response:', text);
        throw new Error(`Server returned ${response.status}: ${response.statusText}\nResponse: ${text}`);
      });
    }
    return response.text();
  })
  .then(text => {
    // Check if response is empty
    if (!text || text.trim() === '') {
      console.error('Empty response from server');
      throw new Error('Empty response from server');
    }
    
    try {
      // Try to parse JSON
      return JSON.parse(text);
    } catch (e) {
      console.error('Failed to parse JSON:', e, 'Response was:', text);
      throw new Error('Invalid JSON response from server: ' + text.substring(0, 100));
    }
  })
  .then(data => processMoreArticles(data, offset, total, button))
  .catch(error => {
    console.error('Error loading more articles:', error);
    button.classList.remove('loading');
    button.textContent = 'Error Loading Articles - Try Again';
  });
}

/**
 * Process and render newly fetched articles
 * 
 * @param {Object} data - Response data from the server
 * @param {number} offset - Current pagination offset
 * @param {number} total - Total number of available articles
 * @param {Element} button - Load more button element
 */
function processMoreArticles(data, offset, total, button) {
  if (data.items && data.items.length > 0) {
    // Get the articles list container
    const articlesList = document.querySelector('.articles-list');
    
    // Append new articles
    data.items.forEach(item => {
      const articleElement = createArticleElement(item);
      articlesList.appendChild(articleElement);
      
      // Add highlight class to show it's new content
      highlightElement(articleElement);
    });
    
    // Update button attributes for next load
    const newOffset = offset + data.items.length;
    button.setAttribute('data-offset', newOffset);
    
    // Hide button if we've loaded all articles
    if (newOffset >= total || !data.hasMore) {
      button.parentElement.style.display = 'none';
    }
    
    // Update relative times for new elements
    updateRelativeTimes();
    
    // Reset button state
    button.classList.remove('loading');
    button.textContent = 'Load More Articles';
  } else {
    // No more articles or error
    button.parentElement.style.display = 'none';
  }
}

/**
 * Create article element from item data
 * 
 * @param {object} item - Article data
 * @return {Element} Article list item element
 */
function createArticleElement(item) {
  const li = document.createElement('li');
  li.className = 'article-card';
  li.setAttribute('itemscope', '');
  li.setAttribute('itemtype', 'https://schema.org/NewsArticle');
  
  const meta = document.createElement('meta');
  meta.setAttribute('itemprop', 'datePublished');
  meta.setAttribute('content', new Date(item.timestamp * 1000).toISOString());
  li.appendChild(meta);
  
  const link = document.createElement('a');
  link.href = item.link;
  link.setAttribute('target', '_blank');
  link.setAttribute('rel', 'noopener');
  link.setAttribute('itemprop', 'url');
  
  const content = document.createElement('div');
  content.className = 'article-content';
  
  const title = document.createElement('h2');
  title.className = 'article-title';
  title.setAttribute('itemprop', 'headline');
  title.textContent = item.title;
  
  const metaDiv = document.createElement('div');
  metaDiv.className = 'article-meta';

  let hasTimestamp = false;
  if (typeof item.timestamp === 'number') {
    const time = document.createElement('span');
    time.className = 'article-time';
    time.setAttribute('data-timestamp', item.timestamp);
    time.textContent = formatRelativeTime(Math.floor(Date.now() / 1000) - item.timestamp);
    metaDiv.appendChild(time);
    hasTimestamp = true;
  }

  const sourceList = Array.isArray(item.sources) && item.sources.length > 0
    ? item.sources
    : (item.source ? [{ name: item.source }] : []);

  if (sourceList.length > 0) {
    if (hasTimestamp) {
      const dot = document.createElement('span');
      dot.className = 'article-dot';
      dot.setAttribute('aria-hidden', 'true');
      dot.textContent = '·';
      metaDiv.appendChild(dot);
    }

    const source = document.createElement('span');
    source.className = 'article-source';
    source.setAttribute('itemprop', 'publisher');
    source.setAttribute('itemscope', '');
    source.setAttribute('itemtype', 'https://schema.org/Organization');

    const firstSource = sourceList[0];
    const firstName = firstSource.name || firstSource.id || 'Unknown';
    const sourceNameMeta = document.createElement('meta');
    sourceNameMeta.setAttribute('itemprop', 'name');
    sourceNameMeta.setAttribute('content', firstName);
    source.appendChild(sourceNameMeta);

    const labels = sourceList.slice(0, 3).map((entry) => entry.name || entry.id || 'Unknown');
    source.appendChild(document.createTextNode(labels.join(' · ')));

    metaDiv.appendChild(source);

    if (sourceList.length > 3) {
      const overflow = document.createElement('span');
      overflow.className = 'article-source overflow';
      overflow.textContent = `+${sourceList.length - 3}`;
      metaDiv.appendChild(overflow);
    }
  }
  
  content.appendChild(title);
  content.appendChild(metaDiv);
  
  link.appendChild(content);
  li.appendChild(link);
  
  // Add fade-in animation
  li.style.opacity = '0';
  li.style.animation = 'fadeIn var(--transition-medium) forwards';
  
  return li;
}

/**
 * Updates all relative time elements on the page
 */
function updateRelativeTimes() {
  const timeElements = document.querySelectorAll(
    ".article-time, #last-updated"
  );

  timeElements.forEach((el) => {
    const timestamp = parseInt(el.getAttribute("data-timestamp"));
    if (!timestamp) return;

    el.textContent = formatRelativeTime(Math.floor(Date.now() / 1000) - timestamp);
  });
}

/**
 * Format timestamp to relative time
 *
 * @param {number} diff - Time difference in seconds
 * @return {string} Formatted relative time
 */
function formatRelativeTime(diff) {
  if (diff < 60) {
    return "Just now";
  } else if (diff < 3600) {
    const minutes = Math.floor(diff / 60);
    return `${minutes} minute${minutes > 1 ? "s" : ""} ago`;
  } else if (diff < 86400) {
    const hours = Math.floor(diff / 3600);
    return `${hours} hour${hours > 1 ? "s" : ""} ago`;
  } else if (diff < 172800) {
    return "Yesterday";
  } else {
    const days = Math.floor(diff / 86400);
    return `${days} days ago`;
  }
}

// Export functions for use by other modules
export {
  initLoadMoreButton,
  createArticleElement,
  updateRelativeTimes,
  formatRelativeTime
};
