# Cash Register System Documentation

## Overview
The Cash Register system manages daily cash drawer sessions for the Fitness Center POS. Each register session tracks opening/closing balances, sales transactions, and cash flow for accountability and reporting.

## Features

### 1. Daily Register Sessions
- **Auto-Open**: Registers can be configured to auto-open each morning
- **Auto-Close**: Registers automatically close at midnight or configured time
- **Manual Control**: Staff can manually open/close registers as needed
- **One Active Register**: Only one register can be open per shop per day

### 2. Transaction Tracking
- **Sales Association**: All sales are linked to the active register
- **Payment Methods**: Tracks cash, card, and credit sales separately
- **Real-time Totals**: Updates register totals as sales are made
- **Transaction Count**: Counts all transactions in the session

### 3. Cash Management
- **Opening Balance**: Record starting cash in drawer
- **Expected Balance**: Auto-calculated based on cash sales
- **Closing Balance**: Manual count at end of day
- **Variance Tracking**: Automatic calculation of over/short amounts

## Database Schema

### cash_registers Table
```sql
- id: Primary key
- uuid: Unique identifier
- shop_id: Foreign key to shops
- user_id: Cashier who opened (foreign key to users)
- register_number: Unique register identifier (REG-XXXXXX)
- register_date: Date of this session
- opening_balance: Starting cash amount
- closing_balance: Ending cash count
- expected_balance: Calculated expected amount
- variance: Difference (closing - expected)
- total_sales: Sum of all sales
- total_cash_sales: Cash payment sales
- total_card_sales: Card payment sales
- total_credit_sales: Credit sales (on account)
- total_mobile_money_sales: Mobile money payment sales (M-Pesa, etc.)
- total_bank_transfer_sales: Bank transfer payment sales
- total_cheque_sales: Cheque payment sales
- transaction_count: Number of sales
- status: 'open' or 'closed'
- opened_at: Timestamp when opened
- closed_at: Timestamp when closed
- closed_by: User who closed (foreign key)
- opening_notes: Optional notes when opening
- closing_notes: Optional notes when closing
```

### sales Table (Updated)
```sql
- register_id: Foreign key to cash_registers
- payment_method: 'cash', 'card', 'credit', 'mobile_money', 'bank_transfer', 'cheque'
```

## Models

### CashRegister Model
Location: `app/Models/CashRegister.php`

**Key Methods:**
```php
// Static Methods
CashRegister::getActiveRegister($shopId): Returns today's open register for shop
CashRegister::openRegister($shopId, $balance, $notes): Opens new register

// Instance Methods
$register->isOpen(): Check if register is open
$register->isClosed(): Check if register is closed
$register->closeRegister($balance, $notes): Close register and calculate variance
$register->updateSalesTotals(): Refresh sales totals from database
$register->calculateExpectedBalance(): Returns expected cash based on sales
$register->calculateVariance(): Returns difference between expected and actual
```

**Relationships:**
```php
$register->shop: Shop this register belongs to
$register->user: User who opened the register
$register->closedBy: User who closed the register
$register->sales: All sales in this register session
```

**Scopes:**
```php
CashRegister::open(): Only open registers
CashRegister::closed(): Only closed registers
CashRegister::forDate($date): Registers for specific date
CashRegister::forShop($shopId): Registers for specific shop
```

## Controllers

### CashRegisterController
Location: `app/Http/Controllers/CashRegisterController.php`

**Routes:**
```php
GET  /cash-registers              # List all registers (with filters)
GET  /cash-registers/open         # Show form to open new register
POST /cash-registers              # Store new register opening
GET  /cash-registers/{id}         # Show register details
GET  /cash-registers/{id}/close   # Show form to close register
POST /cash-registers/{id}/close   # Process register closing
```

### SaleController (Updated)
Location: `app/Http/Controllers/SaleController.php`

**Changes:**
- `create()`: Checks for active register, redirects if none open
- `store()`: Associates sale with active register
- Updates register totals after each sale

## User Workflows

### Opening a Register

1. **Access**: Navigate to Cash Registers → Open Register
2. **Verify**: System checks no register is already open for today
3. **Enter Details**:
   - Opening Balance: Count cash in drawer
   - Opening Notes: Optional (e.g., "Starting with 2x $100 bills")
4. **Submit**: Register opens with status 'open'
5. **Confirmation**: User can now create sales

### Creating Sales (with Register)

1. **Check**: System verifies active register exists
2. **If No Register**: Redirects to open register page
3. **If Register Open**: Allows sale creation
4. **On Sale Complete**: 
   - Sale associated with register_id
   - Register totals updated automatically
   - Transaction count incremented

### Closing a Register

1. **Access**: Navigate to Cash Registers → View Active Register → Close
2. **Review Totals**:
   - Total Sales: $X,XXX.XX
   - Cash Sales: $X,XXX.XX
   - Card Sales: $X,XXX.XX
   - Credit Sales: $X,XXX.XX
   - Transaction Count: XX
3. **Count Cash**:
   - Physical cash count in drawer
   - Enter as Closing Balance
