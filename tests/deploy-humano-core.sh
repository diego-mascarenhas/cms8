#!/bin/bash

# 🚀 HUMANO-CORE PRODUCTION DEPLOYMENT SCRIPT
# Package: idoneo/humano-core v1.1.0
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
PACKAGE_VERSION="^1.1.0"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

echo -e "${BLUE}🚀 HUMANO-CORE PRODUCTION DEPLOYMENT${NC}"
echo -e "${BLUE}====================================${NC}"
echo ""
echo -e "${YELLOW}Package: ${PACKAGE_NAME}${NC}"
echo -e "${YELLOW}Version: ${PACKAGE_VERSION}${NC}"
echo -e "${YELLOW}Timestamp: ${TIMESTAMP}${NC}"
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

# STEP 1: Pre-deployment checks
print_section "PRE-DEPLOYMENT CHECKS"

echo "Checking Laravel installation..."
if php artisan --version > /dev/null 2>&1; then
    LARAVEL_VERSION=$(php artisan --version | cut -d' ' -f3)
    print_success "Laravel detected: $LARAVEL_VERSION"
else
    print_error "Laravel not detected or not working"
    exit 1
fi

echo "Checking Composer..."
if composer --version > /dev/null 2>&1; then
    COMPOSER_VERSION=$(composer --version | cut -d' ' -f3)
    print_success "Composer detected: $COMPOSER_VERSION"
else
    print_error "Composer not detected"
    exit 1
fi

echo "Checking database connection..."
if php artisan migrate:status > /dev/null 2>&1; then
    print_success "Database connection successful"
else
    print_warning "Database connection issues detected"
fi

# STEP 2: Create backups
print_section "CREATING BACKUPS"

echo "Creating composer.lock backup..."
if [ -f "composer.lock" ]; then
    cp composer.lock "composer.lock.backup-before-humano-core-${TIMESTAMP}"
    print_success "Composer lock backup created: composer.lock.backup-before-humano-core-${TIMESTAMP}"
else
    print_warning "composer.lock not found"
fi

echo "Creating .env backup..."
if [ -f ".env" ]; then
    cp .env ".env.backup-before-humano-core-${TIMESTAMP}"
    print_success "Environment backup created: .env.backup-before-humano-core-${TIMESTAMP}"
else
    print_warning ".env not found"
fi

# STEP 3: Package installation
print_section "PACKAGE INSTALLATION"

echo "Installing ${PACKAGE_NAME}:${PACKAGE_VERSION}..."
if composer require "${PACKAGE_NAME}:${PACKAGE_VERSION}" --no-interaction; then
    print_success "Package installed successfully"
else
    print_error "Package installation failed"
    exit 1
fi

echo "Optimizing autoloader..."
composer dump-autoload --optimize --no-interaction
print_success "Autoloader optimized"

# STEP 4: Publish assets
print_section "PUBLISHING PACKAGE ASSETS"

echo "Publishing configuration..."
if php artisan vendor:publish --tag="humano-core-config" --force; then
    print_success "Configuration published"
else
    print_warning "Configuration publishing failed or already exists"
fi

echo "Publishing migrations..."
if php artisan vendor:publish --tag="humano-core-migrations" --force; then
    print_success "Migrations published"
else
    print_warning "Migrations publishing failed or no migrations to publish"
fi

echo "Running migrations..."
if php artisan migrate --force; then
    print_success "Migrations executed"
else
    print_warning "Migration execution failed or nothing to migrate"
fi

# STEP 5: Cache management
print_section "CACHE MANAGEMENT"

echo "Clearing caches..."
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
print_success "All caches cleared"

echo "Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
print_success "Production optimization completed"

# STEP 6: Verification
print_section "DEPLOYMENT VERIFICATION"

echo "Verifying package installation..."
if composer show "${PACKAGE_NAME}" > /dev/null 2>&1; then
    INSTALLED_VERSION=$(composer show "${PACKAGE_NAME}" | grep "versions" | awk '{print $3}')
    print_success "Package verified: ${PACKAGE_NAME} ${INSTALLED_VERSION}"
else
    print_error "Package verification failed"
    exit 1
fi

echo "Checking team-settings routes..."
if php artisan route:list | grep -q "team-settings"; then
    ROUTES_COUNT=$(php artisan route:list | grep "team-settings" | wc -l)
    print_success "Team-settings routes found: ${ROUTES_COUNT} routes"
else
    print_warning "Team-settings routes not found (may be normal if routes are in main app)"
fi

echo "Testing configuration access..."
if php artisan config:show humano-core > /dev/null 2>&1; then
    print_success "Configuration accessible"
else
    print_warning "Configuration not accessible"
fi

# STEP 7: Final report
print_section "DEPLOYMENT COMPLETE"

echo -e "${GREEN}🎉 DEPLOYMENT SUCCESSFUL!${NC}"
echo ""
echo -e "${YELLOW}📊 DEPLOYMENT SUMMARY:${NC}"
echo -e "• Package: ${PACKAGE_NAME}"
echo -e "• Version: $(composer show "${PACKAGE_NAME}" | grep "versions" | awk '{print $3}')"
echo -e "• Timestamp: ${TIMESTAMP}"
echo -e "• Backups: composer.lock.backup-before-humano-core-${TIMESTAMP}"
echo -e "• Backups: .env.backup-before-humano-core-${TIMESTAMP}"
echo ""
echo -e "${YELLOW}🧪 RECOMMENDED TESTING:${NC}"
echo -e "• Visit: https://your-domain.com/team/1/settings"
echo -e "• Test: SMTP/IMAP/Stripe/Twilio connections"
echo -e "• Check: API token management"
echo -e "• Verify: Custom translations functionality"
echo ""
echo -e "${YELLOW}📞 SUPPORT:${NC}"
echo -e "• GitHub: https://github.com/diego-mascarenhas/humano-core"
echo -e "• Packagist: https://packagist.org/packages/idoneo/humano-core"
echo ""
echo -e "${GREEN}✅ Ready for production!${NC}"
