#!/bin/bash

# E2E Test Setup Validation Script
# Validates that E2E testing environment is properly configured

set -e

echo "🔍 Validating E2E Test Setup..."
echo "================================"

# Check if we're in the right directory
if [ ! -f "composer.json" ]; then
    echo "❌ Error: Must be run from the backend project root directory"
    exit 1
fi

# Function to check if file exists
check_file() {
    if [ -f "$1" ]; then
        echo "✅ $1"
    else
        echo "❌ $1 (missing)"
        return 1
    fi
}

# Function to check if directory exists
check_directory() {
    if [ -d "$1" ]; then
        echo "✅ $1/"
    else
        echo "❌ $1/ (missing)"
        return 1
    fi
}

echo ""
echo "📁 Checking File Structure..."
echo "-----------------------------"

ERRORS=0

# Check core test files
check_file "tests/E2E/E2ETestCase.php" || ((ERRORS++))
check_file "tests/E2E/SepoliaOTCSwapTest.php" || ((ERRORS++))
check_file "tests/E2E/MultiTokenSwapTest.php" || ((ERRORS++))
check_file "tests/E2E/ErrorScenarioTest.php" || ((ERRORS++))
check_file "tests/E2E/PerformanceTest.php" || ((ERRORS++))

# Check utilities
check_file "src/Utils/SeedPhraseManager.php" || ((ERRORS++))
check_file "src/Utils/BlockchainTestUtils.php" || ((ERRORS++))
check_file "src/Utils/TestWallet.php" || ((ERRORS++))

# Check configuration files
check_file "phpunit.xml" || ((ERRORS++))
check_file "phpunit.e2e.xml" || ((ERRORS++))
check_file ".env" || ((ERRORS++))

# Check scripts
check_file "bin/run-tests.php" || ((ERRORS++))
check_file "docs/E2E_TESTING_GUIDE.md" || ((ERRORS++))

# Check directories
check_directory "tests/E2E" || ((ERRORS++))
check_directory "src/Utils" || ((ERRORS++))
check_directory "bin" || ((ERRORS++))
check_directory "docs" || ((ERRORS++))

echo ""
echo "🔧 Checking PHPUnit Configuration..."
echo "-----------------------------------"

# Check if E2E test suite is configured in phpunit.xml
if grep -q "E2E" phpunit.xml; then
    echo "✅ E2E test suite configured in phpunit.xml"
else
    echo "❌ E2E test suite not found in phpunit.xml"
    ((ERRORS++))
fi

# Check if E2E configuration file has proper structure
if grep -q "tests/E2E" phpunit.e2e.xml; then
    echo "✅ E2E test directory configured in phpunit.e2e.xml"
else
    echo "❌ E2E test directory not found in phpunit.e2e.xml"
    ((ERRORS++))
fi

echo ""
echo "📝 Checking Test Class Structure..."
echo "---------------------------------"

# Check if test classes extend E2ETestCase
for test_file in tests/E2E/*Test.php; do
    if [ -f "$test_file" ]; then
        test_name=$(basename "$test_file")
        if grep -q "extends E2ETestCase" "$test_file"; then
            echo "✅ $test_name extends E2ETestCase"
        else
            echo "❌ $test_name does not extend E2ETestCase"
            ((ERRORS++))
        fi
    fi
done

echo ""
echo "⚙️  Checking Environment Configuration..."
echo "---------------------------------------"

# Check if .env file has required E2E variables
ENV_VARS=(
    "E2E_TESTING_ENABLED"
    "TESTNET_MODE" 
    "SEED_PHRASE"
    "SEPOLIA_RPC_URL"
    "SEPOLIA_CHAIN_ID"
    "SEPOLIA_USDC_CONTRACT"
    "SEPOLIA_USDT_CONTRACT"
)

for var in "${ENV_VARS[@]}"; do
    if grep -q "^${var}=" .env; then
        # Check if it's not a placeholder value
        value=$(grep "^${var}=" .env | cut -d'=' -f2-)
        if [[ "$value" == *"your-"* ]] || [[ "$value" == *"your_"* ]] || [[ "$value" == *"placeholder"* ]]; then
            echo "⚠️  $var (placeholder value)"
        else
            echo "✅ $var"
        fi
    else
        echo "❌ $var (not set)"
        ((ERRORS++))
    fi
done

echo ""
echo "🗂️  Checking Storage Directories..."
echo "----------------------------------"

# Create storage directories if they don't exist
mkdir -p storage
mkdir -p coverage
mkdir -p reports

check_directory "storage" || ((ERRORS++))
check_directory "coverage" || ((ERRORS++))
check_directory "reports" || ((ERRORS++))

# Check if directories are writable
if [ -w "storage" ]; then
    echo "✅ storage/ is writable"
else
    echo "❌ storage/ is not writable"
    ((ERRORS++))
fi

echo ""
echo "📊 Test Coverage Summary..."
echo "-------------------------"

# Count test methods in each file
count_test_methods() {
    local file="$1"
    local count=0
    if [ -f "$file" ]; then
        count=$(grep -c "public function test" "$file" 2>/dev/null || echo "0")
    fi
    echo "$count"
}

echo "Test Classes and Method Counts:"
echo "  - E2ETestCase: $(count_test_methods "tests/E2E/E2ETestCase.php") helper methods"
echo "  - SepoliaOTCSwapTest: $(count_test_methods "tests/E2E/SepoliaOTCSwapTest.php") test methods"
echo "  - MultiTokenSwapTest: $(count_test_methods "tests/E2E/MultiTokenSwapTest.php") test methods"
echo "  - ErrorScenarioTest: $(count_test_methods "tests/E2E/ErrorScenarioTest.php") test methods"
echo "  - PerformanceTest: $(count_test_methods "tests/E2E/PerformanceTest.php") test methods"

TOTAL_TESTS=$(($(count_test_methods "tests/E2E/SepoliaOTCSwapTest.php") + \
                $(count_test_methods "tests/E2E/MultiTokenSwapTest.php") + \
                $(count_test_methods "tests/E2E/ErrorScenarioTest.php") + \
                $(count_test_methods "tests/E2E/PerformanceTest.php")))

echo "  - Total E2E Test Methods: $TOTAL_TESTS"

echo ""
echo "🎯 Next Steps..."
echo "---------------"

if [ $ERRORS -eq 0 ]; then
    echo "🎉 E2E test setup validation passed!"
    echo ""
    echo "To run E2E tests:"
    echo "1. Fund test wallets with Sepolia ETH"
    echo "2. Update .env with actual RPC URLs and seed phrase"
    echo "3. Run: php bin/run-tests.php check-e2e"
    echo "4. Run: php bin/run-tests.php e2e"
    echo ""
    echo "For detailed instructions, see:"
    echo "  docs/E2E_TESTING_GUIDE.md"
else
    echo "❌ E2E test setup validation failed with $ERRORS errors."
    echo ""
    echo "Please fix the issues above before running E2E tests."
    echo "See docs/E2E_TESTING_GUIDE.md for setup instructions."
fi

echo ""
echo "================================"

exit $ERRORS