# Testing Guide: Dynamic Messages System

## Objective
Verify that the system automatically creates or removes deliveries when contacts start or stop matching a message's criteria.

---

## Test 1: Filter edit lock

### Steps:
1. Visit `http://humano.test/message/list`
2. Create a new message with:
   - Name: "Dynamic Test 1"
   - Category: Any category that has contacts
   - Contact status: "Lead" (or whichever you have)
   - Activate it and wait for deliveries to be created
3. Edit the message
4. **Verify:** The "Category" and "Contact Status" fields should appear **disabled (grayed out)**
5. **Verify:** Orange warning messages should appear below each field

### Expected result:
- You cannot change category or status when deliveries exist
- Warning messages are clear

---

## Test 2: Dynamic delivery creation

### Preparation:
```bash
# Terminal 1: Clear existing deliveries (optional)
php artisan tinker
>>> App\Models\MessageDelivery::truncate();
>>> exit
```

### Steps:
1. Create a message with:
   - Category: One with 3–5 contacts
   - Status: "Lead"
2. Activate the message from the UI
3. In the terminal, run:
   ```bash
   php artisan campaigns:process-active
   ```
4. Reload the message page and verify:
   - **Subscribers:** Should show the correct number of contacts
   - **Deliveries:** Deliveries should have been created

### Expected result:
- Deliveries are created automatically
- Subscriber count is correct

---

## Test 3: Dynamic system — add contact

### Steps:
1. With the message from Test 2 **active and with deliveries created**
2. Go to Contacts and create/edit a contact so that:
   - It is in the message category
   - It has the message status
3. In the terminal, run:
   ```bash
   php artisan campaigns:process-active
   ```
4. Reload the message page

### Expected result:
- A delivery is created automatically for the new contact
- The subscriber count increases by 1

---

## Test 4: Dynamic system — remove contact

### Steps:
1. With the message active and deliveries **pending** (not sent)
2. Take a contact that has a pending delivery
3. Change it to a different category or status
4. In the terminal, run:
   ```bash
   php artisan campaigns:process-active
   ```
5. Check the message deliveries table

### Expected result:
- The contact's pending delivery is removed automatically
- The subscriber count decreases by 1
- If the delivery was already sent, it is NOT removed (history is preserved)

---

## Test 5: Correct count with filters

### Steps:
1. Create a message with:
   - Category: "CMS+" (or one with several contacts)
   - Status: "Cliente"
2. Before activating, check "General Information":
   - **Contacts:** Should show only those that are "Cliente" in "CMS+"
3. Activate the message and run:
   ```bash
   php artisan campaigns:process-active
   ```
4. Verify that the number of deliveries equals the number of filtered contacts

### Expected result:
- Contact count respects both filters (category + status)
- Deliveries are created only for contacts that match BOTH criteria

---

## Useful commands

```bash
# View deliveries for a specific message
php artisan tinker
>>> App\Models\MessageDelivery::where('message_id', 1)->count()

# View contacts that match a message's criteria
>>> $message = App\Models\Message::find(1);
>>> $message->category->contacts()->where('status_id', $message->contact_status_id)->count();

# Clear all deliveries (CAUTION)
>>> App\Models\MessageDelivery::truncate();

# View scheduler log
tail -f storage/logs/laravel.log | grep "ProcessActiveCampaigns"
```

---

## Test results

| Test | Status | Notes |
|------|--------|-------|
| 1. Edit lock | ⬜ | |
| 2. Dynamic creation | ⬜ | |
| 3. Add contact | ⬜ | |
| 4. Remove contact | ⬜ | |
| 5. Count with filters | ⬜ | |

---

## If something fails

1. **Deliveries are not created:**
   - Verify the message has `status_id = 1` and `started_at` is not null
   - Run `php artisan campaigns:process-active` manually
   - Check the logs: `tail -f storage/logs/laravel.log`

2. **Incorrect count:**
   - Verify the message has `category_id` and `contact_status_id` configured
   - Use tinker to verify the count manually

3. **Fields are not disabled:**
   - Verify the message has at least 1 delivery in `message_deliveries`
   - Clear cache: `php artisan view:clear`
