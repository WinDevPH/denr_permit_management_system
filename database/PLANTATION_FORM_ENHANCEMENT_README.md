# Plantation Form Enhancement - Implementation Guide

## Changes Made

### 1. Database Schema Updates

- Created `tree_species` table with 20 common Philippine tree species
- Added `verification_document` field to `plantations` table
- SQL file location: `database/tree_species.sql`

### 2. Form Improvements

- Replaced text input for tree species with dropdown select
- Added file upload field for verification documents
- Accepts: JPG, PNG, PDF, DOC, DOCX files (max 5MB)

### 3. Backend Updates

- Enhanced `add_plantation.php` handler to process file uploads
- Added file validation (type and size checks)
- Automatic directory creation for uploads
- Secure file naming with unique identifiers

## Installation Steps

### Step 1: Run the SQL Script

Execute the following SQL file in phpMyAdmin or MySQL client:

```
c:\xampp\htdocs\denr\database\tree_species.sql
```

**Option A - Using phpMyAdmin:**

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Select your `denrdb` database
3. Click on "Import" tab
4. Click "Choose File" and select `tree_species.sql`
5. Click "Go" to execute

**Option B - Using MySQL Command Line:**

```bash
cd c:\xampp\htdocs\denr\database
mysql -u root -p denrdb < tree_species.sql
```

### Step 2: Verify Directory Structure

The following directory has been created automatically:

```
c:\xampp\htdocs\denr\assets\uploads\verification_documents\
```

Ensure this directory has write permissions (chmod 777 on Linux/Mac).

### Step 3: Test the Features

1. Login as a landowner
2. Navigate to Plantations page
3. Click "Add New Plantation"
4. Verify that:
   - Tree Species dropdown shows all 20 species with scientific names
   - File upload field accepts documents (images/PDFs)
   - Form submission uploads the file successfully

## Features

### Tree Species Dropdown

- 20 pre-loaded Philippine tree species
- Displays both common and scientific names
- Easy to select without typing errors
- Sortable alphabetically

### Document Upload

- **Accepted formats:** JPG, PNG, PDF, DOC, DOCX
- **Max file size:** 5MB
- **Purpose:** Land titles, tax declarations, photos for verification
- **Storage:** `assets/uploads/verification_documents/`
- **Security:** Unique filename generation to prevent conflicts

## Database Tables

### tree_species Table

```sql
- species_id (INT, PRIMARY KEY)
- species_name (VARCHAR 100)
- scientific_name (VARCHAR 150)
- common_name (VARCHAR 100)
- created_at (TIMESTAMP)
```

### plantations Table (Updated)

```sql
- Added: verification_document (VARCHAR 255)
```

## File Locations

- **Form:** `modules/landowner/plantations/plantations.php`
- **Handler:** `handlers/add_plantation.php`
- **SQL Script:** `database/tree_species.sql`
- **Upload Directory:** `assets/uploads/verification_documents/`

## Troubleshooting

### Issue: Dropdown is empty

**Solution:** Run the SQL script to populate tree_species table

### Issue: File upload fails

**Solution:**

1. Check directory permissions
2. Verify max upload size in php.ini:
   - `upload_max_filesize = 10M`
   - `post_max_size = 10M`
3. Restart Apache

### Issue: File too large error

**Solution:** Reduce file size or increase limits in php.ini

## Next Steps

You can:

1. Add more tree species via phpMyAdmin
2. Modify accepted file types in `add_plantation.php`
3. Adjust maximum file size as needed
4. Add file preview functionality

## Support

For additional tree species, insert into `tree_species` table:

```sql
INSERT INTO tree_species (species_name, scientific_name, common_name)
VALUES ('Species Name', 'Scientific Name', 'Common Name');
```
