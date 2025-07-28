# Team Demo Factories

This document describes the factories created for generating demo data for Team 1 (Demo) in the BBO application.

## Overview

The following factories have been created to generate realistic demo data for Team 1:

- **ClientFactory** - Creates demo clients (enterprises)
- **ProjectFactory** - Creates demo projects
- **FareFactory** - Creates demo fares (tarifas) with units
- **SoftwareFactory** - Creates demo software entries
- **CertificationFactory** - Creates demo certifications
- **ContactPortfolioFactory** - Creates demo experience/portfolio items

## Factories

### ClientFactory

Creates demo clients (enterprises) for Team 1.

**Usage:**
```php
// Create a single client
Enterprise::factory()->create();

// Create multiple clients
Enterprise::factory()->count(10)->create();

// Create a client specifically
Enterprise::factory()->client()->create();

// Create a supplier
Enterprise::factory()->supplier()->create();
```

**Features:**
- Generates realistic company names
- Includes contact information (email, phone, website)
- Adds company data (industry, size, revenue)
- Assigns to Team 1 automatically

### ProjectFactory

Creates demo projects for Team 1.

**Usage:**
```php
// Create a single project
Project::factory()->create();

// Create multiple projects
Project::factory()->count(15)->create();

// Create active projects
Project::factory()->active()->create();

// Create completed projects
Project::factory()->completed()->create();

// Create pending projects
Project::factory()->pending()->create();

// Create projects with associated fares
Project::factory()->withFares()->create();
```

**Features:**
- Generates realistic project names and descriptions
- Includes pricing, dates, and status
- Links to existing clients and categories
- Can associate fares with projects using `withFares()`
- Assigns to Team 1 automatically

### FareFactory

Creates demo fares (tarifas) with associated units.

**Usage:**
```php
// Create a single fare with random units
Fare::factory()->withUnits()->create();

// Create translation fares with word units
Fare::factory()->translation()->withWordUnits()->create();

// Create audiovisual fares with time units
Fare::factory()->audiovisual()->withTimeUnits()->create();

// Create specialized fares
Fare::factory()->specialized()->withAudiovisualUnits()->create();
```

**Features:**
- Generates realistic fare names
- Automatically attaches units based on fare type
- Supports different unit types (word-based, time-based, audiovisual)
- Assigns to Team 1 automatically

### SoftwareFactory

Creates demo software entries with categories.

**Usage:**
```php
// Create a single software entry
Software::factory()->create();

// Create CAT tools
Software::factory()->catTool()->create();

// Create subtitling software
Software::factory()->subtitling()->create();

// Create audio editing software
Software::factory()->audioEditing()->create();

// Create video editing software
Software::factory()->videoEditing()->create();

// Create development software
Software::factory()->development()->create();
```

**Features:**
- Generates realistic software names
- Automatically assigns to appropriate categories
- Includes popular CAT tools, subtitling, audio/video editing software
- Assigns to Team 1 automatically

### CertificationFactory

Creates demo certifications.

**Usage:**
```php
// Create a single certification
Certification::factory()->create();

// Create translation certifications
Certification::factory()->translation()->create();

// Create language proficiency certifications
Certification::factory()->languageProficiency()->create();

// Create audiovisual certifications
Certification::factory()->audiovisual()->create();

// Create certifications for specific languages
Certification::factory()->spanish()->create();
Certification::factory()->english()->create();
Certification::factory()->french()->create();
Certification::factory()->german()->create();
```

**Features:**
- Generates realistic certification names
- Includes various types (translation, language proficiency, audiovisual)
- Supports multiple languages
- Assigns to Team 1 automatically

### ContactPortfolioFactory

Creates demo experience/portfolio items for collaborators.

**Usage:**
```php
// Create a single portfolio item
ContactPortfolio::factory()->create();

// Create translation portfolio items
ContactPortfolio::factory()->translation()->create();

// Create subtitling portfolio items
ContactPortfolio::factory()->subtitling()->create();

// Create voice-over portfolio items
ContactPortfolio::factory()->voiceOver()->create();

// Create localization portfolio items
ContactPortfolio::factory()->localization()->create();

// Create recent portfolio items
ContactPortfolio::factory()->recent()->create();
```

**Features:**
- Generates realistic project titles and descriptions
- Includes position, languages, and technologies used
- Supports different project types
- Links to existing collaborators
- Assigns to Team 1 automatically

## Seeders

### TeamDemoSeeder

Main seeder that creates comprehensive demo data for Team 1.

**Usage:**
```bash
php artisan db:seed --class=TeamDemoSeeder
```

**Creates:**
- 15 demo clients
- 25 demo projects (with associated fares)
- 12 demo fares with units
- 30 demo software entries
- 20 demo certifications
- 40 demo experience/portfolio items

### TeamDemoDataSeeder

Standalone seeder that can be run independently.

**Usage:**
```bash
php artisan db:seed --class=TeamDemoDataSeeder
```

**Creates:**
- 10 demo clients
- 15 demo projects (with associated fares)
- 8 demo fares with units
- 20 demo software entries
- 15 demo certifications
- 25 demo experience/portfolio items

## Running the Seeders

### Option 1: Run the main DatabaseSeeder
```bash
php artisan db:seed
```
This will run all seeders including the Team1DemoSeeder.

### Option 2: Run only Team 1 demo data
```bash
php artisan db:seed --class=TeamDemoDataSeeder
```

### Option 3: Verify the data was created
```bash
php artisan tinker --execute="echo 'Clients: ' . App\Models\Enterprise::where('team_id', 1)->count() . PHP_EOL; echo 'Projects: ' . App\Models\Project::where('team_id', 1)->count() . PHP_EOL; echo 'Fares: ' . App\Models\Fare::where('team_id', 1)->count() . PHP_EOL; echo 'Software: ' . App\Models\Software::where('team_id', 1)->count() . PHP_EOL; echo 'Certifications: ' . App\Models\Certification::where('team_id', 1)->count() . PHP_EOL; echo 'Portfolio items: ' . App\Models\ContactPortfolio::count() . PHP_EOL;"
```

Expected output:
```
Clients: 40
Projects: 40
Fares: 49
Software: 50
Certifications: 35
Portfolio items: 65
Fares with units: 20
Projects with fares: 15
Total project-fare relationships: 29
Total fare-unit relationships: 61
```

### Option 4: Run specific factory methods
```php
// In a seeder or tinker
$seeder = new \Database\Seeders\TeamDemoSeeder();

// Create specific types of data
$seeder->createTranslationFares();
$seeder->createCatTools();
$seeder->createTranslationCertifications();
$seeder->createActiveProjects();
```

## Data Structure

All factories create data that:
- Is assigned to Team 1 (Demo)
- Uses realistic names and descriptions
- Follows the application's data structure
- Includes proper relationships between models
- Uses appropriate statuses and categories

## Notes

- All factories respect the global scope for team_id
- Data is created with realistic values suitable for demonstration
- Factories can be customized by modifying the factory files
- The seeders include error checking and informative output
- Data can be easily extended by adding more factory states or methods 
