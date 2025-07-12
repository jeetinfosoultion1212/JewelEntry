@echo off
echo Organizing PHP files...

REM Create directories if they don't exist
if not exist "auth" mkdir auth
if not exist "inventory" mkdir inventory
if not exist "sales" mkdir sales
if not exist "customers" mkdir customers
if not exist "loans" mkdir loans
if not exist "schemes" mkdir schemes
if not exist "billing" mkdir billing
if not exist "reports" mkdir reports
if not exist "settings" mkdir settings
if not exist "utilities" mkdir utilities
if not exist "pages" mkdir pages

REM Move authentication files
move login.php auth\ 2>nul
move register.php auth\ 2>nul
move logout.php auth\ 2>nul
move forgot_password.php auth\ 2>nul
move reset_password.php auth\ 2>nul
move verify_otp.php auth\ 2>nul

REM Move customer files
move add_customer.php customers\ 2>nul
move add_gold_plan.php customers\ 2>nul
move create_gold_plan.php customers\ 2>nul
move edit_gold_plan.php customers\ 2>nul
move gold_plan.php customers\ 2>nul
move customers.php customers\ 2>nul
move customer_catalog.php customers\ 2>nul
move customer_details.php customers\ 2>nul

REM Move sales files
move sale.php sales\ 2>nul
move sale-entry.php sales\ 2>nul
move sale-list.php sales\ 2>nul
move order.php sales\ 2>nul
move edit_order_item.php sales\ 2>nul
move process_payment.php sales\ 2>nul
move jewelry_invoice.php sales\ 2>nul
move jewelry_estimate.php sales\ 2>nul

REM Move loans files
move loans.php loans\ 2>nul
move loans_list.php loans\ 2>nul
move new_loan_assignment.php loans\ 2>nul

REM Move schemes files
move schemes.php schemes\ 2>nul
move create_scheme.php schemes\ 2>nul
move delete_scheme.php schemes\ 2>nul
move manage_scheme_entries.php schemes\ 2>nul

REM Move billing files
move billing.php billing\ 2>nul
move new_billing.php billing\ 2>nul
move th_gst_bill.php billing\ 2>nul
move th_qoutation.php billing\ 2>nul
move gst_thermal_invoice.php billing\ 2>nul

REM Move reports files
move reports.php reports\ 2>nul
move reorts_desktop.php reports\ 2>nul
move stock_report.php reports\ 2>nul

REM Move settings files
move settings.php settings\ 2>nul
move profile.php settings\ 2>nul

REM Move utility files
move test_connection.php utilities\ 2>nul
move test_gd.php utilities\ 2>nul
move test_timezone.php utilities\ 2>nul
move test_view_counter.php utilities\ 2>nul
move phpinfo.php utilities\ 2>nul
move create_tables.php utilities\ 2>nul
move add_demo_data.php utilities\ 2>nul

REM Move main pages
move home.php pages\ 2>nul
move main.php pages\ 2>nul
move default.php pages\ 2>nul
move catalog.php pages\ 2>nul
move jewellerspage.php pages\ 2>nul
move karigars.php pages\ 2>nul
move staff.php pages\ 2>nul
move expenses.php pages\ 2>nul
move repairs.php pages\ 2>nul
move bookings.php pages\ 2>nul
move lucky_draw.php pages\ 2>nul
move hallmark_requests.php pages\ 2>nul
move new_hallmark_request.php pages\ 2>nul
move product_list.php pages\ 2>nul
move process_registration.php pages\ 2>nul
move print_tag.php pages\ 2>nul
move generate_pdf_invoice.php pages\ 2>nul
move huid_data.php pages\ 2>nul
move fetch_due_amount.php pages\ 2>nul

echo File organization complete!
pause 