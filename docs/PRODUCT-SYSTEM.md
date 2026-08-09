# WhatsApp Product System

This document describes the WhatsApp-queryable product system implemented in the Humano project.

## Implemented features

### Core functionality
- **Product catalog** queryable via WhatsApp
- **Keyword search** (products, services, hosting, domain, etc.)
- **Category grouping** for clearer organization
- **Detailed product information** (name, price, description)
- **Twilio integration** for automatic replies
- **AI assistant** (Humano Assistant replies): can list the catalog by category, search by name or **code/SKU**, and use `add_to_whatsapp_cart` for the number writing via WhatsApp (or the recipient in the web chat). The customer can still use *cart* and *checkout*.

### Available commands
- `productos` - View full catalog
- `servicios` - View service list
- `catalogo` - View organized catalog
- `precios` - View pricing information
- `hosting` - Query hosting services
- `dominio` - Query domain services
- `desarrollo` - Query development services

## Created/modified files

### New files
- `app/Models/Product.php` - Product model
- `database/migrations/xxxx_create_products_table.php` - Products migration
- `database/factories/ProductFactory.php` - Factory for test products
- `database/seeders/ProductSeeder.php` - Seeder for Team Demo
- `app/Console/Commands/TestProductSystem.php` - Test command
- `config/shopping_cart.php` - Cart configuration

### Modified files
- `app/Services/TwilioService.php` - Added product functionality

## Database structure

### `products` table
```sql
CREATE TABLE products (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    currency_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    status BOOLEAN DEFAULT TRUE,
    whatsapp_enabled BOOLEAN DEFAULT TRUE,
    team_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (currency_id) REFERENCES currencies(id),
    FOREIGN KEY (category_id) REFERENCES categories(id),
    FOREIGN KEY (team_id) REFERENCES teams(id)
);
```

## Installation and configuration

### 1. Install dependencies
```bash
composer require "darryldecode/cart"
```

### 2. Publish configuration
```bash
php artisan vendor:publish --provider="Darryldecode\Cart\CartServiceProvider" --tag="config"
```

### 3. Run migrations
```bash
php artisan migrate
```

### 4. Run seeders
```bash
php artisan db:seed --class=ProductSeeder
```

## System testing

### Test command
```bash
php artisan test:products 5491112345678
```

### Manual tests
Send WhatsApp messages with:
- "productos"
- "servicios"
- "catalogo"
- "hosting"

## WhatsApp flow

### 1. User sends a message
```
User: "productos"
```

### 2. System detects the command
- Analyzes keywords
- Identifies the product command
- Runs `processProductCommands()`

### 3. System responds
- Fetches active products
- Groups by category
- Formats the reply with emojis
- Sends the full catalog

### 4. Sample response
```
🛍️ Product & Service Catalog

📂 Hosting
• Basic Web Hosting
  💰 $29.99
  📝 Web hosting with 10GB SSD storage...

• Premium Web Hosting
  💰 $59.99
  📝 Premium web hosting with 50GB storage...

💡 To purchase:
• buy [name] or buy [code]
• Or contact support: https://revisionalpha.com/contactenos

🛒 Your cart: Type cart to view your selected products
```

## Team Demo products

### Hosting and domains
- **Basic Web Hosting** - $29.99/month
- **Premium Web Hosting** - $59.99/month
- **.com Domain** - $19.99/year
- **.net Domain** - $24.99/year

### Security and certificates
- **Basic SSL Certificate** - $49.99/year
- **Wildcard SSL Certificate** - $199.99/year
- **Automatic Backup** - $15.99/month

### Development and consulting
- **Basic Web Development** - $999.99
- **Premium Web Development** - $2,499.99
- **Basic Mobile App** - $1,499.99
- **IT Consulting** - $199.99/session

### Support and services
- **Basic Technical Support** - $79.99/month
- **Premium Technical Support** - $149.99/month
- **Server Migration** - $299.99
- **SEO Optimization** - $399.99

## Next steps

### Pending features
- [ ] **Cart system** with Laravel Shopping Cart
- [ ] **"contratar" command** to add products to the cart
- [ ] **"carrito" command** to view selected products
- [ ] **Checkout process** via WhatsApp
- [ ] **Payment gateway integration**
- [ ] **Order notifications** to administrators

### Technical improvements
- [ ] **Product cache** for better performance
- [ ] **Advanced search** by name or description
- [ ] **Price and category filters**
- [ ] **Product images** in replies
- [ ] **Inventory and availability system**

## Troubleshooting

### Error: "No hay productos disponibles"
*(Spanish locale app message: "No products available")*
- Verify that products exist in the database
- Run `php artisan db:seed --class=ProductSeeder`
- Verify that `status = true` and `whatsapp_enabled = true`

### Error: "No hay categorías disponibles"
*(Spanish locale app message: "No categories available")*
- Run `php artisan db:seed --class=CategorySeeder`
- Verify that categories exist in the database

### Error: "No hay monedas disponibles"
*(Spanish locale app message: "No currencies available")*
- Run `php artisan db:seed --class=CurrencySeeder`
- Verify that currencies exist in the database

## Support

For technical issues or questions about the product system:
- **Email**: soporte@revisionalpha.com
- **WhatsApp**: +54 9 11 1234-5678
- **Website**: https://revisionalpha.com/contactenos

---

**Developed by:** Humano Development Team  
**Last updated:** August 2025  
**Version:** 1.0.0
