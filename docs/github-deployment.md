# GitHub to Hostinger Deployment Notes

The site code is pushed to GitHub and then pulled into Hostinger via Git. Because Hostinger's shared hosting environment does not allow running Composer or other build tools, ensure the following steps happen **before** pushing to GitHub:

1. Run `composer install --no-dev --optimize-autoloader` locally to populate the `vendor/` directory.
2. Commit the updated `composer.lock` and the entire `vendor/` directory.
3. Run automated tests locally (`vendor/bin/phpunit`).
4. Optionally run `scripts/build-release.sh` if you need a zipped artifact; otherwise, push directly.

## Hostinger Pull Workflow
- Configure Hostinger's Git pull to track your GitHub repository.
- After pushing from local, trigger the pull from Hostinger's control panel or via git CLI.
- Verify file permissions on `storage/cache` and `storage/logs` after each pull.
- Clear cached feed files if necessary (`storage/cache/*.json`).

## Continuous Deployment Considerations
- If you want GitHub Actions to build the artifact, create a workflow that runs Composer, tests, and uploads an artifact or deploys via Hostinger's API (if available).
- Ensure secrets (FTP, SSH) are stored securely in GitHub.

