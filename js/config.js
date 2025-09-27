/**
 * The News Log - Configuration Module
 * Central location for all configuration values
 */

/**
 * Application configuration values
 */
export const CONFIG = {
  // Animation durations (in milliseconds)
  HIGHLIGHT_DURATION: 800,
  TRANSITION_FAST: 200,
  TRANSITION_MEDIUM: 300,
  TRANSITION_SLOW: 500,
  
  // Refresh intervals (in milliseconds)
  INITIAL_REFRESH_DELAY: 120000,  // 2 minutes
  AUTO_REFRESH_INTERVAL: 300000,  // 5 minutes
  CHECK_UPDATES_INTERVAL: 5000,   // 5 seconds
  UPDATE_TIMES_INTERVAL: 60000,   // 1 minute
  
  // UI thresholds
  PROGRESS_BAR_THRESHOLD: 50,      // px scrolled to show progress bar
  READING_SCROLL_THRESHOLD: 200,   // px scrolled to consider "reading"
  
  // Content configuration
  SKELETON_COUNT: 5,              // Number of skeleton cards to show during loading
  
  // Feature flags
  ENABLE_AUTO_REFRESH: true,
  ENABLE_SCROLL_AWARE_REFRESH: true,
  ENABLE_READING_PROGRESS: true
};

/**
 * CSS variable names for easy reference
 * These should match the variable names in your CSS
 */
export const CSS_VARS = {
  DARK_BG: '--dark-bg',
  DARKER_BG: '--darker-bg',
  MEDIUM_BG: '--medium-bg',
  LIGHT_TEXT: '--light-text',
  MUTED_TEXT: '--muted-text',
  ACCENT_RED: '--accent-red',
  ACCENT_RED_HOVER: '--accent-red-hover',
  LINK_HOVER: '--link-hover',
  CARD_BG: '--card-bg',
  CARD_HOVER: '--card-hover',
  BORDER_COLOR: '--border-color',
  
  TRANSITION_FAST: '--transition-fast',
  TRANSITION_MEDIUM: '--transition-medium',
  TRANSITION_SLOW: '--transition-slow'
};