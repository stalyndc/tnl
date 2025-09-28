

<?php

if (getenv('APP_DEBUG') === 'true') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

// Include functions
require_once 'functions.php';


// Set the title for the page
$pageTitle = "The News Log";

// Pagination controls
$itemsPerPage = 10;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$offset = ($page - 1) * $itemsPerPage;

// Get feed data (now with pagination support)
$feedData = getAllFeeds($itemsPerPage, $offset, true); // true to get total count

$totalCount = isset($feedData['totalCount']) ? (int) $feedData['totalCount'] : (isset($feedData['items']) ? count($feedData['items']) : 0);
$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $itemsPerPage) : 1;

// If someone requests a page past the end, redirect to the last page worth of results
if ($page > $totalPages && $totalPages > 0) {
    $page = $totalPages;
    $offset = ($page - 1) * $itemsPerPage;
    $feedData = getAllFeeds($itemsPerPage, $offset, true);
}

// Recalculate totals after potential bounds adjustment
$totalCount = isset($feedData['totalCount']) ? (int) $feedData['totalCount'] : (isset($feedData['items']) ? count($feedData['items']) : 0);
$totalPages = $totalCount > 0 ? (int) ceil($totalCount / $itemsPerPage) : 1;

$previousPageUrl = $page > 1 ? (($page - 1) === 1 ? '/' : '/?page=' . ($page - 1)) : null;
$nextPageUrl = ($page < $totalPages) ? '/?page=' . ($page + 1) : null;

// Canonical URL helpers
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'thenewslog.org';
$baseUrl = rtrim($scheme . '://' . $host, '/');
$canonicalUrl = $baseUrl . '/';
if ($page > 1) {
    $canonicalUrl = $baseUrl . '/?page=' . $page;
}

// Keep compatibility with the JS enhancement
$initialLimit = $itemsPerPage;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="The News Log - A minimalist tech news aggregator bringing you the latest headlines from top sources around the web.">
    
    <!-- IBM Plex Sans and Mono fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@300;400&family=IBM+Plex+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">

    <link rel="canonical" href="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="A minimalist tech news aggregator bringing you the latest headlines from top sources around the web.">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://thenewslog.org/img/og-image.jpg">
    <meta name="twitter:card" content="summary_large_image">
    
    <!-- Structured Data - Schema.org -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "The News Log",
        "url": "https://thenewslog.org/",
        "description": "A minimalist tech news aggregator bringing you the latest headlines from top sources around the web.",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "https://thenewslog.org/search?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "The News Log",
        "url": "https://thenewslog.org/",
        "logo": "https://thenewslog.org/img/logo.png",
        "sameAs": [
            "https://x.com/Slyndc"
        ]
    }
    </script>
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "https://thenewslog.org/"
            }
        ]
    }
    </script>
    
    <?php if (isset($feedData['items']) && !empty($feedData['items'])): ?>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "ItemList",
        "itemListElement": [
            <?php 
            $itemCount = count($feedData['items']);
            foreach ($feedData['items'] as $index => $item): 
                $position = $index + 1;
            ?>
            {
                "@type": "ListItem",
                "position": <?php echo $position; ?>,
                "item": {
                    "@type": "NewsArticle",
                    "headline": "<?php echo htmlspecialchars(str_replace('"', '\"', $item['title']), ENT_QUOTES, 'UTF-8'); ?>",
                    "url": "<?php echo htmlspecialchars($item['link']); ?>",
                    "datePublished": "<?php echo date('c', $item['timestamp']); ?>",
                    "publisher": {
                        "@type": "Organization",
                        "name": "<?php echo htmlspecialchars($item['source']); ?>"
                    }
                }
            }<?php echo ($index < $itemCount - 1) ? ',' : ''; ?>
            <?php endforeach; ?>
        ]
    }
    </script>
    <?php endif; ?>

    <!-- Google Analytics -->
    <script
        async
        src="https://www.googletagmanager.com/gtag/js?id=G-TQRE4ENQGD"></script>
    <script>
        window.dataLayer = window.dataLayer || [];

        function gtag() {
            dataLayer.push(arguments);
        }
        gtag("js", new Date());
        gtag("config", "G-TQRE4ENQGD");
    </script>
