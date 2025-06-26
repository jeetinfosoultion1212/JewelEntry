# Timezone Fix for Indian Time (Asia/Kolkata)

## Problem
The jewellery sales data was not storing the correct Indian timezone (IST - UTC+5:30). The database was using the server's default timezone instead of Indian time.

## Solution Implemented

### 1. Created Missing Config File
- Created `config/config.php` with proper database connection settings
- Added MySQL timezone setting: `SET time_zone = '+05:30'`
- Added PHP timezone setting: `date_default_timezone_set('Asia/Kolkata')`

### 2. Updated Database Connection Files
- Updated `config/db_connect.php` with timezone settings
- Updated `config/db.php` (PDO connection) with timezone settings

### 3. Fixed Sale Date Field
- Changed `sale_date` field from `NOW()` to `CURDATE()` in jewellery_sales INSERT
- This ensures the date field gets the correct date in Indian timezone

### 4. Centralized Timezone Management
- All database connections now use Indian timezone
- All `NOW()` calls in MySQL queries will use Indian time
- All PHP date functions will use Indian time

## Files Modified
1. `config/config.php` - Created with timezone settings
2. `config/db_connect.php` - Added timezone settings
3. `config/db.php` - Added timezone settings for PDO
4. `sale-entry.php` - Fixed sale_date field and removed duplicate connection
5. `test_timezone.php` - Created test script to verify timezone settings

## Verification
Run `test_timezone.php` to verify that:
- PHP timezone is set to Asia/Kolkata
- MySQL timezone is set to +05:30
- NOW() functions return Indian time
- Date fields store correct Indian dates

## Impact
- All new sales will be recorded with correct Indian time
- All timestamps will be in Indian timezone
- Reports and queries will show correct Indian time
- No impact on existing data (only affects new records) 