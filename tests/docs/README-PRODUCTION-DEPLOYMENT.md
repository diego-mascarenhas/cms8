# 🚀 HUMANO-CORE PRODUCTION DEPLOYMENT

Complete deployment system for `idoneo/humano-core` package in production environments.

---

## 📋 **OVERVIEW**

This deployment system provides a safe, automated way to install the `idoneo/humano-core v1.1.0` package in production environments with comprehensive backup and rollback capabilities.

### **Package Details:**
- **Package**: `idoneo/humano-core`
- **Version**: `v1.1.0`
- **Packagist**: https://packagist.org/packages/idoneo/humano-core
- **GitHub**: https://github.com/diego-mascarenhas/humano-core
- **Features**: Team Settings, SMTP/IMAP/Stripe/Twilio testing, API tokens, Custom translations

---

## 📁 **FILES INCLUDED**

| File | Purpose | Status |
|------|---------|--------|
| `tests/deploy-humano-core.sh` | Automated deployment script | ✅ Executable |
| `tests/rollback-humano-core.sh` | Automated rollback script | ✅ Executable |
| `tests/docs/PRODUCTION-DEPLOYMENT.md` | Comprehensive deployment guide | ✅ Documentation |
| `composer.json.backup-before-packagist` | Composer backup for reference | ✅ Backup |

---

## 🎯 **QUICK START**

### **Option A: Automated Script (Recommended)**
```bash
# Deploy package
./tests/deploy-humano-core.sh

# If issues occur, rollback
./tests/rollback-humano-core.sh
```

### **Option B: Manual Installation**
```bash
# Install package
composer require idoneo/humano-core:^1.1.0

# Publish assets
php artisan vendor:publish --tag="humano-core-config"

# Clear caches
php artisan cache:clear && php artisan config:clear
```

---

## 🔧 **DEPLOYMENT SCRIPT FEATURES**

### **deploy-humano-core.sh includes:**
- ✅ **Pre-deployment checks** (Laravel, Composer, Database)
- ✅ **Automatic backups** (composer.lock, .env)
- ✅ **Package installation** from Packagist
- ✅ **Asset publishing** (config, migrations)
- ✅ **Cache optimization** for production
- ✅ **Verification tests** (package, routes, config)
- ✅ **Colored output** for clear progress tracking
- ✅ **Error handling** with exit codes

### **rollback-humano-core.sh includes:**
- ✅ **Safety confirmations** before rollback
- ✅ **Package removal** with cleanup
- ✅ **Backup restoration** (composer.lock)
- ✅ **Configuration cleanup**
- ✅ **Cache rebuilding**
- ✅ **Verification tests**
- ✅ **Complete rollback** with status reporting

---

## 📊 **WHAT GETS INSTALLED**

### **Core Components:**
- **TeamSettingController** (44KB) - Complete team settings management
- **Models**: Team, TeamSetting, CustomTranslation
- **Request Validation**: UpdateTeamSettingsRequest
- **Views**: 5 professional team-settings Blade templates
- **Configuration**: humano-core.php config file

### **Functionality Included:**
- ✅ **SMTP Configuration** with connection testing
- ✅ **IMAP Setup** with mailbox verification
- ✅ **Stripe Integration** with API validation
- ✅ **Twilio Communication** with SMS/WhatsApp testing
- ✅ **API Token Management** for team access
- ✅ **Custom Translations** with multi-language support
- ✅ **Team Valorations** system

---

## 🛡️ **SAFETY FEATURES**

### **Automatic Backups:**
- `composer.lock.backup-before-humano-core-TIMESTAMP`
- `.env.backup-before-humano-core-TIMESTAMP`
- `config/humano-core.php.removed-TIMESTAMP` (during rollback)

### **Verification Steps:**
- Package installation verification
- Route availability checking
- Configuration accessibility testing
- Laravel application health check

### **Rollback Safety:**
- User confirmation required
- Backup restoration
- Dependency reinstallation
- Complete cleanup

---

## 🧪 **TESTING CHECKLIST**

After deployment, verify these features work:

| Feature | URL | Test Action |
|---------|-----|-------------|
| **Settings Access** | `/team/1/settings` | Page loads without errors |
| **SMTP Testing** | Settings → SMTP tab | Test connection button works |
| **IMAP Testing** | Settings → IMAP tab | Mailbox connection test |
| **Stripe Testing** | Settings → Stripe tab | API key validation |
| **Twilio Testing** | Settings → Twilio tab | SMS/WhatsApp test |
| **API Tokens** | `/team/1/api-tokens` | Token generation/revocation |
| **Translations** | `/team/1/custom-translations` | Add/edit translations |

---

## 📞 **TROUBLESHOOTING**

### **Common Issues:**

#### **Script Permission Denied**
```bash
chmod +x tests/deploy-humano-core.sh
chmod +x tests/rollback-humano-core.sh
```

#### **Package Not Found**
```bash
composer clear-cache
composer require idoneo/humano-core:^1.1.0
```

#### **Route Conflicts**
```bash
php artisan route:clear
php artisan cache:clear
```

#### **View Errors**
```bash
php artisan view:clear
php artisan vendor:publish --tag="humano-core-views" --force
```

---

## 🔍 **MONITORING**

### **Log Files to Monitor:**
- `storage/logs/laravel.log` - Application errors
- Web server error logs
- Database slow query logs

### **Performance Metrics:**
- Page load times for `/team/{team}/settings`
- Database query performance for team_settings table
- Memory usage during settings operations

---

## 📈 **VERSION MANAGEMENT**

### **Current Version:** v1.1.0
- Team Settings module complete
- SMTP/IMAP/Stripe/Twilio testing
- API token management
- Custom translations system

### **Update Strategy:**
```bash
# Check for updates
composer outdated | grep humano-core

# Update to latest patch
composer update idoneo/humano-core

# Update to specific version
composer require idoneo/humano-core:^1.2.0
```

---

## 🌐 **ENGLISH NAMING CONVENTION**

All files follow English naming conventions:
- ✅ `tests/deploy-humano-core.sh` (not deploy-humano-core-es.sh)
- ✅ `tests/rollback-humano-core.sh` (not rollback-humano-core-es.sh)
- ✅ `composer.json.backup-before-packagist` (not composer.json.backup-antes-packagist)
- ✅ `tests/docs/PRODUCTION-DEPLOYMENT.md` (not PRODUCCION-DEPLOYMENT.md)

---

## 📞 **SUPPORT**

- **Issues**: https://github.com/diego-mascarenhas/humano-core/issues
- **Documentation**: Package README.md
- **Packagist**: https://packagist.org/packages/idoneo/humano-core

---

## ✅ **SUCCESS CRITERIA**

Deployment is successful when:
- ✅ Scripts execute without errors
- ✅ Package shows in `composer show`
- ✅ Team settings page loads
- ✅ All connection tests work
- ✅ No performance degradation
- ✅ Error logs remain clean

---

**Created**: 2024-08-29
**Package**: idoneo/humano-core v1.1.0
**Environment**: Production-ready
**Language**: English
**Status**: ✅ Ready for deployment
