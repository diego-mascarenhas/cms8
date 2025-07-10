# Contact Data Management Command

## Overview

The `contact:manage-data` command provides a comprehensive tool for managing contact data stored in JSON fields. It allows you to view, validate, and import contact data to different sections of the application.

## Features

- **📋 List Contacts**: View all contacts that have data in their JSON field
- **📊 View Data**: Display detailed information about a specific contact's data structure
- **🔍 Validate Data**: Check if contact data is valid for import to different sections
- **📤 Import Data**: Import validated data to enterprises, projects, tasks, or services
- **🛡️ Dry Run**: Preview what would be imported without making changes

## Command Signature

```bash
php artisan contact:manage-data [options]
```

## Options

| Option | Description | Example |
|--------|-------------|---------|
| `--contact-id` | Specific contact ID to manage | `--contact-id=123` |
| `--list` | List all contacts with data | `--list` |
| `--view` | View contact data | `--view` |
| `--validate` | Validate contact data structure | `--validate` |
| `--import` | Import data to sections | `--import` |
| `--section` | Specific section to import to | `--section=enterprise` |
| `--dry-run` | Show what would be imported without making changes | `--dry-run` |

## Usage Examples

### 1. Interactive Mode

Run the command without options to enter interactive mode:

```bash
php artisan contact:manage-data
```

This will show a menu with options:
- List contacts with data
- View contact data
- Validate contact data
- Import data to sections
- Exit

### 2. List All Contacts with Data

```bash
php artisan contact:manage-data --list
```

Output example:
```
=== Contact Data Management Tool ===
📋 Listing contacts with data...

+----+------------------+------------------+--------+-------------+------------------+---------------------+
| ID | Name             | Email            | Team   | Responsible | Data Keys        | Created             |
+----+------------------+------------------+--------+-------------+------------------+---------------------+
| 1  | John Doe         | john@example.com | Team A | Admin User  | company_name, ... | 2024-01-15 10:30:00 |
| 2  | Jane Smith       | jane@example.com | Team B | Manager     | project_name, ... | 2024-01-16 14:20:00 |
+----+------------------+------------------+--------+-------------+------------------+---------------------+

Found 2 contacts with data.
```

### 3. View Contact Data

```bash
php artisan contact:manage-data --view --contact-id=1
```

Output example:
```
📊 Contact Data for: John Doe (ID: 1)

Basic Information:
+------------+------------------+
| Field      | Value            |
+------------+------------------+
| Name       | John Doe         |
| Email      | john@example.com |
| Phone      | +1234567890      |
| Team       | Team A           |
| Responsible| Admin User       |
| Created    | 2024-01-15 10:30:00 |
+------------+------------------+

Data Structure:
📁 company_name: Acme Corp
📁 cuit: 20-12345678-9
📁 address: 123 Main St
📁 city: New York
📁 postal_code: 10001
📁 project_name: Website Redesign
📁 project_description: Complete website overhaul
📁 start_date: 2024-02-01
📁 end_date: 2024-03-31

Available Data Keys:
  1. company_name (string): Acme Corp
  2. cuit (string): 20-12345678-9
  3. address (string): 123 Main St
  4. city (string): New York
  5. postal_code (string): 10001
  6. project_name (string): Website Redesign
  7. project_description (string): Complete website overhaul
  8. start_date (string): 2024-02-01
  9. end_date (string): 2024-03-31
```

### 4. Validate Contact Data

```bash
php artisan contact:manage-data --validate --contact-id=1
```

Output example:
```
🔍 Validating data for contact: John Doe (ID: 1)

Validation Results:
✅ Enterprise: Ready (5 fields)
✅ Project: Ready (4 fields)
❌ Task: Invalid data
    - Need at least 2 fields from: task_title, task_description, priority, due_date
❌ Service: Invalid data
    - Need at least 2 fields from: service_name, service_type, domain, hosting_provider, price

📋 Ready for import:
  - Enterprise
  - Project
```

### 5. Import Data (Dry Run)

```bash
php artisan contact:manage-data --import --contact-id=1 --dry-run
```

Output example:
```
🔍 DRY RUN MODE - No changes will be made
📤 Importing data from contact: John Doe (ID: 1)

Sections to import:
  - Enterprise
  - Project

Importing Enterprise...
  Would create/update enterprise: Acme Corp
✅ Enterprise: Would be created/updated

Importing Project...
  Would create project: Website Redesign
✅ Project: Would be created

Import Summary:
Successfully imported: 2/2
This was a dry run. No actual data was imported.
```

### 6. Import Data to Specific Section

```bash
php artisan contact:manage-data --import --contact-id=1 --section=enterprise
```

### 7. Import All Valid Data

```bash
php artisan contact:manage-data --import --contact-id=1 --section=all
```

## Data Validation Rules

### Enterprise Data
**Required fields:** At least 2 of the following:
- `company_name` or `business_name`
- `cuit` (format: XX-XXXXXXXX-X)
- `address`
- `city`
- `postal_code`
- `email` (valid email format)
- `phone`
- `website`

### Project Data
**Required fields:** At least 2 of the following:
- `project_name`
- `project_description`
- `project_type`
- `start_date` (valid date format)
- `end_date` (valid date format)

### Task Data
**Required fields:** At least 2 of the following:
- `task_title`
- `task_description`
- `priority` (values: low, medium, high, urgent)
- `due_date` (valid date format)

### Service Data
**Required fields:** At least 2 of the following:
- `service_name`
- `service_type`
- `domain` (valid domain format)
- `hosting_provider`
- `price` (numeric value)

## Import Behavior

### Enterprise Import
- Creates or updates an enterprise record
- Links the enterprise to the contact as responsible
- Maps data fields to enterprise attributes
- Validates CUIT format and email

### Project Import
- Creates a new project record
- Links the project to the contact via pivot table
- Sets default status and responsible user
- Maps project data fields

### Task Import
- Creates a new task record
- Sets default status and responsible user
- Maps task data fields
- Validates priority values

### Service Import
- Creates a new service record
- Links to enterprise if contact has one
- Sets default category and currency
- Stores additional data in JSON field

## Error Handling

The command includes comprehensive error handling:

- **Database Connection**: Validates database connectivity
- **Contact Existence**: Checks if specified contact exists
- **Data Validation**: Validates data structure before import
- **Import Errors**: Catches and reports import failures
- **Dry Run**: Prevents accidental data changes

## Best Practices

1. **Always use `--dry-run` first** to preview what will be imported
2. **Validate data** before importing to ensure quality
3. **Check existing records** to avoid duplicates
4. **Review import results** to confirm success
5. **Backup data** before large imports

## Troubleshooting

### Common Issues

1. **"Contact not found"**: Verify the contact ID exists
2. **"No valid data found"**: Check data structure matches validation rules
3. **"Import failed"**: Review error messages and data format
4. **"Database connection failed"**: Check database configuration

### Debug Mode

Run with verbose output for debugging:

```bash
php artisan contact:manage-data --verbose
```

## Integration with Existing Features

The command integrates with existing application features:

- **Team Scoping**: Respects team-based data isolation
- **User Permissions**: Works with existing permission system
- **Data Relationships**: Maintains proper relationships between entities
- **Activity Logging**: Leverages existing activity log system
- **Soft Deletes**: Respects soft delete functionality

## Future Enhancements

Potential improvements for future versions:

- **Batch Processing**: Import multiple contacts at once
- **Data Mapping**: Custom field mapping configuration
- **Import Templates**: Predefined import templates
- **Export Functionality**: Export contact data to various formats
- **Scheduled Imports**: Automated import scheduling
- **Web Interface**: GUI for data management 