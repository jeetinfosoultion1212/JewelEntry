$(function() {
    // 1. Initialize Date Range Picker
    const start = moment().startOf('month');
    const end = moment().endOf('month');

    function cb(start, end) {
        $('#reportrange span').html(start.format('MMMM D, YYYY') + ' - ' + end.format('MMMM D, YYYY'));
        fetchReportData(start.format('YYYY-MM-DD'), end.format('YYYY-MM-DD'));
    }

    $('#reportrange').daterangepicker({
        startDate: start,
        endDate: end,
        ranges: {
           'Today': [moment(), moment()],
           'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
           'Last 7 Days': [moment().subtract(6, 'days'), moment()],
           'Last 30 Days': [moment().subtract(29, 'days'), moment()],
           'This Month': [moment().startOf('month'), moment().endOf('month')],
           'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    }, cb);

    // Initial data fetch
    cb(start, end);

    // 2. Tab Switching Logic
    $('.tab-button').on('click', function() {
        const tabId = $(this).data('tab');
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').addClass('hidden');
        $('#' + tabId + '-content').removeClass('hidden');
    });

    // 3. Fetch Report Data via AJAX
    function fetchReportData(startDate, endDate) {
        $.ajax({
            url: 'api/get_report_data.php',
            type: 'GET',
            data: {
                start_date: startDate,
                end_date: endDate
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    updateUI(data);
                } else {
                    console.error("Error fetching report data:", response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("AJAX Error:", error);
            }
        });
    }

    // 4. Update UI with fetched data
    function updateUI(data) {
        // Balances
        const { cash, bank, upi } = data.balances;
        $('#cashIn').text(formatSimpleCurrency(cash.in));
        $('#cashOut').text(formatSimpleCurrency(cash.out));
        $('#cashNet').text(formatCurrency(cash.in - cash.out));

        $('#bankIn').text(formatSimpleCurrency(bank.in));
        $('#bankOut').text(formatSimpleCurrency(bank.out));
        $('#bankNet').text(formatCurrency(bank.in - bank.out));

        $('#upiIn').text(formatSimpleCurrency(upi.in));
        $('#upiOut').text(formatSimpleCurrency(upi.out));
        $('#upiNet').text(formatCurrency(upi.in - upi.out));

        // Summary
        $('#totalRevenue').text(formatCurrency(data.summary.total_revenue));
        $('#totalExpenses').text(formatCurrency(data.summary.total_expenses));
        const netFlow = (Number(data.summary.total_revenue) || 0) - (Number(data.summary.total_expenses) || 0);
        const netFlowElement = $('#netFlow');
        netFlowElement.text(formatCurrency(netFlow));
        if (netFlow < 0) {
            netFlowElement.removeClass('text-green-600 text-blue-900').addClass('text-red-600');
        } else {
            netFlowElement.removeClass('text-red-600').addClass('text-blue-900');
        }
        $('#itemsSold').text(data.summary.items_sold_count || 0);
        $('#itemsSoldWeight').text((Number(data.summary.items_sold_weight) || 0).toFixed(2) + ' g');
        $('#itemsAdded').text(data.summary.items_added_count || 0);
        $('#itemsAddedWeight').text((Number(data.summary.items_added_weight) || 0).toFixed(2) + ' g');

        // --- NEW: Total Sales Card ---
        $('#totalSales').text(formatCurrency(data.summary.total_sales));
        $('#totalSalesPaid').text(formatSimpleCurrency(data.summary.total_sales_paid));
        $('#totalSalesDue').text(formatSimpleCurrency(data.summary.total_sales_due));

        // Tables
        populateIncomeTable('#incomeTableBody', data.cash_flow.income);
        populateExpenseTable('#expenseTableBody', data.cash_flow.expenses);
        populateStockInTable('#stockInTableBody', data.inventory.stock_in);
        populateStockOutTable('#stockOutTableBody', data.inventory.stock_out);
    }

    function formatCurrency(value) {
        return '₹' + new Intl.NumberFormat('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(value || 0);
    }
    
    function formatSimpleCurrency(value) {
        const num = Number(value);
        if (num >= 10000000) return '₹' + (num / 10000000).toFixed(2) + 'Cr';
        if (num >= 100000) return '₹' + (num / 100000).toFixed(2) + 'L';
        if (num >= 1000) return '₹' + (num / 1000).toFixed(1) + 'k';
        return '₹' + num.toFixed(0);
    }

    function populateIncomeTable(tbodyId, data) {
        const tbody = $(tbodyId);
        tbody.empty();
        if (!data || data.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center text-gray-500 py-4">No data available for this period.</td></tr>');
            return;
        }
        data.forEach(item => {
            const id = item.id ? `#${item.id}` : '-';
            const date = item.created_at ? moment(item.created_at).format('DD MMM YYYY') : '-';
            const customer = item.customer_name || '-';
            const amount = item.grand_total !== undefined ? formatCurrency(item.grand_total) : '-';
            let status = '-';
            if (item.payment_status) {
                let badgeClass = 'bg-gray-100 text-gray-700';
                if (item.payment_status.toLowerCase() === 'paid') badgeClass = 'bg-green-100 text-green-800';
                else if (item.payment_status.toLowerCase() === 'partial') badgeClass = 'bg-yellow-100 text-yellow-800';
                else if (item.payment_status.toLowerCase() === 'unpaid') badgeClass = 'bg-red-100 text-red-800';
                status = `<span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${badgeClass}">${item.payment_status}</span>`;
            }
            tbody.append(`<tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="px-2 py-3 text-left">${id}</td>
                <td class="px-2 py-3 text-left">${date}</td>
                <td class="px-2 py-3 text-left">${customer}</td>
                <td class="px-2 py-3 text-right">${amount}</td>
                <td class="px-2 py-3 text-center">${status}</td>
            </tr>`);
        });
    }

    function populateExpenseTable(tbodyId, data) {
        const tbody = $(tbodyId);
        tbody.empty();
        if (!data || data.length === 0) {
            tbody.append('<tr><td colspan="4" class="text-center text-gray-500 py-4">No data available for this period.</td></tr>');
            return;
        }
        data.forEach(item => {
            const date = item.date ? moment(item.date).format('DD MMM YYYY') : '-';
            const category = item.category || '-';
            const description = item.description || '-';
            const amount = item.amount !== undefined ? formatCurrency(item.amount) : '-';
            let paidBadge = '';
            if (item.paid_amount !== undefined && item.paid_amount !== null) {
                paidBadge = `<div style="margin-top:2px;"><span style="background:#ef4444;color:#fff;font-size:10px;padding:2px 6px;border-radius:4px;display:inline-block;">Paid: ${formatCurrency(item.paid_amount)}</span></div>`;
            }
            tbody.append(`<tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="px-2 py-3 text-left">${date}</td>
                <td class="px-2 py-3 text-left">${category}</td>
                <td class="px-2 py-3 text-left">${description}</td>
                <td class="px-2 py-3 text-right">${amount}${paidBadge}</td>
            </tr>`);
        });
    }

    function populateStockInTable(tbodyId, data) {
        const tbody = $(tbodyId);
        tbody.empty();
        if (!data || data.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center text-gray-500 py-4">No data available for this period.</td></tr>');
            return;
        }
        data.forEach(item => {
            const productId = item.product_id || '-';
            const itemName = item.product_name || item.item_name || '-';
            const dateAdded = item.created_at ? moment(item.created_at).format('DD MMM YYYY') : '-';
            const grossWeight = item.gross_weight !== undefined ? (Number(item.gross_weight) || 0).toFixed(2) + ' g' : '-';
            const supplier = item.supplier_name || '-';
            tbody.append(`<tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="px-2 py-3 text-left">${productId}</td>
                <td class="px-2 py-3 text-left">${itemName}</td>
                <td class="px-2 py-3 text-left">${dateAdded}</td>
                <td class="px-2 py-3 text-right">${grossWeight}</td>
                <td class="px-2 py-3 text-left">${supplier}</td>
            </tr>`);
        });
    }

    function populateStockOutTable(tbodyId, data) {
        const tbody = $(tbodyId);
        tbody.empty();
        if (!data || data.length === 0) {
            tbody.append('<tr><td colspan="5" class="text-center text-gray-500 py-4">No data available for this period.</td></tr>');
            return;
        }
        data.forEach(item => {
            const productId = item.product_id || '-';
            const itemName = item.product_name || item.item_name || '-';
            const dateSold = item.created_at ? moment(item.created_at).format('DD MMM YYYY') : '-';
            const grossWeight = item.gross_weight !== undefined ? (Number(item.gross_weight) || 0).toFixed(2) + ' g' : '-';
            const invoiceId = item.sale_id || item.invoice_id || '-';
            tbody.append(`<tr class="border-b border-gray-100 hover:bg-gray-50">
                <td class="px-2 py-3 text-left">${productId}</td>
                <td class="px-2 py-3 text-left">${itemName}</td>
                <td class="px-2 py-3 text-left">${dateSold}</td>
                <td class="px-2 py-3 text-right">${grossWeight}</td>
                <td class="px-2 py-3 text-left">${invoiceId}</td>
            </tr>`);
        });
    }
}); 