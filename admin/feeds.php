<?php

require_once __DIR__ . '/../functions.php';

session_start();

ensureAdminAuth();

$repo = getFeedRepository();
$metricsRepo = getFeedMetricsRepository();
$message = null;
$error = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        validateCsrfToken($_POST['csrf_token'] ?? '');
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'toggle':
                $id = trim($_POST['id'] ?? '');
                $enabled = ($_POST['enabled'] ?? '') === '1';
                if ($id === '') {
                    throw new \Exception('Missing feed id');
                }
                $repo->setEnabled($id, $enabled);
                $message = sprintf("Feed '%s' is now %s.", $id, $enabled ? 'enabled' : 'disabled');
                break;
            case 'add':
                $id = trim($_POST['id'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $url = trim($_POST['url'] ?? '');
                $enabled = isset($_POST['enabled']) && $_POST['enabled'] === '1';

                if ($id === '' || $name === '' || $url === '') {
                    throw new \Exception('All fields are required to add a feed.');
                }

                $repo->add($id, $name, $url, $enabled);
                $message = sprintf("Feed '%s' added.", $id);
                break;
            case 'update':
                $id = trim($_POST['id'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $url = trim($_POST['url'] ?? '');

                if ($id === '' || $name === '' || $url === '') {
                    throw new \Exception('All fields are required to update a feed.');
                }

                $repo->updateDetails($id, $name, $url);
                $message = sprintf("Feed '%s' updated.", $id);
                break;
        }
    }
} catch (\Exception $e) {
    $error = $e->getMessage();
}

$feeds = $repo->allWithMeta();
$metrics = $metricsRepo->all();
$csrfToken = ensureCsrfToken();

function ensureAdminAuth(): void
{
    $user = getenv('FEED_ADMIN_USER') ?: '';
    $pass = getenv('FEED_ADMIN_PASS') ?: '';

    if ($user === '' && $pass === '') {
        return; // no auth configured
    }

    $providedUser = $_SERVER['PHP_AUTH_USER'] ?? null;
    $providedPass = $_SERVER['PHP_AUTH_PW'] ?? null;

    if ($providedUser !== $user || $providedPass !== $pass) {
        header('WWW-Authenticate: Basic realm="Feed Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authentication required.';
        exit;
    }
}

function ensureCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $token): void
{
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new \Exception('Invalid CSRF token');
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatIso(?string $iso): string
{
    if (!$iso) {
        return '—';
    }

    try {
        $dt = new \DateTimeImmutable($iso);
        $dt = $dt->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        return $dt->format('Y-m-d H:i');
    } catch (\Exception $e) {
        return $iso;
    }
}

?><!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feed Management</title>
    <style>
        :root {
            color-scheme: dark light;
            font-family: "IBM Plex Sans", system-ui, -apple-system, sans-serif;
        }

        body {
            margin: 0;
            padding: 0;
            background: #111;
            color: #f5f5f5;
        }

        a {
            color: #ef233c;
        }

        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        h1 {
            margin-top: 0;
            font-size: 2rem;
        }

        h2 {
            margin-top: 2rem;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 2rem;
        }

        th,
        td {
            padding: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: left;
            vertical-align: top;
        }

        tr.disabled {
            opacity: 0.5;
        }

        tr.failure-warning {
            background: rgba(255, 82, 82, 0.15);
        }

        .actions form {
            display: inline-block;
            margin-right: 0.5rem;
        }

        input[type="text"],
        input[type="url"],
        input[type="search"] {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            background: rgba(0, 0, 0, 0.3);
            color: inherit;
        }

        label {
            display: block;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        button,
        input[type="submit"],
        input[type="reset"],
        select {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            background: rgba(255, 255, 255, 0.08);
            color: inherit;
        }

        .btn-primary {
            background: #ef233c;
            color: #fff;
        }

        .flash {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .flash-success {
            background: rgba(76, 175, 80, 0.25);
            border: 1px solid rgba(76, 175, 80, 0.4);
        }

        .flash-error {
            background: rgba(255, 82, 82, 0.25);
            border: 1px solid rgba(255, 82, 82, 0.4);
        }

        .controls {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .controls > div {
            flex: 1 1 200px;
        }

        .add-feed {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 8px;
        }

        .meta {
            font-size: 0.85rem;
            opacity: 0.7;
        }

        .pagination {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .pagination button {
            background: rgba(255, 255, 255, 0.08);
        }

        .inline-input {
            width: 100%;
            padding: 0.4rem;
            margin-bottom: 0.3rem;
        }

        td.metrics {
            font-size: 0.85rem;
            line-height: 1.4;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1>Feed Management</h1>
        <p class="meta">Authenticated via FEED_ADMIN_USER/FEED_ADMIN_PASS. Commands mirror the CLI `feed-admin.php` script.</p>

        <?php if ($message): ?>
            <div class="flash flash-success"><?php echo h($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="flash flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>

        <div class="controls">
            <div>
                <label for="search">Search feeds</label>
                <input type="search" id="search" placeholder="Filter by id, name, or URL">
            </div>
            <div>
                <label for="page-size">Rows per page</label>
                <select id="page-size">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="0">All</option>
                </select>
            </div>
        </div>

        <div class="pagination">
            <button type="button" id="prev-page">Prev</button>
            <span id="pagination-info"></span>
            <button type="button" id="next-page">Next</button>
        </div>

        <table id="feed-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Metrics</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feeds as $feed):
                    $feedMetrics = $metrics[$feed['id']] ?? null;
                    $searchData = strtolower(($feed['id'] ?? '') . ' ' . ($feed['name'] ?? '') . ' ' . ($feed['url'] ?? ''));
                    $rowClasses = [];
                    if (!($feed['enabled'] ?? true)) {
                        $rowClasses[] = 'disabled';
                    }
                    if (($feedMetrics['consecutive_failures'] ?? 0) >= 3) {
                        $rowClasses[] = 'failure-warning';
                    }
                ?>
                    <tr class="<?php echo implode(' ', $rowClasses); ?>" data-search="<?php echo h($searchData); ?>">
                        <td><?php echo h($feed['id']); ?></td>
                        <td>
                            <form method="post" class="inline edit-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="id" value="<?php echo h($feed['id']); ?>">
                                <input class="inline-input" type="text" name="name" value="<?php echo h($feed['name'] ?? ''); ?>" required>
                        </td>
                        <td>
                                <input class="inline-input" type="url" name="url" value="<?php echo h($feed['url'] ?? ''); ?>" required>
                                <button type="submit" class="btn-secondary">Save</button>
                            </form>
                        </td>
                        <td><?php echo ($feed['enabled'] ?? true) ? 'Enabled' : 'Disabled'; ?></td>
                        <td class="metrics">
                            <div><strong>Successes:</strong> <?php echo (int) ($feedMetrics['success_count'] ?? 0); ?></div>
                            <div><strong>Failures:</strong> <?php echo (int) ($feedMetrics['failure_count'] ?? 0); ?></div>
                            <div><strong>Consec. failures:</strong> <?php echo (int) ($feedMetrics['consecutive_failures'] ?? 0); ?></div>
                            <div><strong>Last success:</strong> <?php echo h(formatIso($feedMetrics['last_success'] ?? null)); ?></div>
                            <div><strong>Last failure:</strong> <?php echo h(formatIso($feedMetrics['last_failure'] ?? null)); ?></div>
                            <?php if (!empty($feedMetrics['last_error'])): ?>
                                <div><strong>Last error:</strong> <?php echo h($feedMetrics['last_error']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <form method="post" class="inline toggle-form">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?php echo h($feed['id']); ?>">
                                <input type="hidden" name="enabled" value="<?php echo ($feed['enabled'] ?? true) ? '0' : '1'; ?>">
                                <button type="submit" class="btn-secondary"><?php echo ($feed['enabled'] ?? true) ? 'Disable' : 'Enable'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <section class="add-feed">
            <h2>Add Feed</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
                <input type="hidden" name="action" value="add">

                <label for="id">Feed ID (slug)</label>
                <input type="text" name="id" id="id" required>

                <label for="name">Display Name</label>
                <input type="text" name="name" id="name" required>

                <label for="url">Feed URL</label>
                <input type="url" name="url" id="url" required>

                <label>
                    <input type="checkbox" name="enabled" value="1" checked>
                    Enabled
                </label>

                <div>
                    <button type="submit" class="btn-primary">Add Feed</button>
                </div>
            </form>
        </section>
    </div>

    <script>
        const rows = Array.from(document.querySelectorAll('#feed-table tbody tr'));
        const searchInput = document.getElementById('search');
        const pageSizeSelect = document.getElementById('page-size');
        const paginationInfo = document.getElementById('pagination-info');
        const prevButton = document.getElementById('prev-page');
        const nextButton = document.getElementById('next-page');

        let filteredRows = rows.slice();
        let currentPage = 1;

        function getPageSize() {
            const value = parseInt(pageSizeSelect.value, 10);
            return value === 0 ? filteredRows.length || 1 : value;
        }

        function renderTable() {
            rows.forEach(row => row.style.display = 'none');
            const pageSize = getPageSize();
            const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
            currentPage = Math.max(1, Math.min(currentPage, totalPages));
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            filteredRows.slice(start, end).forEach(row => row.style.display = '');
            paginationInfo.textContent = `Page ${currentPage} of ${totalPages}`;
            prevButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage === totalPages;
        }

        function applyFilter() {
            const term = searchInput.value.trim().toLowerCase();
            filteredRows = rows.filter(row => row.dataset.search.includes(term));
            currentPage = 1;
            renderTable();
        }

        searchInput.addEventListener('input', applyFilter);
        pageSizeSelect.addEventListener('change', renderTable);
        prevButton.addEventListener('click', () => {
            currentPage = Math.max(1, currentPage - 1);
            renderTable();
        });
        nextButton.addEventListener('click', () => {
            const pageSize = getPageSize();
            const totalPages = Math.max(1, Math.ceil(filteredRows.length / pageSize));
            currentPage = Math.min(totalPages, currentPage + 1);
            renderTable();
        });

        document.querySelectorAll('.toggle-form').forEach(form => {
            form.addEventListener('submit', (event) => {
                if (!confirm('Are you sure you want to toggle this feed?')) {
                    event.preventDefault();
                }
            });
        });

        document.querySelectorAll('.edit-form').forEach(form => {
            form.addEventListener('submit', (event) => {
                if (!confirm('Save changes to this feed?')) {
                    event.preventDefault();
                }
            });
        });

        applyFilter();
    </script>
</body>

</html>
