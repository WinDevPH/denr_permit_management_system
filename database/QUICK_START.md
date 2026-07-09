# 🚀 QUICK START GUIDE

## Run This First!

### Step 1: Update Database

Navigate to:

```
http://localhost/denr/database/update_database.php
```

Click "Run Database Update" button.

### Step 2: Test the Form

Navigate to:

```
http://localhost/denr/
```

Login as landowner → Go to Plantations → Click "Add New Plantation"

## What Changed?

### ✅ Tree Species Field

- Changed from TEXT INPUT → DROPDOWN SELECT
- Pre-loaded with 20 Philippine tree species
- Shows scientific names in parentheses

### ✅ Document Upload Field

- NEW field for uploading verification documents
- Accepts: JPG, PNG, PDF, DOC, DOCX
- Max size: 5MB

### ✅ Admin View

- New "Document" column shows upload status
- "View Document" button in review modal
- Can download uploaded documents

## Files Changed

```
✓ modules/landowner/plantations/plantations.php    (Form updated)
✓ handlers/add_plantation.php                      (Upload handling)
✓ modules/admin/plantations/plantations.php        (Admin view)
✓ database/tree_species.sql                        (Schema + data)
✓ database/update_database.php                     (Web updater)
✓ database/run_tree_species_update.php             (Update script)
```

## Common Issues

**Error: "Cannot set properties of null"**
→ Fixed! Now properly handles dropdown on edit.

**Dropdown is empty**
→ Run the database update script.

**Can't upload files**
→ Check folder permissions for:
`assets/uploads/verification_documents/`

## Database Tables

### NEW: tree_species

- 20 Philippine tree species
- Scientific names included
- Easy to add more species

### UPDATED: plantations

- Added: `verification_document` column
- Stores file path to uploaded docs

## Quick Test

1. ✅ Login as landowner
2. ✅ Add new plantation
3. ✅ Select species from dropdown (e.g., "Narra")
4. ✅ Upload a PDF/image document
5. ✅ Submit form
6. ✅ Login as admin
7. ✅ Review plantation
8. ✅ Click "View Document"

All working? You're done! 🎉

## Need Help?

Check the detailed documentation:

- `IMPLEMENTATION_COMPLETE.md` - Full details
- `PLANTATION_FORM_ENHANCEMENT_README.md` - Installation guide

---

**Status:** ✅ READY FOR USE
**Date:** January 8, 2026
**Version:** 1.0
