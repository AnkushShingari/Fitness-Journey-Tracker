/**
 * Fitness Journey Tracker - Frontend JavaScript
 * Handles session management, form submissions, validation, and chart rendering
 */

(function($) {
    'use strict';

    // Global state
    let currentUser = null;
    let currentEntries = [];
    let progressChart = null;

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Try to resume existing session
        resumeSession();

        // Bind event handlers
        bindEventHandlers();

        // Initialize energy slider
        initEnergySlider();

        // Initialize chart directly from server-rendered data (most reliable)
        if (window.fjtEntries && window.fjtEntries.length > 0 && $('#progressChart').length) {
            currentEntries = window.fjtEntries;
            initProgressChart(window.fjtEntries);
        }

        // Initialize tab system
        initTabs();
    });

    /**
     * Resume session from cookie
     */
    function resumeSession() {
        $.ajax({
            url: fjtData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_resume_session',
                nonce: fjtData.nonce
            },
            success: function(response) {
                if (response.success && response.data.user) {
                    currentUser = response.data.user;
                    currentEntries = response.data.entries || [];
                }
            }
        });
    }

    /**
     * Bind all event handlers
     */
    function bindEventHandlers() {
        // Mobile check form
        $(document).on('submit', '#mobileCheckForm', handleMobileCheck);

        // Health form
        $(document).on('submit', '#healthForm', handleHealthForm);

        // Entry form
        $(document).on('submit', '#entryForm', handleEntryForm);

        // Logout button
        $(document).on('click', '#logoutBtn', handleLogout);

        // Chart filter
        $(document).on('change', '#chartFilter', handleChartFilter);

        // Energy slider display
        $(document).on('input', '#energySlider', updateEnergyDisplay);
    }

    /**
     * Handle mobile number verification
     */
    function handleMobileCheck(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        
        // Get mobile from input (works for both regular and readonly)
        const mobile = $('#mobileInput').val().trim();

        // Client-side validation
        if (!validateMobile(mobile)) {
            showError('Please enter a valid 10-digit mobile number');
            return;
        }

        // Disable button
        $btn.prop('disabled', true).html('<span class="fjt-loading"></span> Checking...');

        $.ajax({
            url: fjtData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_check_user',
                nonce: fjtData.nonce,
                mobile_number: mobile
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.exists) {
                        // User exists - reload to show dashboard
                        currentUser = response.data.user;
                        currentEntries = response.data.entries;
                        location.reload();
                    } else {
                        // New user - show health form
                        showHealthForm(mobile);
                    }
                } else {
                    showError(response.data.message || 'An error occurred');
                    $btn.prop('disabled', false).html('Continue');
                }
            },
            error: function() {
                showError('Connection error. Please try again.');
                $btn.prop('disabled', false).html('Continue');
            }
        });
    }

    /**
     * Show health form with auto-filled mobile
     */
    function showHealthForm(mobile) {
        $('#step1').removeClass('active').hide();
        $('#step2').addClass('active').show();

        // Auto-fill hidden and visible mobile fields
        $('#hidden_mobile').val(mobile);
        $('#mobile_display').val(mobile);

        // Scroll to top
        $('html, body').animate({ scrollTop: 0 }, 300);
    }

    /**
     * Get query parameters from URL
     */
    function getQueryParams() {
        const params = {};
        const queryString = window.location.search.substring(1);
        const pairs = queryString.split('&');
        
        for (let i = 0; i < pairs.length; i++) {
            const pair = pairs[i].split('=');
            if (pair[0]) {
                params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1] || '');
            }
        }
        
        return params;
    }

    /**
     * Handle health form submission
     */
    function handleHealthForm(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        let formData = $form.serialize();

        // Append query parameters to ensure they're included
        const queryParams = getQueryParams();
        const queryString = Object.keys(queryParams)
            .map(key => encodeURIComponent(key) + '=' + encodeURIComponent(queryParams[key]))
            .join('&');
        
        if (queryString) {
            formData += '&' + queryString;
        }

        // Client-side validation
        const fullName = $form.find('[name="full_name"]').val() || queryParams['full_name'] || '';
        const weight = parseFloat($form.find('[name="weight"]').val());
        const targetWeight = parseFloat($form.find('[name="target_weight"]').val());

        if (!fullName.trim()) {
            showError('Please enter your full name');
            return;
        }

        if (!weight || weight < 20 || weight > 300) {
            showError('Please enter a valid weight (20-300 kg)');
            return;
        }

        if (!targetWeight || targetWeight < 20 || targetWeight > 300) {
            showError('Please enter a valid target weight (20-300 kg)');
            $form.find('[name="target_weight"]').focus();
            return;
        }

        // Disable button
        $btn.prop('disabled', true).html('<span class="fjt-loading"></span> Saving...');

        $.ajax({
            url: fjtData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_save_user',
                nonce: fjtData.nonce,
                data: formData
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Profile saved! Redirecting...');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showError(response.data.message || 'Failed to save profile');
                    $btn.prop('disabled', false).html('Start My Journey 🙏');
                }
            },
            error: function() {
                showError('Connection error. Please try again.');
                $btn.prop('disabled', false).html('Start My Journey 🙏');
            }
        });
    }

    /**
     * Handle weight entry submission
     */
    function handleEntryForm(e) {
        e.preventDefault();

        const $form = $(this);
        const $btn = $form.find('button[type="submit"]');
        const weight = parseFloat($form.find('[name="weight"]').val());
        const mobile = $form.find('[name="mobile_number"]').val();

        // Validation
        if (!weight || weight < 20 || weight > 300) {
            showError('Please enter a valid weight (20-300 kg)');
            return;
        }

        // Disable button
        $btn.prop('disabled', true).html('<span class="fjt-loading"></span> Saving...');

        // Dynamically collect ALL form fields (no hardcoded field names)
        var postData = {
            action: 'fjt_add_entry',
            nonce: fjtData.nonce,
            mobile_number: mobile,
            weight: weight
        };

        // Collect every named input/select/textarea in the form (except mobile_number and weight already set)
        $form.find('[name]').each(function() {
            var name = $(this).attr('name');
            if (!name || name === 'mobile_number' || name === 'weight') return;
            var type = ($(this).attr('type') || '').toLowerCase();
            if (type === 'checkbox') {
                if ($(this).is(':checked')) {
                    // Collect all checked checkboxes into array
                    if (!postData[name]) postData[name] = [];
                    postData[name].push($(this).val());
                }
            } else if (type === 'radio') {
                if ($(this).is(':checked')) {
                    postData[name] = $(this).val();
                }
            } else {
                postData[name] = $(this).val();
            }
        });

        $.ajax({
            url: fjtData.ajaxUrl,
            type: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    showSuccess('Entry saved! Refreshing...');
                    
                    // Save progress tab to sessionStorage for current session persistence
                    sessionStorage.setItem('fjt_active_tab', 'progress');
                    
                    setTimeout(function() {
                        // Reload with tab parameter to ensure progress tab opens
                        const currentUrl = window.location.href.split('?')[0];
                        window.location.href = currentUrl + '?tab=progress';
                    }, 800);
                } else {
                    showError(response.data.message || 'Failed to save entry');
                    $btn.prop('disabled', false).html('Save Entry');
                }
            },
            error: function() {
                showError('Connection error. Please try again.');
                $btn.prop('disabled', false).html('Save Entry');
            }
        });
    }

    /**
     * Handle logout
     */
    function handleLogout(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to logout?')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('Logging out...');

        $.ajax({
            url: fjtData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_logout',
                nonce: fjtData.nonce
            },
            success: function(response) {
                // Clear session tab state on logout
                sessionStorage.removeItem('fjt_active_tab');
                
                showSuccess('Logged out successfully');
                setTimeout(function() {
                    location.reload();
                }, 500);
            },
            error: function() {
                // Clear session tab state even on error
                sessionStorage.removeItem('fjt_active_tab');
                
                showError('Logout failed. Clearing session...');
                setTimeout(function() {
                    location.reload();
                }, 1000);
            }
        });
    }

    /**
     * Initialize progress chart
     */
    function initProgressChart(entries) {
        const canvas = document.getElementById('progressChart');
        if (!canvas || entries.length === 0) return;

        const ctx = canvas.getContext('2d');

        // Destroy existing chart if any
        if (progressChart) {
            progressChart.destroy();
            progressChart = null;
        }
        
        // Prepare data
        const chartData = prepareChartData(entries);

        // Get target weight from global user data
        const targetWeight = (window.fjtUser && window.fjtUser.target_weight)
            ? parseFloat(window.fjtUser.target_weight) : null;

        // Build datasets
        const datasets = [{
            label: 'Weight (kg)',
            data: chartData.weights,
            borderColor: 'rgb(147, 51, 234)',
            backgroundColor: 'rgba(147, 51, 234, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: 'rgb(147, 51, 234)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }];

        // Add target weight line if set
        if (targetWeight && targetWeight > 0) {
            const targetData = chartData.labels.map(function() { return targetWeight; });
            datasets.push({
                label: 'Target Weight',
                data: targetData,
                borderColor: 'rgba(16, 185, 129, 0.85)',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [8, 4],
                tension: 0,
                fill: false,
                pointRadius: 0,
                pointHoverRadius: 0
            });
        }

        // Create chart
        progressChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            pointStyleWidth: 16,
                            boxHeight: 2
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        bodyFont: { size: 14 },
                        filter: function(item) {
                            // Show target weight in tooltip only if it's set
                            return true;
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                        ticks: {
                            callback: function(value) { return value + ' kg'; }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }

    /**
     * Prepare chart data from entries
     */
    function prepareChartData(entries) {
        const labels = [];
        const weights = [];

        entries.forEach(function(entry) {
            const date = new Date(entry.created_at);
            labels.push(formatDate(date));
            weights.push(parseFloat(entry.weight));
        });

        return { labels, weights };
    }

    /**
     * Handle chart filter change
     */
    function handleChartFilter(e) {
        const filter = $(this).val();
        
        if (!window.fjtEntries) return;

        let filteredEntries = window.fjtEntries;

        if (filter !== 'all') {
            const days = parseInt(filter);
            const cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - days);
            filteredEntries = window.fjtEntries.filter(function(entry) {
                return new Date(entry.created_at) >= cutoffDate;
            });
        }

        // Rebuild chart fully to maintain target weight line across filters
        if (progressChart) {
            progressChart.destroy();
            progressChart = null;
        }
        if (filteredEntries.length > 0) {
            initProgressChart(filteredEntries);
        }
    }

    /**
     * Initialize energy slider
     */
    function initEnergySlider() {
        updateEnergyDisplay();
    }

    /**
     * Update energy slider display
     */
    function updateEnergyDisplay() {
        const value = $('#energySlider').val();
        $('#energyValue').text(value + '/10');
    }

    /**
     * Validate mobile number
     */
    function validateMobile(mobile) {
        // Remove non-digits
        mobile = mobile.replace(/\D/g, '');
        
        // Check length
        if (mobile.length < 10 || mobile.length > 15) {
            return false;
        }

        // Indian mobile validation (10 digits starting with 6-9)
        if (mobile.length === 10) {
            return /^[6-9][0-9]{9}$/.test(mobile);
        }

        return true;
    }

    /**
     * Format date for display
     */
    function formatDate(date) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[date.getMonth()] + ' ' + date.getDate();
    }

    /**
     * Show error message
     */
    function showError(message) {
        // Create toast notification
        const $toast = $('<div class="fjt-toast fjt-toast-error">' + message + '</div>');
        $('body').append($toast);

        setTimeout(function() {
            $toast.addClass('fjt-toast-show');
        }, 100);

        setTimeout(function() {
            $toast.removeClass('fjt-toast-show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        const $toast = $('<div class="fjt-toast fjt-toast-success">' + message + '</div>');
        $('body').append($toast);

        setTimeout(function() {
            $toast.addClass('fjt-toast-show');
        }, 100);

        setTimeout(function() {
            $toast.removeClass('fjt-toast-show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, 3000);
    }

    /**
     * Initialize tab system
     */
    function initTabs() {
        const $tabs = $('.fjt-tab-btn');
        const $panels = $('.fjt-tab-panel');

        if ($tabs.length === 0) return;

        // Check for saved tab BEFORE binding any events
        const urlParams = new URLSearchParams(window.location.search);
        const urlTab = urlParams.get('tab');
        const sessionTab = sessionStorage.getItem('fitness_active_tab');
        
        let activeTab = null;
        
        // Priority: URL parameter > sessionStorage > default (first tab)
        if (urlTab) {
            activeTab = urlTab;
            // Save URL tab to sessionStorage for persistence during session
            sessionStorage.setItem('fitness_active_tab', urlTab);
        } else if (sessionTab) {
            activeTab = sessionTab;
        }

        // Manual activation function (bypasses click handler to avoid race conditions)
        function activateTab(tabName) {
            $tabs.removeClass('active');
            $panels.hide().removeClass('active');
            
            const $targetTab = $tabs.filter('[data-tab="' + tabName + '"]');
            const $targetPanel = $('#fjt-tab-' + tabName);
            
            if ($targetTab.length > 0 && $targetPanel.length > 0) {
                $targetTab.addClass('active');
                $targetPanel.show().addClass('active');
                
                // Re-init chart when activating progress tab
                if (tabName === 'progress' && window.fjtEntries && window.fjtEntries.length > 0) {
                    if (!progressChart) {
                        initProgressChart(window.fjtEntries);
                    } else {
                        progressChart.resize();
                    }
                }
                
                return true;
            }
            
            return false;
        }

        // Activate saved or default tab IMMEDIATELY
        if (activeTab) {
            const activated = activateTab(activeTab);
            if (!activated) {
                // Fallback if saved tab doesn't exist
                activateTab('add-entry');
            }
        } else {
            // Default to first tab
            activateTab('add-entry');
        }

        // Bind click handlers AFTER initial activation
        $tabs.on('click', function() {
            const target = $(this).data('tab');
            
            // Save to sessionStorage
            sessionStorage.setItem('fitness_active_tab', target);
            
            // Activate the tab
            activateTab(target);
        });
    }

})(jQuery);

// Toast styles (inline for simplicity)
jQuery(document).ready(function($) {
    if (!$('#fjt-toast-styles').length) {
        $('head').append(`
            <style id="fjt-toast-styles">
                .fjt-toast {
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    padding: 16px 24px;
                    border-radius: 12px;
                    color: white;
                    font-weight: 600;
                    font-size: 14px;
                    z-index: 9999;
                    opacity: 0;
                    transform: translateX(400px);
                    transition: all 0.3s ease;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                }
                .fjt-toast-show {
                    opacity: 1;
                    transform: translateX(0);
                }
                .fjt-toast-error {
                    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
                }
                .fjt-toast-success {
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                }
                @media (max-width: 640px) {
                    .fjt-toast {
                        left: 20px;
                        right: 20px;
                        top: auto;
                        bottom: 20px;
                        transform: translateY(200px);
                    }
                    .fjt-toast-show {
                        transform: translateY(0);
                    }
                }
            </style>
        `);
    }
}); // end jQuery

/* =============================================
   FJT Dropdown helpers — global scope
   Used by dynamically rendered select fields
   ============================================= */
window.fjtToggleDropdown = function(ddId) {
    var menu = document.getElementById(ddId);
    if (!menu) return;
    var wrap = menu.closest('.fjt-dropdown-wrap');
    var isOpen = menu.classList.contains('active');

    // Close all open dropdowns first
    document.querySelectorAll('.fjt-dropdown-menu.active').forEach(function(m) {
        m.classList.remove('active');
        var w = m.closest('.fjt-dropdown-wrap');
        if (w) w.classList.remove('open');
    });

    if (!isOpen) {
        menu.classList.add('active');
        if (wrap) wrap.classList.add('open');
    }
};

window.fjtSelectDropdown = function(fieldName, value) {
    var textEl  = document.getElementById(fieldName + '_dd_text');
    var inputEl = document.getElementById(fieldName + '_input');
    var ddId    = fieldName + '_dd';
    var menu    = document.getElementById(ddId);
    var wrap    = menu ? menu.closest('.fjt-dropdown-wrap') : null;

    if (textEl) {
        textEl.textContent = value;
        textEl.classList.remove('fjt-dd-placeholder');
        textEl.classList.add('selected');
    }
    if (inputEl) inputEl.value = value;

    // Mark selected item
    if (menu) {
        menu.querySelectorAll('.fjt-dd-item').forEach(function(btn) {
            btn.classList.toggle('selected', btn.textContent.trim() === value);
        });
        menu.classList.remove('active');
    }
    if (wrap) wrap.classList.remove('open');
};

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.fjt-dropdown-wrap')) {
        document.querySelectorAll('.fjt-dropdown-menu.active').forEach(function(m) {
            m.classList.remove('active');
            var w = m.closest('.fjt-dropdown-wrap');
            if (w) w.classList.remove('open');
        });
    }
});