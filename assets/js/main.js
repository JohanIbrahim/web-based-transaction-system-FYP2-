/**
 * Smart Web-Based Transaction System - Main JavaScript
 * Phase 2: Customer Transaction Flow + Mock Payment Simulation
 */

document.addEventListener('DOMContentLoaded', function () {

    // ============================================================
    // 1. Mobile Navigation Toggle
    // ============================================================
    const navbarToggle = document.getElementById('navbarToggle');
    const navbarNav = document.getElementById('navbarNav');

    if (navbarToggle && navbarNav) {
        navbarToggle.addEventListener('click', function () {
            navbarNav.classList.toggle('active');
        });

        document.addEventListener('click', function (e) {
            if (!navbarToggle.contains(e.target) && !navbarNav.contains(e.target)) {
                navbarNav.classList.remove('active');
            }
        });
    }

    // ============================================================
    // 2. Cart Quantity Controls (cart.php)
    // ============================================================
    document.querySelectorAll('.qty-control').forEach(function(control) {
        var minusBtn = control.querySelector('.qty-minus');
        var plusBtn = control.querySelector('.qty-plus');
        var valueSpan = control.querySelector('.qty-value');
        var hiddenInput = control.querySelector('.qty-input');

        if (minusBtn && plusBtn && valueSpan && hiddenInput) {
            minusBtn.addEventListener('click', function() {
                var val = parseInt(valueSpan.textContent) || 1;
                if (val > 1) {
                    val--;
                    valueSpan.textContent = val;
                    hiddenInput.value = val;
                }
            });

            plusBtn.addEventListener('click', function() {
                var val = parseInt(valueSpan.textContent) || 1;
                if (val < 99) {
                    val++;
                    valueSpan.textContent = val;
                    hiddenInput.value = val;
                }
            });
        }
    });

    // ============================================================
    // 3. Print Receipt (receipt.php)
    // ============================================================
    var printBtn = document.getElementById('printReceipt');
    if (printBtn) {
        printBtn.addEventListener('click', function() {
            window.print();
        });
    }

    // ============================================================
    // 4. Form Validation (checkout form)
    // ============================================================
    var checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            var nameInput = document.getElementById('customer_name');
            if (nameInput && nameInput.value.trim() === '') {
                e.preventDefault();
                nameInput.focus();
                nameInput.style.borderColor = 'var(--danger)';
                alert('Please enter your name.');
                return false;
            }
        });
    }

    // ============================================================
    // 5. Mock Payment Simulation (payment.php)
    // ============================================================
    var paymentForm = document.getElementById('paymentForm');
    var payButton = document.getElementById('payButton');
    var overlay = document.getElementById('paymentOverlay');
    var loading = document.getElementById('paymentLoading');
    var cashSuccess = document.getElementById('paymentCashSuccess');
    var mockOnline = document.getElementById('mockOnline');
    var mockEwallet = document.getElementById('mockEwallet');
    var mockOnlinePayBtn = document.getElementById('mockOnlinePayBtn');
    var mockEwalletPayBtn = document.getElementById('mockEwalletPayBtn');

    // Track whether payment has been submitted (to prevent double-submit)
    var paymentSubmitted = false;

    if (paymentForm && payButton) {
        // Step 1: Customer clicks "Place Order" or "Confirm Payment"
        paymentForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Get selected payment method
            var selectedMethod = document.querySelector('.payment-radio:checked');
            if (!selectedMethod) {
                alert('Please select a payment method.');
                return;
            }

            var method = selectedMethod.value;

            // Disable button to prevent double-click
            payButton.disabled = true;
            payButton.textContent = 'Processing...';

            // Show overlay
            overlay.style.display = 'flex';

            // Generate a mock reference number for display
            var mockRef = '';
            if (method === 'cash') {
                mockRef = 'TXN-' + String(Date.now()).slice(-5) + '-' + new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
            } else if (method === 'online') {
                mockRef = 'ONLINE-' + String(Date.now()).slice(-5) + '-' + new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
            } else if (method === 'ewallet') {
                mockRef = 'EWALLET-' + String(Date.now()).slice(-5) + '-' + new Date().toISOString().replace(/[-:T.Z]/g, '').slice(0, 14);
            }

            if (method === 'cash') {
                // Cash: order placed — no payment processing
                // Just show the success message with "View Order Details" button
                loading.style.display = 'none';
                mockOnline.style.display = 'none';
                mockEwallet.style.display = 'none';
                cashSuccess.style.display = 'block';

                // Submit the form to save the order (unpaid)
                submitPayment();

            } else if (method === 'online') {
                // Online Banking: show mock portal with bank selection
                loading.style.display = 'none';
                cashSuccess.style.display = 'none';
                mockOnline.style.display = 'block';
                mockEwallet.style.display = 'none';

                // Set reference display
                var refDisplay = document.getElementById('mockRefDisplay');
                if (refDisplay) refDisplay.textContent = mockRef;

                // Reset portal state
                var status = document.getElementById('mockOnlineStatus');
                if (status) status.style.display = 'none';
                var payBtn = document.getElementById('mockOnlinePayBtn');
                if (payBtn) {
                    payBtn.style.display = 'block';
                    payBtn.disabled = false;
                    payBtn.textContent = 'Pay Now';
                }
                var bankSelect = document.getElementById('mockBankSelect');
                if (bankSelect) bankSelect.value = '';

            } else if (method === 'ewallet') {
                // E-Wallet: show mock portal with e-wallet selection
                loading.style.display = 'none';
                cashSuccess.style.display = 'none';
                mockOnline.style.display = 'none';
                mockEwallet.style.display = 'block';

                // Set reference display
                var refDisplay2 = document.getElementById('mockRefDisplay2');
                if (refDisplay2) refDisplay2.textContent = mockRef;

                // Reset portal state
                var status = document.getElementById('mockEwalletStatus');
                if (status) status.style.display = 'none';
                var payBtn = document.getElementById('mockEwalletPayBtn');
                if (payBtn) {
                    payBtn.style.display = 'block';
                    payBtn.disabled = false;
                    payBtn.textContent = 'Pay Now';
                }
                // Deselect all e-wallet options
                document.querySelectorAll('.ewallet-option').forEach(function(o) {
                    o.classList.remove('selected');
                });
            }
        });
    }

    // Step 2: Customer clicks "Pay Now" in the Online Banking portal
    if (mockOnlinePayBtn) {
        mockOnlinePayBtn.addEventListener('click', function() {
            var bankSelect = document.getElementById('mockBankSelect');
            if (!bankSelect || bankSelect.value === '' || bankSelect.value === '-- Select a bank --') {
                alert('Please select your bank first.');
                bankSelect.focus();
                return;
            }

            // Hide the Pay Now button, show processing status
            mockOnlinePayBtn.style.display = 'none';
            var status = document.getElementById('mockOnlineStatus');
            if (status) {
                status.style.display = 'flex';
                status.innerHTML = '<div class="payment-spinner" style="width: 24px; height: 24px; border-width: 3px;"></div> <span>Redirecting to ' + bankSelect.value + '...</span>';
            }

            // After 2.5s, show authorized
            setTimeout(function() {
                if (status) {
                    status.innerHTML = '<span style="color: #16a34a; font-size: 1.2rem;">&#10003;</span> <span>Payment authorized!</span>';
                }
            }, 2500);

            // Submit after 5 seconds total
            setTimeout(function() {
                submitPayment();
            }, 5000);
        });
    }

    // Step 2: Customer clicks "Pay Now" in the E-Wallet portal
    if (mockEwalletPayBtn) {
        mockEwalletPayBtn.addEventListener('click', function() {
            var selectedWallet = document.querySelector('.ewallet-option.selected');
            if (!selectedWallet) {
                alert('Please select your e-wallet first.');
                return;
            }

            var walletName = selectedWallet.getAttribute('data-wallet');

            // Hide the Pay Now button, show processing status
            mockEwalletPayBtn.style.display = 'none';
            var status = document.getElementById('mockEwalletStatus');
            if (status) {
                status.style.display = 'flex';
                status.innerHTML = '<div class="payment-spinner" style="width: 24px; height: 24px; border-width: 3px;"></div> <span>Processing ' + walletName + ' payment...</span>';
            }

            // After 2.5s, show successful
            setTimeout(function() {
                if (status) {
                    status.innerHTML = '<span style="color: #16a34a; font-size: 1.2rem;">&#10003;</span> <span>Payment successful!</span>';
                }
            }, 2500);

            // Submit after 5 seconds total
            setTimeout(function() {
                submitPayment();
            }, 5000);
        });
    }

    /**
     * Submit payment form via AJAX
     */
    function submitPayment() {
        // Prevent double submission
        if (paymentSubmitted) return;
        paymentSubmitted = true;

        var formData = new FormData(paymentForm);
        formData.append('process_payment', '1');

        // Add AJAX header
        var xhr = new XMLHttpRequest();
        xhr.open('POST', paymentForm.action || window.location.href, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success && response.redirect) {
                        window.location.href = response.redirect;
                    } else {
                        // Fallback: regular form submit
                        paymentForm.submit();
                    }
                } catch (e) {
                    // If response isn't JSON, submit normally
                    paymentForm.submit();
                }
            } else {
                // Error: show message and re-enable button
                overlay.style.display = 'none';
                payButton.disabled = false;
                payButton.textContent = 'Confirm Payment';
                paymentSubmitted = false;
                alert('Payment failed. Please try again.');
            }
        };

        xhr.onerror = function() {
            overlay.style.display = 'none';
            payButton.disabled = false;
            payButton.textContent = 'Confirm Payment';
            paymentSubmitted = false;
            alert('Network error. Please try again.');
        };

        xhr.send(formData);
    }

    // ============================================================
    // 6. Flash Message Auto-Dismiss
    // ============================================================
    var flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(function(msg) {
        setTimeout(function() {
            msg.style.transition = 'opacity 0.5s ease';
            msg.style.opacity = '0';
            setTimeout(function() {
                msg.style.display = 'none';
            }, 500);
        }, 4000);
    });

    // ============================================================
    // 7. E-Wallet Option Selection (payment.php)
    // ============================================================
    document.querySelectorAll('.ewallet-option').forEach(function(option) {
        option.addEventListener('click', function() {
            document.querySelectorAll('.ewallet-option').forEach(function(o) {
                o.classList.remove('selected');
            });
            this.classList.add('selected');
        });
    });

});
