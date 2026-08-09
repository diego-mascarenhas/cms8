# Collaborators Seeding Guide

This guide explains how to run the collaborator import process from scratch.

## Prerequisites

1. **Collaborators SQL file**: You must have the SQL file with collaborator data at `/Users/magoo/Downloads/inserts_colaboradoras.sql`
2. **Configured database**: Laravel must be configured with database access
3. **Permissions**: The database user must have permission to create and modify tables

## Execution options

### Option 1: Run everything from scratch (recommended)

To run all seeders from scratch (including collaborators):

```bash
# Reset and run all seeders
php artisan migrate:fresh --seed
```

This command:
- Recreates all tables
- Runs all seeders in the correct order
- Automatically includes collaborators at the end

### Option 2: Collaborators only

If the database is already configured and you only want to run the collaborators portion:

```bash
# Run collaborators only (without deleting existing data)
php artisan seed:collaborators

# Run collaborators from scratch (deleting existing data)
php artisan seed:collaborators --fresh
```

### Option 3: Individual seeders

To run specific seeders in order:

```bash
# 1. Base languages
php artisan db:seed --class=LanguageSeeder

# 2. Language variants
php artisan db:seed --class=LanguageVariantSeeder

# 3. Collaborators
php artisan db:seed --class=CollaboratorsSeeder
```

## Execution order

`DatabaseSeeder` runs seeders in this order:

1. **Basic data**: Currencies, countries, roles, and related base data
2. **LanguageSeeder**: Base languages (es, en, fr, de, it, pt, ca, zh, ja, ko, ru, ar, etc.)
3. **LanguageVariantSeeder**: Language variants (es-ES, en-US, fr-FR, etc.)
4. **ContactSeeder**: Basic contacts
5. **CollaboratorsSeeder**: Collaborators imported from SQL

## CollaboratorsSeeder configuration

`CollaboratorsSeeder` is configured to:

- **SQL file**: Read `/Users/magoo/Downloads/inserts_colaboradoras.sql`
- **Default team**: Use the team with ID 1
- **Roles**: Assign the "collaborator" role to created users
- **Combinations**: Automatically process language combinations
- **Duplicates**: Prevent creation of duplicate contacts/users

## Processed data

### Supported base languages
- Spanish (es)
- English (en)
- French (fr)
- German (de)
- Italian (it)
- Portuguese (pt)
- Catalan (ca)
- Chinese (zh)
- Japanese (ja)
- Korean (ko)
- Russian (ru)
- Arabic (ar)
- And many more...

### Supported language variants
- **Spanish**: es-ES, es-MX, es-AR, es-CO, es-CL, es-PE, es-VE
- **English**: en-US, en-GB, en-CA, en-AU
- **French**: fr-FR, fr-CA, fr-BE, fr-CH
- **German**: de-DE, de-AT, de-CH
- **Italian**: it-IT, it-CH
- **Portuguese**: pt-PT, pt-BR
- **Catalan**: ca-ES, ca-AD
- And many more...

## Expected results

After a full seeding run, you should have:

- **~40 base languages** in the `languages` table
- **~50+ language variants** in the `language_variants` table
- **~1400+ collaborator contacts** in the `contacts` table
- **~300+ language combinations** in the `contact_language_variants` table
- **~1000+ collaborator users** in the `users` table

## Troubleshooting

### Error: "Cannot add or update a child row: a foreign key constraint fails"

**Cause**: Missing base languages or variants in the database.

**Solution**: Run the language seeders first:
```bash
php artisan db:seed --class=LanguageSeeder
php artisan db:seed --class=LanguageVariantSeeder
```

### Error: "File not found"

**Cause**: The SQL file is not at the expected path.

**Solution**: Confirm the file is at `/Users/magoo/Downloads/inserts_colaboradoras.sql`

### Error: "Duplicate entry"

**Cause**: The data already exists in the database.

**Solution**: Use the `--fresh` option to clear existing data:
```bash
php artisan seed:collaborators --fresh
```

## Verification

To verify that everything ran correctly:

```bash
# Verify counts
php artisan tinker --execute="
echo 'Languages: ' . App\Models\Language::count() . PHP_EOL;
echo 'Language Variants: ' . App\Models\LanguageVariant::count() . PHP_EOL;
echo 'Contacts: ' . App\Models\Contact::count() . PHP_EOL;
echo 'Language Combinations: ' . App\Models\ContactLanguageVariant::count() . PHP_EOL;
"
```

## Done

Once the process is complete, all collaborators are imported with their language combinations processed and ready for use in the system.
