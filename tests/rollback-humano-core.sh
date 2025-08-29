#!/bin/bash

# 🔄 HUMANO-CORE ROLLBACK SCRIPT
# Package: idoneo/humano-core
# Author: Laravel Team
# Date: $(date +%Y-%m-%d)

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PACKAGE_NAME="idoneo/humano-core"

echo -e "${RED}🔄 HUMANO-CORE ROLLBACK SCRIPT${NC}"
echo -e "${RED}===============================${NC}"
echo ""
echo -e "${YELLOW}⚠️  WARNING: This will remove the humano-core package${NC}"
echo -e "${YELLOW}⚠️  Make sure you have backups before proceeding${NC}"
echo ""

# Function to print section headers
print_section() {
    echo -e "${BLUE}📋 $1${NC}"
    echo -e "${BLUE}$(printf '=%.0s' {1..40})${NC}"
    echo ""
}

# Function to print success messages
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

# Function to print error messages
print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Function to print warnings
print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# Check if we're in Laravel project
if [ ! -f "artisan" ]; then
    print_error "Not in Laravel project directory. Please run from project root."
    exit 1
fi

# Confirmation prompt
echo -e "${RED}🚨 CONFIRMATION REQUIRED${NC}"
echo ""
read -p "Are you sure you want to rollback humano-core package? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    print_warning "Rollback cancelled by user"
    exit 0
fi

# STEP 1: Pre-rollback checks
print_section "PRE-ROLLBACK CHECKS"

echo "Checking if package is installed..."
if composer show "${PACKAGE_NAME}" > /dev/null 2>&1; then
    CURRENT_VERSION=$(composer show "${PACKAGE_NAME}" | grep "versions" | awk '{print $3}')
    print_success "Package found: ${PACKAGE_NAME} ${CURRENT_VERSION}"
else
    print_warning "Package not found - may already be removed"
    exit 0
fi

echo "Checking for backup files..."
BACKUP_FILES=$(ls composer.lock.backup-before-humano-core-* 2>/dev/null | head -1 || echo "")
if [ -n "$BACKUP_FILES" ]; then
    print_success "Backup files found: $BACKUP_FILES"
else
    print_warning "No backup files found - proceeding anyway"
fi

# STEP 2: Remove package
print_section "REMOVING PACKAGE"

echo "Removing ${PACKAGE_NAME}..."
if composer remove "${PACKAGE_NAME}" --no-interaction; then
    print_success "Package removed successfully"
else
    print_error "Package removal failed"
    exit 1
fi

# STEP 3: Restore backups
print_section "RESTORING BACKUPS"

if [ -n "$BACKUP_FILES" ]; then
    echo "Restoring composer.lock from backup..."
    if cp "$BACKUP_FILES" "composer.lock"; then
        print_success "Composer lock restored from backup"
    else
        print_error "Failed to restore composer.lock"
    fi

    echo "Reinstalling dependencies..."
    if composer install --no-dev --optimize-autoloader --no-interaction; then
        print_success "Dependencies reinstalled"
    else
        print_error "Failed to reinstall dependencies"
        exit 1
    fi
else
    print_warning "No backup to restore - skipping"
fi

# STEP 4: Clean up configuration
print_section "CLEANING CONFIGURATION"

echo "Removing published configuration..."
if [ -f "config/humano-core.php" ]; then
    mv "config/humano-core.php" "config/humano-core.php.removed-$(date +%Y%m%d_%H%M%S)"
    print_success "Configuration file moved to backup"
else
    print_warning "Configuration file not found"
fi

# STEP 5: Cache management
print_section "CACHE MANAGEMENT"

echo "Clearing all caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
print_success "All caches cleared"

echo "Rebuilding optimized files..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_success "Optimized files rebuilt"

# STEP 6: Verification
print_section "ROLLBACK VERIFICATION"

echo "Verifying package removal..."
if ! composer show "${PACKAGE_NAME}" > /dev/null 2>&1; then
    print_success "Package successfully removed"
else
    print_error "Package still present - rollback may have failed"
    exit 1
fi

echo "Checking application status..."
if php artisan --version > /dev/null 2>&1; then
    print_success "Laravel application is functional"
else
    print_error "Laravel application may have issues"
    exit 1
fi

# STEP 7: Final report
print_section "ROLLBACK COMPLETE"

echo -e "${GREEN}🔄 ROLLBACK SUCCESSFUL!${NC}"
echo ""
echo -e "${YELLOW}📊 ROLLBACK SUMMARY:${NC}"
echo -e "• Package removed: ${PACKAGE_NAME}"
echo -e "• Backup restored: $([ -n "$BACKUP_FILES" ] && echo "Yes" || echo "No")"
echo -e "• Configuration: Moved to backup"
echo -e "• Timestamp: $(date +%Y-%m-%d\ %H:%M:%S)"
echo ""
echo -e "${YELLOW}🧪 RECOMMENDED ACTIONS:${NC}"
echo -e "• Test application functionality"
echo -e "• Verify no broken routes or views"
echo -e "• Check database integrity"
echo -e "• Review error logs"
echo ""
echo -e "${YELLOW}📞 IF ISSUES PERSIST:${NC}"
echo -e "• Check storage/logs/laravel.log"
echo -e "• Run: php artisan route:list"
echo -e "• Run: composer dump-autoload"
echo ""
echo -e "${GREEN}✅ Rollback completed successfully!${NC}"
