<?php
// Set the title for the page
$pageTitle = "About | The News Log";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="Learn more about The News Log - a minimalist news aggregator that brings you the latest headlines from top sources around the web.">
    
    <!-- IBM Plex Sans and Mono fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@300;400&family=IBM+Plex+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    
    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="Learn more about The News Log - a minimalist news aggregator that brings you the latest headlines from top sources around the web.">
    <meta property="og:url" content="https://thenewslog.org/about">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://thenewslog.org/img/og-image.jpg">
    <meta name="twitter:card" content="summary_large_image">
    
    <!-- Structured Data - Schema.org -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "AboutPage",
        "name": "About The News Log",
        "url": "https://thenewslog.org/about",
        "description": "Learn more about The News Log - a minimalist news aggregator that brings you the latest headlines from top sources around the web.",
        "isPartOf": {
            "@type": "WebSite",
            "name": "The News Log",
            "url": "https://thenewslog.org/"
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
            },
            {
                "@type": "ListItem",
                "position": 2,
                "name": "About",
                "item": "https://thenewslog.org/about"
            }
        ]
    }
    </script>
    
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
    <header>
        <div class="container">
            <h1>The News Log</h1>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php" class="active">About</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <!-- Loading state (hidden by default, shown by JS) -->
        <div id="loading" class="loading-container" style="display: none;">
            <div class="loading-dots"></div>
            <p class="loading-text">Loading page</p>
        </div>
        
        <section class="about-section" itemscope itemtype="https://schema.org/AboutPage">
            <h2 itemprop="name">About The News Log</h2>

            <div class="about-content" itemprop="mainContentOfPage">
                <h3>What is The News Log?</h3>
                <p>The News Log is a minimalist news aggregator that brings you the latest headlines from top sources around the web. We focus on delivering a clean, distraction-free reading experience.</p>

                <h3>How It Works</h3>
                <p>We collect and curate headlines from trusted news sources using RSS feeds. Headlines are updated regularly to ensure you're always getting fresh content.</p>

                <h3>Our Sources</h3>
                <p>Currently, we aggregate headlines from The Verge, TechCrunch, Engadget and Arstechnica just to name a few. We carefully select quality publications to ensure reliable information.</p>

                <h3>Why Use The News Log?</h3>
                <ul>
                    <li>Clean, distraction-free interface</li>
                    <li>Fast loading headlines</li>
                    <li>Dark theme for comfortable reading</li>
                    <li>Mobile-friendly design</li>
                    <li>Regularly updated content</li>
                </ul>

                <h3>Contact</h3>
                <p>Have suggestions or feedback? We'd love to hear from you. Contact me via X <a href="https://x.com/Slyndc" itemprop="sameAs">@Slyn</a>.</p>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> The News Log</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script type="module" src="js/main.js"></script>
</body>

</html>
