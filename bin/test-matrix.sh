#!/usr/bin/env bash
#
# test-matrix.sh - Run test suite across all PHP versions (like CI)
#
# Usage:
#   bin/test-matrix.sh                  # Full matrix + PHPCS
#   bin/test-matrix.sh --php 7.4        # Single version
#   bin/test-matrix.sh --php 7.4 --php 8.3  # Multiple versions
#   bin/test-matrix.sh --phpcs-only     # PHPCS only
#   bin/test-matrix.sh --tests-only     # Skip PHPCS
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
PARALLEL=false

while [[ $# -gt 0 ]]; do
	case $1 in
		--php)
			SELECTED_VERSIONS+=("$2")
			shift 2
			;;
		--phpcs-only)
			RUN_TESTS=false
			shift
			;;
		--tests-only)
			RUN_PHPCS=false
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
			echo "  --tests-only     Skip PHPCS, run only PHPUnit"
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
		unit_count=$(grep -oP 'OK \(\K[0-9]+' "$tmpfile" || echo "?")

		# Integration tests
		(cd "$PROJECT_DIR" && "$binary" vendor/bin/phpunit --testsuite=integration 2>&1) > "$tmpfile" || exit_code=$?

		if [ $exit_code -eq 0 ]; then
			integ_count=$(grep -oP 'OK \(\K[0-9]+' "$tmpfile" || echo "?")
		fi
	fi

	local end_time
	end_time=$(date +%s)
	local duration=$(( end_time - start_time ))

	DURATIONS["$version"]="${duration}s"

	if [ $exit_code -eq 0 ]; then
		STATUSES["$version"]="PASS"
		DETAILS["$version"]="${unit_count} unit, ${integ_count} integration"
		echo -e "  PHP ${version}: ${GREEN}PASS${NC} - ${unit_count} unit, ${integ_count} integration (${duration}s)"
	else
		STATUSES["$version"]="FAIL"
		DETAILS["$version"]="see output above"
		HAS_FAILURE=true
		echo -e "  PHP ${version}: ${RED}FAIL${NC} (${duration}s)"
		echo ""
		tail -20 "$tmpfile"
		echo ""
	fi

	rm -f "$tmpfile"
}

# --- Main ---
echo ""
echo -e "${BOLD}+----------------------------------------------+${NC}"
echo -e "${BOLD}|   Ops Health Dashboard - Test Matrix          |${NC}"
echo -e "${BOLD}+----------------------------------------------+${NC}"
echo ""

# Verify PHP binaries
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

# PHPCS
if [ "$RUN_PHPCS" = true ]; then
	echo -e "${BOLD}=== PHPCS (PHP ${PHPCS_PHP}) ===${NC}"
	run_phpcs
	echo ""
fi

# PHPUnit matrix
if [ "$RUN_TESTS" = true ]; then
	echo -e "${BOLD}=== PHPUnit Matrix ===${NC}"

	if [ "$PARALLEL" = true ]; then
		for version in "${SELECTED_VERSIONS[@]}"; do
			run_tests_for_version "$version" &
		done
		wait
	else
		for version in "${SELECTED_VERSIONS[@]}"; do
			run_tests_for_version "$version"
		done
	fi
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
	else
		printf "| %-8s | ${RED}%-6s${NC} | %-8s |\n" "$label" "$status" "$duration"
	fi
}

if [ -n "${STATUSES[phpcs]+x}" ]; then
	print_row "phpcs" "${STATUSES[phpcs]}" "${DURATIONS[phpcs]}"
fi

for version in "${SELECTED_VERSIONS[@]}"; do
	if [ -n "${STATUSES[$version]+x}" ]; then
		print_row "PHP $version" "${STATUSES[$version]}" "${DURATIONS[$version]}"
	fi
done

echo "+----------+--------+----------+"
echo ""

if [ "$HAS_FAILURE" = true ]; then
	echo -e "${RED}Some checks failed!${NC}"
	exit 1
else
	echo -e "${GREEN}All checks passed!${NC}"
	exit 0
fi
