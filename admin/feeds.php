<?php

require_once __DIR__ . '/../functions.php';

session_start();

ensureAdminAuth();

$repo = getFeedRepository();
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
        }
    }
} catch (\Exception $e) {
    $error = $e->getMessage();
}

$feeds = $repo->allWithMeta();
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
            max-width: 960px;
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
        }

        tr.disabled {
            opacity: 0.5;
        }

        .actions form {
            display: inline-block;
            margin-right: 0.5rem;
        }

        input[type="text"],
        input[type="url"] {
            width: 100%;
            padding: 0.5rem;
            margin-bottom: 0.75rem;
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
        input[type="reset"] {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-primary {
            background: #ef233c;
            color: #fff;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: inherit;
        }

        .flash {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
        }

        .flash-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.4);
        }

        .flash-error {
            background: rgba(255, 82, 82, 0.2);
            border: 1px solid rgba(255, 82, 82, 0.4);
        }

        form.inline {
            display: inline;
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

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>URL</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($feeds as $feed): ?>
                    <tr class="<?php echo ($feed['enabled'] ?? true) ? '' : 'disabled'; ?>">
                        <td><?php echo h($feed['id']); ?></td>
                        <td><?php echo h($feed['name'] ?? ''); ?></td>
                        <td><a href="<?php echo h($feed['url'] ?? ''); ?>" target="_blank" rel="noopener"><?php echo h($feed['url'] ?? ''); ?></a></td>
                        <td><?php echo ($feed['enabled'] ?? true) ? 'Enabled' : 'Disabled'; ?></td>
                        <td class="actions">
                            <form method="post" class="inline">
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
</body>

</html>