</head>

<body>
    <!-- Reading Progress Indicator -->
    <div id="reading-progress-container">
        <div id="reading-progress-bar"></div>
    </div>

    <header>
        <div class="container">
            <h1><?php echo $pageTitle; ?></h1>
            <nav>
                <ul>
                    <li><a href="/" class="active">Home</a></li>
                    <li><a href="about">About</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <!-- Loading state (hidden by default, shown by JS) -->
        <div id="loading" class="loading-container" style="display: none;">
            <div class="loading-dots"></div>
            <p class="loading-text">Fetching headlines</p>
        </div>

        <!-- Cache timestamp and refresh button -->
        <?php if (isset($feedData['timestamp'])): ?>
            <div class="cache-info">
                <span>Last Updated:</span>
                <span id="last-updated" data-timestamp="<?php echo $feedData['timestamp']; ?>">
                    <?php echo formatTimestamp($feedData['timestamp']); ?>
                </span>
                <span id="refresh-status" class="refresh-status"></span>
                <button id="manual-refresh" class="refresh-button" title="Refresh content">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                    </svg>
                </button>
            </div>
        <?php endif; ?>

        <!-- Articles container -->
        <div id="articles-container">
            <?php if (isset($feedData['error'])): ?>
                <!-- Error state -->
                <div class="error-message">
                    <p><?php echo $feedData['error']; ?></p>
                    <p>Please try again later.</p>
                </div>
            <?php elseif (isset($feedData['items']) && !empty($feedData['items'])): ?>
                <!-- Articles list -->
                <ul class="articles-list">
                    <?php foreach ($feedData['items'] as $item): ?>
                        <li class="article-card" itemscope itemtype="https://schema.org/NewsArticle">
                            <meta itemprop="datePublished" content="<?php echo date('c', $item['timestamp']); ?>">
                            <a href="<?php echo htmlspecialchars($item['link']); ?>" target="_blank" rel="noopener" itemprop="url">
                                <div class="article-content">
                                    <h2 class="article-title" itemprop="headline"><?php echo htmlspecialchars($item['title']); ?></h2>
                                    <div class="article-meta">
                                        <span class="article-source" itemprop="publisher" itemscope itemtype="https://schema.org/Organization">
                                            <meta itemprop="name" content="<?php echo htmlspecialchars($item['source']); ?>">
                                            <?php echo htmlspecialchars($item['source']); ?>
                                        </span>
                                        <?php if (isset($item['timestamp'])): ?>
                                            <span class="article-time" data-timestamp="<?php echo $item['timestamp']; ?>">
                                                <?php echo formatTimestamp($item['timestamp']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($totalPages > 1): ?>
                <nav class="pagination-nav" aria-label="Pagination">
                    <span class="pagination-status">Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?></span>
                    <div class="pagination-links">
                        <?php if ($previousPageUrl !== null): ?>
                            <a class="pagination-link prev" href="<?php echo htmlspecialchars($previousPageUrl, ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                        <?php endif; ?>
                        <?php if ($nextPageUrl !== null): ?>
                            <a class="pagination-link next" href="<?php echo htmlspecialchars($nextPageUrl, ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </nav>
                <?php endif; ?>

            <?php else: ?>
                <!-- Empty state -->
                <div class="empty-message">
                    <p>No articles available right now.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> The News Log</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script type="module" src="js/main.js"></script>
    
    <!-- Progressive Enhancement Script with improved refresh timing -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Initialize auto-refresh functionality
        if (typeof initAutoRefresh === 'function') {
            initAutoRefresh();
        }
        
        // Initialize scroll-aware refresh
        if (typeof initScrollAwareRefresh === 'function') {
            initScrollAwareRefresh();
        }
    });
    </script>
</body>

</html>