4. **Variance Calculation**:
   ```
   Opening Balance:    $200.00
   + Cash Sales:       $1,450.00
   Expected Balance:   $1,650.00
   - Closing Balance:  $1,645.00
   = Variance:         -$5.00 (short)
   ```
5. **Add Notes**: Explain any variance
6. **Submit**: Register status changes to 'closed'

## Automatic Operations

### Auto-Open (Scheduled Task)
Configure in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Open registers every morning at 8 AM
    $schedule->call(function () {
        Shop::where('status', 'active')->each(function ($shop) {
            $activeRegister = CashRegister::getActiveRegister($shop->id);
            
            if (!$activeRegister) {
                CashRegister::openRegister(
                    shopId: $shop->id,
                    openingBalance: $shop->default_opening_balance ?? 200,
                    notes: 'Auto-opened by system'
                );
            }
        });
    })->dailyAt('08:00');
}
```

### Auto-Close (Scheduled Task)
Configure in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Close registers every night at midnight
    $schedule->call(function () {
        CashRegister::where('status', 'open')
            ->where('register_date', '<', today())
            ->each(function ($register) {
                $register->closeRegister(
                    closingBalance: null, // Will use expected balance
                    notes: 'Auto-closed by system'
                );
            });
    })->daily();
}
```

## Reports & Analytics

### Daily Register Report
Shows for specific register:
- Opening/closing balances
- Total sales breakdown by payment method
- Transaction count
- Variance analysis
- List of all transactions
- Cashier information

### Register History
Lists all past registers with:
- Date
- Cashier
- Opening/closing times
- Total sales
- Variance
- Status

## Security & Permissions

### Recommended Permissions:
```php
'cash-register.view'   // View register list and details
'cash-register.open'   // Open new registers
'cash-register.close'  // Close registers
'cash-register.manage' // Full access including editing past registers
```

### Best Practices:
1. **Opening**: Only managers/supervisors should open registers
2. **Closing**: Only cashier who opened or supervisor should close
3. **Variance Review**: Variances over $10 should require supervisor approval
4. **Audit Trail**: All register actions are logged with user IDs and timestamps

## Integration Points

### With Sales Module
- Sales cannot be created without active register
- Each sale references its register session
- Register totals update automatically

### With Reporting Module
- Daily sales reports filtered by register
- Cashier performance by register session
- Variance trend analysis

### With Audit Logs
- All open/close actions logged
- Variance explanations stored
- User accountability maintained

## API Endpoints (Future)

```php
GET    /api/cash-registers              # List registers
POST   /api/cash-registers/open         # Open register
GET    /api/cash-registers/active       # Get active register
POST   /api/cash-registers/{id}/close   # Close register
GET    /api/cash-registers/{id}/sales   # Get register sales
```

## Troubleshooting

### Issue: "Please open cash register" error
**Solution**: Navigate to Cash Registers → Open Register and create today's session

### Issue: Register won't close
**Possible Causes:**
- Register already closed
- Validation errors in closing balance
- Missing required fields
**Solution**: Check error message, verify closing balance is numeric and positive

### Issue: Variance is large
**Steps:**
1. Recount physical cash
2. Review all cash transactions
3. Check for voided sales
4. Verify opening balance was correct
5. Document in closing notes

### Issue: Can't create sales
**Solution**: Ensure register is open for current date. One register per shop per day.

## Future Enhancements

1. **Multiple Registers**: Support multiple concurrent registers per shop
2. **Cash Drops**: Record mid-shift cash removals
3. **Petty Cash**: Track small cash expenses
4. **Float Management**: Configure different opening balances
5. **Blind Close**: Hide expected balance until closing count entered
6. **Receipt Printing**: Print register open/close receipts
7. **Mobile App**: Close registers from mobile devices
8. **Integration**: Sync with accounting systems

## Configuration

### Environment Variables
```env
# Default opening balance for all shops
DEFAULT_OPENING_BALANCE=200.00

# Auto-open time (24-hour format)
REGISTER_AUTO_OPEN_TIME=08:00

# Auto-close time (24-hour format)  
REGISTER_AUTO_CLOSE_TIME=00:00

# Variance alert threshold
REGISTER_VARIANCE_ALERT=10.00
```

### Shop Settings (Future)
Each shop can have custom:
- Default opening balance
- Auto-open/close times
- Variance thresholds
- Required fields

## Testing

### Manual Testing Checklist
- [ ] Open register with opening balance
- [ ] Create sale - verify register totals update
- [ ] Create multiple sales with different payment methods
- [ ] Close register - verify variance calculation
- [ ] Try opening second register (should fail)
- [ ] View register details
- [ ] Filter register list by date/status
- [ ] Test auto-close functionality

### Test Data
```php
// Factory for testing
CashRegister::factory()->create([
    'opening_balance' => 200,
    'status' => 'open',
    'register_date' => today(),
]);
```

## Changelog

### Version 1.0.0 (2026-02-04)
- Initial implementation
- Basic open/close functionality
- Payment method tracking
- Variance calculation
- Integration with sales module
- Auto-close old registers
