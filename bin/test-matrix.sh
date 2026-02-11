#!/usr/bin/env bash
#
# test-matrix.sh - Run test suite across all PHP versions (like CI)
#
# Usage:
#   bin/test-matrix.sh                  # Full matrix + PHPCS + PHPStan + E2E
#   bin/test-matrix.sh --php 7.4        # Single version
#   bin/test-matrix.sh --php 7.4 --php 8.3  # Multiple versions
#   bin/test-matrix.sh --phpcs-only     # PHPCS only
#   bin/test-matrix.sh --tests-only     # Skip PHPCS, PHPStan and E2E
#   bin/test-matrix.sh --e2e-only       # E2E only
#   bin/test-matrix.sh --no-e2e         # Skip E2E
#   bin/test-matrix.sh --parallel       # Run versions in parallel
#
set -uo pipefail

# --- Configuration ---
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ALL_VERSIONS=(7.4 8.0 8.1 8.2 8.3 8.4 8.5)
PHPCS_PHP="8.3"

# --- Colors ---
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# --- Parse arguments ---
SELECTED_VERSIONS=()
RUN_PHPCS=true
RUN_TESTS=true
RUN_E2E=true
PARALLEL=false

while [[ $# -gt 0 ]]; do
	case $1 in
		--php)
			SELECTED_VERSIONS+=("$2")
			shift 2
			;;
		--phpcs-only)
			RUN_TESTS=false
			RUN_E2E=false
			shift
			;;
		--tests-only)
			RUN_PHPCS=false
			RUN_E2E=false
			shift
			;;
		--e2e-only)
			RUN_PHPCS=false
			RUN_TESTS=false
			shift
			;;
		--no-e2e)
			RUN_E2E=false
			shift
			;;
		--parallel)
			PARALLEL=true
			shift
			;;
		--help|-h)
			echo "Usage: $0 [OPTIONS]"
			echo ""
			echo "Options:"
			echo "  --php VERSION    Run only this PHP version (repeatable)"
			echo "  --phpcs-only     Run only PHPCS check"
			echo "  --tests-only     Skip PHPCS, PHPStan and E2E, run only PHPUnit"
			echo "  --e2e-only       Run only E2E tests (Playwright + wp-env)"
			echo "  --no-e2e         Skip E2E tests"
			echo "  --parallel       Run PHP versions in parallel"
			echo "  --help, -h       Show this help"
			exit 0
			;;
		*)
			echo -e "${RED}Unknown option: $1${NC}"
			exit 1
			;;
	esac
done

