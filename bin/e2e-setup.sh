#!/usr/bin/env bash
#
# E2E Test Setup
#
# Creates test users for E2E testing with wp-env.
# Run after `npm run env:start`.
#
set -euo pipefail

echo "Creating E2E test users..."

# Create subscriber user.
npx wp-env run cli wp user create subscriber_e2e subscriber_e2e@example.com \
	--role=subscriber \
	--user_pass=subscriber_e2e_pass \
	2>/dev/null || echo "subscriber_e2e already exists"

# Create editor user.
npx wp-env run cli wp user create editor_e2e editor_e2e@example.com \
	--role=editor \
	--user_pass=editor_e2e_pass \
	2>/dev/null || echo "editor_e2e already exists"

echo "E2E test users ready."

# Verify plugin is active.
echo "Verifying plugin activation..."
npx wp-env run cli wp plugin list --status=active --field=name | grep -q "ops-health-dashboard" \
	&& echo "Plugin is active." \
	|| (echo "Activating plugin..." && npx wp-env run cli wp plugin activate ops-health-dashboard)

echo "E2E setup complete."
