# ⏰ Email Delay Configuration

## 🎯 **New Feature: Configurable Email Delays**

Email sending delays are now configurable through environment variables instead of hardcoded values.

---

## 🔧 **Environment Variables (.env)**

Add these variables to your `.env` file to customize email sending delays:

```env
# Email Delay Configuration
EMAIL_DELAY_BASE_MINUTES=5          # Minutes between each email (default: 5)
EMAIL_DELAY_RANDOM_SECONDS=120      # Random seconds added (0-X, default: 120)
```

---

## ⚙️ **How It Works**

### **Campaign Scheduling (MessageController):**
- **Base Delay**: `EMAIL_DELAY_BASE_MINUTES * contactIndex`
  - Email 1: 0 minutes
  - Email 2: 5 minutes  
  - Email 3: 10 minutes
  - Email 4: 15 minutes

- **Random Delay**: `rand(0, EMAIL_DELAY_RANDOM_SECONDS)`
  - Adds 0-120 seconds randomly to each email
  - Prevents exact simultaneous sending

### **Manual Sending (SendPendingMessages command):**
- Uses `EMAIL_DELAY_RANDOM_SECONDS` with minimum 60 seconds
- Applied as: `rand(60, max(300, EMAIL_DELAY_RANDOM_SECONDS))`

---

## 📊 **Configuration Examples**

### **Default Setup (Current):**
```env
EMAIL_DELAY_BASE_MINUTES=5
EMAIL_DELAY_RANDOM_SECONDS=120
```
**Result:** 5-minute intervals + 0-2 minutes random

### **Faster Sending:**
```env
EMAIL_DELAY_BASE_MINUTES=2
EMAIL_DELAY_RANDOM_SECONDS=60
```
**Result:** 2-minute intervals + 0-1 minute random

### **Slower/Safe Sending:**
```env
EMAIL_DELAY_BASE_MINUTES=10
EMAIL_DELAY_RANDOM_SECONDS=300
```
**Result:** 10-minute intervals + 0-5 minutes random

---

## 🚀 **Implementation Details**

### **Files Modified:**
- ✅ `config/services.php` - Added delay configuration
- ✅ `app/Http/Controllers/MessageController.php` - Uses config values
- ✅ `app/Console/Commands/SendPendingMessages.php` - Uses config values

### **Backward Compatibility:**
- ✅ Default values maintained (5 minutes, 120 seconds)
- ✅ No breaking changes if variables not set
- ✅ Existing campaigns continue working

### **Configuration Access:**
```php
// In your code:
$baseMinutes = config('services.email.delay.base_minutes', 5);
$randomSeconds = config('services.email.delay.random_seconds', 120);
```

---

## 🎯 **Production Recommendations**

### **High Volume Servers:**
```env
EMAIL_DELAY_BASE_MINUTES=10
EMAIL_DELAY_RANDOM_SECONDS=300
```

### **Testing/Development:**
```env
EMAIL_DELAY_BASE_MINUTES=1
EMAIL_DELAY_RANDOM_SECONDS=30
```

### **Standard Production:**
```env
EMAIL_DELAY_BASE_MINUTES=5
EMAIL_DELAY_RANDOM_SECONDS=120
```

---

## ✅ **Verification**

After updating `.env`:

1. **Clear config cache:**
   ```bash
   php artisan config:clear
   ```

2. **Test campaign scheduling:**
   - Create new message campaign
   - Check logs for delay values
   - Verify timing matches your configuration

3. **Monitor sending patterns:**
   - Check message delivery timestamps
   - Ensure proper intervals are maintained
