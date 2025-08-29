# 🧪 TESTS & DEPLOYMENT TOOLS

This directory contains testing utilities and deployment tools for the Humano project.

---

## 📁 **DIRECTORY STRUCTURE**

```
tests/
├── docs/                               # Documentation
│   ├── PRODUCTION-DEPLOYMENT.md       # Production deployment guide
│   ├── README-PRODUCTION-DEPLOYMENT.md # User-friendly deployment manual
│   ├── STAGING-DEPLOYMENT-REPORT.md   # Staging deployment results
│   └── ...                            # Other project documentation
├── Feature/                            # Feature tests
├── Unit/                               # Unit tests
├── deploy-humano-core.sh              # 🚀 Production deployment script
├── rollback-humano-core.sh            # 🔄 Emergency rollback script
├── setup-hooks.sh                     # Git hooks setup
└── test_user_api.sh                   # API testing script
```

---

## 🚀 **DEPLOYMENT SCRIPTS**

### **deploy-humano-core.sh**
Automated deployment script for `idoneo/humano-core` package.

**Features:**
- ✅ Pre-deployment validation
- ✅ Automatic backup creation
- ✅ Package installation from Packagist
- ✅ Asset publishing & optimization
- ✅ Route conflict detection & resolution
- ✅ Production cache optimization
- ✅ Comprehensive verification tests

**Usage:**
```bash
# Make executable (if needed)
chmod +x tests/deploy-humano-core.sh

# Run deployment
./tests/deploy-humano-core.sh
```

### **rollback-humano-core.sh**
Emergency rollback script with safety confirmations.

**Features:**
- ✅ User confirmation prompts
- ✅ Package removal with cleanup
- ✅ Backup restoration
- ✅ Cache rebuilding
- ✅ System verification

**Usage:**
```bash
# Make executable (if needed)
chmod +x tests/rollback-humano-core.sh

# Run rollback (use with caution)
./tests/rollback-humano-core.sh
```

---

## 📚 **DOCUMENTATION**

### **For Developers:**
- **`docs/README-PRODUCTION-DEPLOYMENT.md`** - Complete deployment guide
- **`docs/PRODUCTION-DEPLOYMENT.md`** - Technical deployment manual
- **`docs/STAGING-DEPLOYMENT-REPORT.md`** - Staging test results

### **Quick References:**
- **Package**: `idoneo/humano-core v1.1.0`
- **Packagist**: https://packagist.org/packages/idoneo/humano-core
- **GitHub**: https://github.com/diego-mascarenhas/humano-core

---

## ⚡ **QUICK START**

### **Deploy to Production:**
```bash
# From project root
./tests/deploy-humano-core.sh
```

### **Deploy to Staging:**
```bash
# Same script, different environment
./tests/deploy-humano-core.sh
```

### **Emergency Rollback:**
```bash
# If issues occur
./tests/rollback-humano-core.sh
```

---

## 🧪 **TESTING UTILITIES**

| Script | Purpose | Status |
|--------|---------|--------|
| `deploy-humano-core.sh` | Package deployment automation | ✅ Production tested |
| `rollback-humano-core.sh` | Emergency rollback automation | ✅ Production ready |
| `test_user_api.sh` | API endpoint testing | ✅ Available |
| `setup-hooks.sh` | Git hooks configuration | ✅ Available |

---

## 🔧 **MAINTENANCE**

### **Regular Tasks:**
1. **Update scripts** when package versions change
2. **Review documentation** after successful deployments
3. **Test rollback procedures** in staging environment
4. **Backup important configurations** before major updates

### **Monitoring:**
- Check deployment logs for errors
- Monitor package updates on Packagist
- Verify backup files are created correctly
- Test scripts in staging before production use

---

## 📊 **SUCCESS METRICS**

The deployment system has achieved:
- ✅ **Zero-downtime deployments**
- ✅ **Automated backup & recovery**
- ✅ **Route conflict detection & resolution**
- ✅ **Production optimization**
- ✅ **Comprehensive error handling**
- ✅ **User verification & testing**

---

## 📞 **SUPPORT**

For deployment issues or questions:
- Check documentation in `tests/docs/`
- Review staging deployment report
- Consult package repository: https://github.com/diego-mascarenhas/humano-core
- Use rollback script if needed

---

**Last Updated**: 2024-08-29
**Scripts Version**: v1.1.0
**Package Version**: idoneo/humano-core v1.1.0
**Status**: ✅ Production Ready
