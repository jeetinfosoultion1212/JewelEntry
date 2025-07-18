# Stock Entry API Documentation

## Overview
The `add_stock_entry.php` API handles stock entry operations for the jewelry management system. It supports both opening stock entries and purchase entries with comprehensive inventory management.

## Endpoint
```
POST /api/add_stock_entry.php
```

## Authentication
- Requires valid user session (`$_SESSION['id']`)
- Requires firm ID (`$_SESSION['firmID']`)

## Request Parameters

### Required Fields
- `material_type` (string): Type of material (Gold, Silver, Diamond, etc.)
- `stock_name` (string): Name/description of the stock item
- `purity` (float): Purity percentage (0-100)
- `weight` (float): Weight in grams
- `rate` (float): Rate per gram
- `cost_price_per_gram` (float): Calculated cost per gram
- `total_taxable_amount` (float): Taxable amount before GST
- `final_amount` (float): Final amount including GST

### Optional Fields
- `entry_type` (string): "opening_stock" or "purchase" (default: "opening_stock")
- `unit_measurement` (string): Unit of measurement (gms, carat, pcs, etc.)
- `quantity` (int): Quantity in pieces (default: 1)
- `making_charges` (float): Making charges percentage
- `gst` (string): "true" or "false" for GST inclusion
- `hsn_code` (string): HSN code for the material

### Purchase-specific Fields (when entry_type = "purchase")
- `supplier_id` (int): Supplier ID
- `invoice_number` (string): Invoice number
- `invoice_date` (string): Invoice date (YYYY-MM-DD)
- `paid_amount` (float): Amount paid
- `payment_status` (string): "unpaid", "paid", or "partial"
- `payment_mode` (string): Payment mode (cash, bank, upi, etc.)
- `transaction_ref` (string): Transaction reference

### Opening Stock Fields (when entry_type = "opening_stock")
- `custom_source_info` (string): Source information for opening stock

## Database Operations

### 1. Inventory Management
The API manages the `inventory_metals` table:
- **New Entry**: Creates new inventory record
- **Existing Entry**: Updates existing stock and recalculates cost per gram
- **Stock Tracking**: Maintains current_stock, remaining_stock, and total_cost

### 2. Purchase Records
The API creates records in `metal_purchases` table:
- Tracks purchase transactions
- Links to inventory items
- Records payment details
- Supports both opening stock and purchase entries

### 3. Purchase Items (Purchase entries only)
For purchase entries, creates records in `purchase_items` table:
- Detailed item information
- GST calculations
- HSN code tracking

## Response Format

### Success Response
```json
{
    "success": true,
    "message": "Stock entry saved successfully!",
    "debug": ["debug log entries"],
    "inventory_id": 123,
    "purchase_id": 456
}
```

### Error Response
```json
{
    "success": false,
    "message": "Error description",
    "debug": ["debug log entries"]
}
```

## Error Handling

### Common Errors
1. **Missing Required Fields**: Returns error for any missing required parameters
2. **Database Errors**: Transaction rollback with detailed error message
3. **Authentication Errors**: User not logged in or firm ID missing
4. **Validation Errors**: Invalid data types or values

### Transaction Safety
- Uses database transactions for data integrity
- Automatic rollback on errors
- Detailed logging for debugging

## Usage Examples

### Opening Stock Entry
```javascript
const formData = new FormData();
formData.append('entry_type', 'opening_stock');
formData.append('material_type', 'Gold');
formData.append('stock_name', '22K Gold Stock');
formData.append('purity', '91.6');
formData.append('weight', '100.5');
formData.append('rate', '5000.00');
formData.append('cost_price_per_gram', '5125.00');
formData.append('total_taxable_amount', '515062.50');
formData.append('final_amount', '530514.38');
formData.append('gst', 'true');
formData.append('hsn_code', '7108');
formData.append('custom_source_info', 'Initial Inventory');

fetch('../api/add_stock_entry.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Stock entry saved:', data);
    } else {
        console.error('Error:', data.message);
    }
});
```

### Purchase Entry
```javascript
const formData = new FormData();
formData.append('entry_type', 'purchase');
formData.append('material_type', 'Silver');
formData.append('stock_name', '999 Silver');
formData.append('purity', '99.9');
formData.append('weight', '50.0');
formData.append('rate', '750.00');
formData.append('cost_price_per_gram', '757.50');
formData.append('total_taxable_amount', '37875.00');
formData.append('final_amount', '39011.25');
formData.append('gst', 'true');
formData.append('hsn_code', '7106');
formData.append('supplier_id', '1');
formData.append('invoice_number', 'INV-2024-001');
formData.append('invoice_date', '2024-01-15');
formData.append('paid_amount', '39011.25');
formData.append('payment_status', 'paid');
formData.append('payment_mode', 'bank');
formData.append('transaction_ref', 'TXN-001');

fetch('../api/add_stock_entry.php', {
    method: 'POST',
    body: formData
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Purchase entry saved:', data);
    } else {
        console.error('Error:', data.message);
    }
});
```

## Database Schema

### inventory_metals Table
- `inventory_id` (Primary Key)
- `firm_id` (Foreign Key)
- `material_type` (VARCHAR)
- `stock_name` (VARCHAR)
- `purity` (DECIMAL)
- `current_stock` (DECIMAL)
- `remaining_stock` (DECIMAL)
- `cost_price_per_gram` (DECIMAL)
- `unit_measurement` (VARCHAR)
- `total_cost` (DECIMAL)
- `source_type` (TEXT)
- `minimum_stock_level` (DECIMAL)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

### metal_purchases Table
- `purchase_id` (Primary Key)
- `source_type` (ENUM: 'Supplier', 'Customer', 'Owner')
- `source_id` (INT)
- `purchase_date` (TIMESTAMP)
- `material_type` (VARCHAR)
- `stock_name` (VARCHAR)
- `purity` (DECIMAL)
- `quantity` (DECIMAL)
- `rate_per_gram` (DECIMAL)
- `total_amount` (DECIMAL)
- `transaction_reference` (VARCHAR)
- `payment_status` (ENUM: 'Unpaid', 'Paid', 'Partial')
- `inventory_id` (Foreign Key)
- `firm_id` (Foreign Key)
- `weight` (DECIMAL)
- `paid_amount` (INT)
- `payment_mode` (VARCHAR)
- `invoice_number` (VARCHAR)
- `entry_type` (VARCHAR)

### purchase_items Table
- `purchase_id` (Foreign Key)
- `material_type` (VARCHAR)
- `stock_name` (VARCHAR)
- `purity` (DECIMAL)
- `quantity` (DECIMAL)
- `unit_measurement` (VARCHAR)
- `rate_per_unit` (DECIMAL)
- `total_amount` (DECIMAL)
- `gst_percent` (DECIMAL)
- `gst_amount` (DECIMAL)
- `hsn_code` (VARCHAR)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

## Testing
Use the provided test file `PC/test_stock_entry_api.php` to verify API functionality:
```bash
http://localhost/JewelEntryApp/PC/test_stock_entry_api.php
```

## Security Considerations
- Session-based authentication
- Input validation and sanitization
- SQL injection prevention with prepared statements
- Transaction-based data integrity
- Error logging without exposing sensitive information

## Integration Points
- **Frontend**: `PC/stock-entry.php` - Main stock entry form
- **Database**: Uses existing `inventory_metals`, `metal_purchases`, and `purchase_items` tables
- **Authentication**: Integrates with existing session management
- **Suppliers**: Links to existing supplier management system
