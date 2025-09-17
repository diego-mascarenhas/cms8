# 🚀 PRODUCTION DEPLOYMENT GUIDE
## idoneo/humano-core v1.1.0 - Team Settings Module

---

## 📋 **PRE-DEPLOYMENT CHECKLIST**

### ✅ **Requirements Verification:**
- [ ] Laravel 10+ running in production
- [ ] Composer 2.0+ available
- [ ] Database backup completed
- [ ] Application in maintenance mode
- [ ] Git repository clean state

### ✅ **Current Package Status:**
- **Package**: `idoneo/humano-core`
- **Version**: `v1.1.0`
- **Packagist**: ✅ Published
- **GitHub**: ✅ https://github.com/diego-mascarenhas/humano-core
- **Testing**: ✅ Verified locally

---

## 🛠️ **DEPLOYMENT COMMANDS**

### **STEP 1: Install Package from Packagist**
```bash
# Install the package
composer require idoneo/humano-core:^1.1.0

# Optimize autoloader
composer dump-autoload --optimize
```

### **STEP 2: Publish Package Assets**
```bash
# Publish configuration file
php artisan vendor:publish --tag="humano-core-config"

# Publish migrations (if any)
php artisan vendor:publish --tag="humano-core-migrations"

# Run migrations
php artisan migrate --force
```

### **STEP 3: Clear Caches**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### **STEP 4: Verify Installation**
```bash
# Check package installation
composer show idoneo/humano-core

# Verify routes are loaded
php artisan route:list | grep team-settings

# Test configuration
php artisan config:show humano-core
```

---

## 🔧 **EXPECTED COMPONENTS**

### **✅ What Gets Installed:**
| Component | Location | Status |
|-----------|----------|--------|
| **TeamSettingController** | `vendor/idoneo/humano-core/src/Http/Controllers/` | ✅ 44KB |
| **Team Models** | `vendor/idoneo/humano-core/src/Models/` | ✅ Team, TeamSetting |
| **Request Validation** | `vendor/idoneo/humano-core/src/Http/Requests/` | ✅ UpdateTeamSettingsRequest |
| **Views** | `vendor/idoneo/humano-core/resources/views/` | ✅ 5 team-settings views |
| **Configuration** | `config/humano-core.php` | ✅ Published |

### **✅ Routes Already Available:**
The team-settings routes are **already present** in your main `routes/web.php`:
- ✅ `/team/{team}/settings` - Settings index
- ✅ `/team/{team}/settings/edit` - Edit settings
- ✅ `/team/{team}/test-smtp` - SMTP testing
- ✅ `/team/{team}/test-imap` - IMAP testing
- ✅ `/team/{team}/test-stripe` - Stripe testing
- ✅ `/team/{team}/test-twilio` - Twilio testing
- ✅ `/team/{team}/valorations` - Team valorations
- ✅ `/team/{team}/api-tokens` - API token management
- ✅ `/team/{team}/custom-translations` - Custom translations

---

## ⚠️ **BACKUP & ROLLBACK STRATEGY**

### **BEFORE DEPLOYMENT:**
```bash
# 1. Database backup
mysqldump -u user -p database_name > db_backup_$(date +%Y%m%d_%H%M%S).sql

# 2. Application backup
tar -czf app_backup_$(date +%Y%m%d_%H%M%S).tar.gz /path/to/application

# 3. Composer lock backup
cp composer.lock composer.lock.backup-before-production
```

### **ROLLBACK PROCEDURE:**
```bash
# 1. Remove package
composer remove idoneo/humano-core

# 2. Restore composer.lock
cp composer.lock.backup-before-production composer.lock
composer install --no-dev --optimize-autoloader

# 3. Clear caches
php artisan cache:clear && php artisan config:clear

# 4. Restore database (if needed)
mysql -u user -p database_name < db_backup_file.sql
```

---

## ✅ **POST-DEPLOYMENT VERIFICATION**

### **1. Package Installation Check:**
```bash
# Verify package is installed
composer show | grep humano-core
# Expected: idoneo/humano-core  1.1.0

# Check autoloader
composer dump-autoload --optimize
```

### **2. Functionality Tests:**
| Test | URL | Expected Result |
|------|-----|----------------|
| **Settings Access** | `/team/1/settings` | ✅ Settings page loads |
| **SMTP Test** | Settings → Test SMTP | ✅ Connection test works |
| **IMAP Test** | Settings → Test IMAP | ✅ Connection test works |
| **Stripe Test** | Settings → Test Stripe | ✅ API validation works |
| **Twilio Test** | Settings → Test Twilio | ✅ SMS/WhatsApp test works |
| **API Tokens** | `/team/1/api-tokens` | ✅ Token management works |
| **Translations** | `/team/1/custom-translations` | ✅ Translation editor works |

### **3. Performance Check:**
```bash
# Check response times
curl -w "@curl-format.txt" -o /dev/null -s "https://your-domain.com/team/1/settings"

# Monitor logs
tail -f storage/logs/laravel.log
```

### **4. Database Verification:**
```sql
-- Check required tables exist
SHOW TABLES LIKE '%team%';
SHOW TABLES LIKE '%settings%';
SHOW TABLES LIKE '%translations%';

-- Verify data integrity
SELECT COUNT(*) FROM teams;
SELECT COUNT(*) FROM team_settings;
```

---

## 🔍 **TROUBLESHOOTING**

### **Common Issues:**

#### **1. Package Not Found**
```bash
# Clear composer cache
composer clear-cache
composer require idoneo/humano-core:^1.1.0
```

#### **2. Autoloader Issues**
```bash
composer dump-autoload --optimize
php artisan clear-compiled
php artisan cache:clear
```

#### **3. Route Conflicts**
```bash
# Check for duplicate routes
php artisan route:list | grep team-settings | sort
```

#### **4. View Not Found**
```bash
# Re-publish package assets
php artisan vendor:publish --tag="humano-core-views" --force
php artisan view:clear
```

#### **5. Configuration Issues**
```bash
# Re-publish configuration
php artisan vendor:publish --tag="humano-core-config" --force
php artisan config:clear && php artisan config:cache
```

---

## 📊 **MONITORING & MAINTENANCE**

### **Performance Monitoring:**
- Monitor `/team/{team}/settings` response times
- Check memory usage during Settings operations
- Monitor database queries for team_settings table

### **Update Strategy:**
```bash
# Check for updates
composer outdated | grep humano-core

# Update to latest patch version
composer update idoneo/humano-core

# Update to specific version
composer require idoneo/humano-core:^1.2.0
```

---

## 📞 **SUPPORT**

- **GitHub Issues**: https://github.com/diego-mascarenhas/humano-core/issues
- **Packagist**: https://packagist.org/packages/idoneo/humano-core
- **Documentation**: See README.md in package

---

## 🎯 **SUCCESS CRITERIA**

Deployment is successful when:
- ✅ Package installed without errors
- ✅ All team-settings routes respond correctly
- ✅ SMTP/IMAP/Stripe/Twilio tests work
- ✅ API token management functional
- ✅ Custom translations working
- ✅ No performance degradation
- ✅ No error logs generated

---

**Created**: $(date +%Y-%m-%d)
**Package Version**: idoneo/humano-core v1.1.0
**Tested On**: Laravel 10+, PHP 8.1+