if [ ${#SELECTED_VERSIONS[@]} -eq 0 ]; then
	SELECTED_VERSIONS=("${ALL_VERSIONS[@]}")
fi

# --- Results tracking ---
declare -A STATUSES
declare -A DURATIONS
declare -A DETAILS
HAS_FAILURE=false
RESULTS_DIR=""

# --- Helper: check PHP binary exists ---
check_php_binary() {
	local version=$1
	local binary="php${version}"
	if ! command -v "$binary" &>/dev/null; then
		echo -e "${RED}  php${version} not found. Install with: sudo apt install php${version}-cli${NC}"
		return 1
	fi
	return 0
}

# --- Run PHPCS ---
run_phpcs() {
	local start_time
	start_time=$(date +%s)
	local output
	local exit_code=0

	output=$(cd "$PROJECT_DIR" && "php${PHPCS_PHP}" vendor/bin/phpcs 2>&1) || exit_code=$?

	local end_time
	end_time=$(date +%s)
	local duration=$(( end_time - start_time ))

	DURATIONS["phpcs"]="${duration}s"

	if [ $exit_code -eq 0 ]; then
		STATUSES["phpcs"]="PASS"
		echo -e "  PHPCS: ${GREEN}PASS${NC} (${duration}s)"
	else
		STATUSES["phpcs"]="FAIL"
		HAS_FAILURE=true
		echo -e "  PHPCS: ${RED}FAIL${NC} (${duration}s)"
		echo "$output" | tail -20
	fi
}

# --- Run PHPStan ---
run_phpstan() {
	local start_time
	start_time=$(date +%s)
	local output
	local exit_code=0

	output=$(cd "$PROJECT_DIR" && "php${PHPCS_PHP}" vendor/bin/phpstan analyse 2>&1) || exit_code=$?

	local end_time
	end_time=$(date +%s)
	local duration=$(( end_time - start_time ))

	DURATIONS["phpstan"]="${duration}s"

	if [ $exit_code -eq 0 ]; then
		STATUSES["phpstan"]="PASS"
		echo -e "  PHPStan: ${GREEN}PASS${NC} (${duration}s)"
	else
		STATUSES["phpstan"]="FAIL"
		HAS_FAILURE=true
		echo -e "  PHPStan: ${RED}FAIL${NC} (${duration}s)"
		echo "$output" | tail -20
	fi
}

# --- Run PHPUnit for one version ---
run_tests_for_version() {
	local version=$1
	local binary="php${version}"
	local start_time
	start_time=$(date +%s)
	local exit_code=0
	local unit_count=""
	local integ_count=""
	local tmpfile

	tmpfile=$(mktemp)

	# Unit tests
	(cd "$PROJECT_DIR" && "$binary" vendor/bin/phpunit --testsuite=unit 2>&1) > "$tmpfile" || exit_code=$?

	if [ $exit_code -eq 0 ]; then
		# Handles both "OK (N tests, ...)" and "Tests: N, ..." (when skipped/incomplete).
		unit_count=$(grep -oP 'OK \(\K[0-9]+' "$tmpfile" || grep -oP 'Tests: \K[0-9]+' "$tmpfile" || echo "?")

		# Integration tests
		(cd "$PROJECT_DIR" && "$binary" vendor/bin/phpunit --testsuite=integration 2>&1) > "$tmpfile" || exit_code=$?

		if [ $exit_code -eq 0 ]; then
			integ_count=$(grep -oP 'OK \(\K[0-9]+' "$tmpfile" || grep -oP 'Tests: \K[0-9]+' "$tmpfile" || echo "?")
		fi
	fi

	local end_time
	end_time=$(date +%s)
	local duration=$(( end_time - start_time ))

	local status
	local details

	if [ $exit_code -eq 0 ]; then
		status="PASS"
		details="${unit_count} unit, ${integ_count} integration"
		echo -e "  PHP ${version}: ${GREEN}PASS${NC} - ${details} (${duration}s)"
	else
		status="FAIL"
		details="see output"
		echo -e "  PHP ${version}: ${RED}FAIL${NC} (${duration}s)"
		echo ""
		tail -20 "$tmpfile"
		echo ""
	fi

	# Salva risultati: su file per modalita' parallela, su array per sequenziale.
	if [ -n "$RESULTS_DIR" ]; then
		printf '%s\n' "$status" "${duration}s" "$details" > "${RESULTS_DIR}/${version}.result"
	else
		STATUSES["$version"]="$status"
		DURATIONS["$version"]="${duration}s"
		DETAILS["$version"]="$details"
		if [ "$status" = "FAIL" ]; then
			HAS_FAILURE=true
		fi
	fi

	rm -f "$tmpfile"
}

# --- Run E2E (Playwright + wp-env) ---
run_e2e() {
	local start_time
	start_time=$(date +%s)
	local exit_code=0
	local tmpfile
	tmpfile=$(mktemp)
	local e2e_count=""

	# Check prerequisites
	if ! command -v npm &>/dev/null; then
		STATUSES["e2e"]="SKIP"
		DURATIONS["e2e"]="0s"
		echo -e "  E2E: ${YELLOW}SKIP${NC} (npm not found)"
		rm -f "$tmpfile"
		return
	fi

	if ! command -v docker &>/dev/null; then
		STATUSES["e2e"]="SKIP"
		DURATIONS["e2e"]="0s"
		echo -e "  E2E: ${YELLOW}SKIP${NC} (Docker not found)"
		rm -f "$tmpfile"
		return
	fi

	# Install npm dependencies if needed
	if [ ! -d "$PROJECT_DIR/node_modules" ]; then
		echo -e "  Installing npm dependencies..."
		(cd "$PROJECT_DIR" && npm ci 2>&1) > "$tmpfile" || exit_code=$?
		if [ $exit_code -ne 0 ]; then
			STATUSES["e2e"]="FAIL"
			DURATIONS["e2e"]="0s"
			HAS_FAILURE=true
			echo -e "  E2E: ${RED}FAIL${NC} (npm ci failed)"
			tail -10 "$tmpfile"
			rm -f "$tmpfile"
			return
		fi
	fi

	# Start wp-env
	echo -e "  Starting wp-env..."
	(cd "$PROJECT_DIR" && npm run env:start 2>&1) > "$tmpfile" || exit_code=$?
	if [ $exit_code -ne 0 ]; then
		local end_time
		end_time=$(date +%s)
		STATUSES["e2e"]="FAIL"
		DURATIONS["e2e"]="$(( end_time - start_time ))s"
		HAS_FAILURE=true
		echo -e "  E2E: ${RED}FAIL${NC} (wp-env start failed)"
		tail -10 "$tmpfile"
		rm -f "$tmpfile"
		return
	fi

	# Create test users
	echo -e "  Creating test users..."
	(cd "$PROJECT_DIR" && bash bin/e2e-setup.sh 2>&1) > "$tmpfile" || true

	# Run Playwright tests (dot reporter for real-time progress)
	echo -e "  Running Playwright tests..."
	exit_code=0
	(cd "$PROJECT_DIR" && npx playwright test --reporter=dot 2>&1) | tee "$tmpfile" || exit_code=$?
	echo ""

	# Strip ANSI codes and parse test count (e.g. "138 passed")
	local clean_output
	clean_output=$(sed 's/\x1b\[[0-9;]*m//g' "$tmpfile")
	e2e_count=$(echo "$clean_output" | grep -oP '[0-9]+ passed' | head -1 || echo "")

	# Stop wp-env
	echo -e "  Stopping wp-env..."
	(cd "$PROJECT_DIR" && npm run env:stop 2>&1) > /dev/null || true

	local end_time
	end_time=$(date +%s)
	local duration=$(( end_time - start_time ))

	DURATIONS["e2e"]="${duration}s"

	if [ $exit_code -eq 0 ]; then
		STATUSES["e2e"]="PASS"
		echo -e "  E2E: ${GREEN}PASS${NC} - ${e2e_count} (${duration}s)"
	else
		STATUSES["e2e"]="FAIL"
		HAS_FAILURE=true
		local failed_count
		failed_count=$(echo "$clean_output" | grep -oP '[0-9]+ failed' | head -1 || echo "")
		echo -e "  E2E: ${RED}FAIL${NC} - ${e2e_count} ${failed_count} (${duration}s)"
	fi

	rm -f "$tmpfile"
}

# --- Main ---
echo ""
echo -e "${BOLD}+----------------------------------------------+${NC}"
echo -e "${BOLD}|   Ops Health Dashboard - Test Matrix          |${NC}"
echo -e "${BOLD}+----------------------------------------------+${NC}"
echo ""

# Verify PHP binaries (skip if only running E2E)
if [ "$RUN_PHPCS" = true ] || [ "$RUN_TESTS" = true ]; then
	MISSING=false
	for version in "${SELECTED_VERSIONS[@]}"; do
		if ! check_php_binary "$version"; then
			MISSING=true
		fi
	done
	if [ "$MISSING" = true ]; then
		echo ""
		echo -e "${RED}Some PHP versions are missing. Aborting.${NC}"
		exit 1
	fi
fi

# PHPCS
if [ "$RUN_PHPCS" = true ]; then
	echo -e "${BOLD}=== PHPCS (PHP ${PHPCS_PHP}) ===${NC}"
	run_phpcs
	echo ""

	echo -e "${BOLD}=== PHPStan (PHP ${PHPCS_PHP}) ===${NC}"
	run_phpstan
	echo ""
fi

# PHPUnit matrix
if [ "$RUN_TESTS" = true ]; then
	echo -e "${BOLD}=== PHPUnit Matrix ===${NC}"

	if [ "$PARALLEL" = true ]; then
		RESULTS_DIR=$(mktemp -d)
		for version in "${SELECTED_VERSIONS[@]}"; do
			run_tests_for_version "$version" &
		done
		wait

		# Raccoglie risultati dai file delle subshell.
		for version in "${SELECTED_VERSIONS[@]}"; do
			if [ -f "${RESULTS_DIR}/${version}.result" ]; then
				{
					read -r status
					read -r duration
					read -r details
				} < "${RESULTS_DIR}/${version}.result"
				STATUSES["$version"]="$status"
				DURATIONS["$version"]="$duration"
				DETAILS["$version"]="$details"
				if [ "$status" = "FAIL" ]; then
					HAS_FAILURE=true
				fi
			fi
		done
		rm -rf "$RESULTS_DIR"
		RESULTS_DIR=""
	else
		for version in "${SELECTED_VERSIONS[@]}"; do
			run_tests_for_version "$version"
		done
	fi
	echo ""
fi

# E2E tests
if [ "$RUN_E2E" = true ]; then
	echo -e "${BOLD}=== E2E (Playwright + wp-env) ===${NC}"
	run_e2e
	echo ""
fi

# Summary table
echo -e "${BOLD}=== RESULTS ===${NC}"
echo "+----------+--------+----------+"
echo "| Target   | Status | Duration |"
echo "+----------+--------+----------+"

print_row() {
	local label=$1
	local status=$2
	local duration=$3

	if [ "$status" = "PASS" ]; then
		printf "| %-8s | ${GREEN}%-6s${NC} | %-8s |\n" "$label" "$status" "$duration"
	elif [ "$status" = "SKIP" ]; then
		printf "| %-8s | ${YELLOW}%-6s${NC} | %-8s |\n" "$label" "$status" "$duration"
	else
		printf "| %-8s | ${RED}%-6s${NC} | %-8s |\n" "$label" "$status" "$duration"
	fi
}

if [ -n "${STATUSES[phpcs]+x}" ]; then
	print_row "phpcs" "${STATUSES[phpcs]}" "${DURATIONS[phpcs]}"
fi

if [ -n "${STATUSES[phpstan]+x}" ]; then
	print_row "phpstan" "${STATUSES[phpstan]}" "${DURATIONS[phpstan]}"
fi

for version in "${SELECTED_VERSIONS[@]}"; do
	if [ -n "${STATUSES[$version]+x}" ]; then
		print_row "PHP $version" "${STATUSES[$version]}" "${DURATIONS[$version]}"
	fi
done

if [ -n "${STATUSES[e2e]+x}" ]; then
	print_row "E2E" "${STATUSES[e2e]}" "${DURATIONS[e2e]}"
fi

echo "+----------+--------+----------+"
echo ""

if [ "$HAS_FAILURE" = true ]; then
	echo -e "${RED}Some checks failed!${NC}"
	exit 1
else
	echo -e "${GREEN}All checks passed!${NC}"
	exit 0
fi
