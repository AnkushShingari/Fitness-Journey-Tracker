/**
 * Fitness Journey Tracker - Admin Dashboard JavaScript
 * Handles user management, CRUD operations, and analytics charts
 */

(function($) {
    'use strict';

    // Global state
    let currentPage = 1;
    let totalPages = 1;
    let currentFilter = 'all';
    let searchQuery = '';

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Load users on dashboard page
        if ($('#usersTableBody').length) {
            loadUsers();
        }

        // Initialize user detail charts
        if (window.fjtUserEntries && $('#userProgressChart').length) {
            initUserProgressChart(window.fjtUserEntries);
        }

        // Initialize analytics charts
        if ($('.fjt-charts-row').length) {
            initAnalyticsCharts();
        }

        // Bind event handlers
        bindEventHandlers();
    });

    /**
     * Bind all event handlers
     */
    function bindEventHandlers() {
        // Search button
        $(document).on('click', '#searchBtn', performSearch);
        
        // Search on Enter key
        $(document).on('keypress', '#userSearch', function(e) {
            if (e.which === 13) {
                performSearch();
            }
        });

        // Filter buttons
        $(document).on('click', '.fjt-filter-btn', function() {
            $('.fjt-filter-btn').removeClass('active');
            $(this).addClass('active');
            currentFilter = $(this).data('filter');
            currentPage = 1; // Reset to first page
            loadUsers();
        });

        // Pagination buttons
        $(document).on('click', '.fjt-page-btn', function() {
            const page = $(this).data('page');
            if (page && page !== currentPage) {
                currentPage = page;
                loadUsers();
            }
        });
    }

    /**
     * Load users via AJAX with pagination
     */
    function loadUsers() {
        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_admin_get_users',
                nonce: fjtAdminData.nonce,
                search: searchQuery,
                filter: currentFilter,
                page: currentPage
            },
            success: function(response) {
                if (response.success) {
                    const users = response.data.users;
                    const pagination = response.data.pagination;
                    
                    totalPages = pagination.total_pages;
                    currentPage = pagination.current_page;
                    
                    renderUsersTable(users);
                    renderPagination(pagination);
                } else {
                    showError('Failed to load users');
                }
            },
            error: function() {
                showError('Connection error');
            }
        });
    }

    /**
     * Perform search
     */
    function performSearch() {
        searchQuery = $('#userSearch').val().trim();
        currentPage = 1; // Reset to first page
        loadUsers();
    }

    /**
     * Render users table
     */
    function renderUsersTable(users) {
        const $tbody = $('#usersTableBody');

        if (!users || users.length === 0) {
            $tbody.html('<tr><td colspan="9" class="fjt-table-message">No users found</td></tr>');
            return;
        }

        let html = '';

        users.forEach(function(user) {
            const statusBadge = user.is_restricted == 1 
                ? '<span class="fjt-badge fjt-badge-danger">Restricted</span>'
                : '<span class="fjt-badge fjt-badge-success">Active</span>';
            
            const currentWeight = user.current_weight !== '--' ? user.current_weight + ' kg' : '--';

            html += `
                <tr>
                    <td><strong>${escapeHtml(user.mobile_number)}</strong></td>
                    <td>${escapeHtml(user.full_name)}</td>
                    <td>${escapeHtml(user.email || '-')}</td>
                    <td>${escapeHtml(user.goal || '-')}</td>
                    <td>${currentWeight}</td>
                    <td>${user.target_weight ? user.target_weight + ' kg' : '-'}</td>
                    <td><strong>${user.total_entries || 0}</strong></td>
                    <td>${statusBadge}</td>
                    <td>
                        <a href="?page=fitness-tracker-user&mobile=${encodeURIComponent(user.mobile_number)}" 
                           class="fjt-btn fjt-btn-sm fjt-btn-primary">
                            View
                        </a>
                    </td>
                </tr>
            `;
        });

        $tbody.html(html);
    }

    /**
     * Render pagination controls
     */
    function renderPagination(pagination) {
        const container = $('.fjt-table-container');
        
        // Remove existing pagination
        $('.fjt-pagination').remove();
        
        if (pagination.total_pages <= 1) {
            return;
        }
        
        let paginationHtml = '<div class="fjt-pagination" style="margin-top: 20px; text-align: center;">';
        
        // Previous button
        if (pagination.current_page > 1) {
            paginationHtml += `<button class="fjt-btn fjt-btn-sm fjt-page-btn" data-page="${pagination.current_page - 1}">← Previous</button>`;
        }
        
        // Page numbers
        const maxVisible = 5;
        let startPage = Math.max(1, pagination.current_page - Math.floor(maxVisible / 2));
        let endPage = Math.min(pagination.total_pages, startPage + maxVisible - 1);
        
        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }
        
        if (startPage > 1) {
            paginationHtml += `<button class="fjt-btn fjt-btn-sm fjt-page-btn" data-page="1">1</button>`;
            if (startPage > 2) {
                paginationHtml += '<span style="margin: 0 5px;">...</span>';
            }
        }
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current_page ? 'fjt-btn-primary' : '';
            paginationHtml += `<button class="fjt-btn fjt-btn-sm fjt-page-btn ${activeClass}" data-page="${i}">${i}</button>`;
        }
        
        if (endPage < pagination.total_pages) {
            if (endPage < pagination.total_pages - 1) {
                paginationHtml += '<span style="margin: 0 5px;">...</span>';
            }
            paginationHtml += `<button class="fjt-btn fjt-btn-sm fjt-page-btn" data-page="${pagination.total_pages}">${pagination.total_pages}</button>`;
        }
        
        // Next button
        if (pagination.current_page < pagination.total_pages) {
            paginationHtml += `<button class="fjt-btn fjt-btn-sm fjt-page-btn" data-page="${pagination.current_page + 1}">Next →</button>`;
        }
        
        paginationHtml += `<span style="margin-left: 15px; color: #666;">Showing ${(pagination.current_page - 1) * pagination.per_page + 1}-${Math.min(pagination.current_page * pagination.per_page, pagination.total_users)} of ${pagination.total_users}</span>`;
        paginationHtml += '</div>';
        
        container.after(paginationHtml);
    }

    /**
     * Get latest weight from user profile
     */
    function getLatestWeight(user) {
        if (user.user_profile) {
            try {
                const profile = typeof user.user_profile === 'string' 
                    ? JSON.parse(user.user_profile) 
                    : user.user_profile;
                
                if (profile.current_weight) {
                    return profile.current_weight + ' kg';
                }
            } catch (e) {
                // Ignore parse errors
            }
        }
        return '-';
    }

    /**
     * Restrict/Unrestrict user
     */
    window.fjtRestrict = function(mobile, status) {
        const action = status === 1 ? 'restrict' : 'unrestrict';
        const message = status === 1 
            ? 'Are you sure you want to restrict this user? They will be logged out immediately.' 
            : 'Are you sure you want to unrestrict this user?';

        if (!confirm(message)) {
            return;
        }

        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_admin_restrict_user',
                nonce: fjtAdminData.nonce,
                mobile: mobile,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    showSuccess(response.data.message);
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showError(response.data.message || 'Operation failed');
                }
            },
            error: function() {
                showError('Connection error');
            }
        });
    };

    /**
     * Delete user
     */
    window.fjtDeleteUser = function(mobile) {
        if (!confirm('Are you sure you want to delete this user? This action cannot be undone. All their data will be permanently removed.')) {
            return;
        }

        if (!confirm('Final confirmation: This will delete ALL user data including weight entries and history. Continue?')) {
            return;
        }

        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_admin_delete_user',
                nonce: fjtAdminData.nonce,
                mobile: mobile
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('User deleted successfully');
                    setTimeout(function() {
                        window.location.href = '?page=fitness-tracker';
                    }, 1000);
                } else {
                    showError(response.data.message || 'Delete failed');
                }
            },
            error: function() {
                showError('Connection error');
            }
        });
    };

    /**
     * Edit entry - inline editing
     */
    window.fjtEditEntry = function(entryId) {
        const $row = $('tr[data-entry-id="' + entryId + '"]');
        
        if ($row.hasClass('fjt-editing')) {
            return; // Already editing
        }
        
        // Get current values
        const currentWeight = $row.find('td:eq(1) strong').text().trim();
        const currentFeeling = $row.find('td:eq(3)').text().trim();
        const currentSleep = $row.find('td:eq(4)').text().trim();
        const currentEnergy = $row.find('td:eq(5)').text().replace('/10', '').trim();
        
        // Store original HTML
        $row.data('original-html', $row.html());
        
        // Build edit HTML
        const editHTML = `
            <td colspan="7" class="fjt-entry-edit-container">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; padding: 12px;">
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px;">Weight (kg)</label>
                        <input type="number" class="fjt-entry-edit-weight" value="${currentWeight}" min="20" max="300" step="0.1" 
                               style="width: 100%; padding: 8px; border: 2px solid #667eea; border-radius: 6px; font-weight: 600;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px;">Feeling</label>
                        <input type="text" class="fjt-entry-edit-feeling" value="${currentFeeling === '-' ? '' : currentFeeling}" 
                               style="width: 100%; padding: 8px; border: 2px solid #667eea; border-radius: 6px;">
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px;">Sleep</label>
                        <select class="fjt-entry-edit-sleep" style="width: 100%; padding: 8px; border: 2px solid #667eea; border-radius: 6px;">
                            <option value="">Select</option>
                            <option value="Poor" ${currentSleep === 'Poor' ? 'selected' : ''}>Poor</option>
                            <option value="Fair" ${currentSleep === 'Fair' ? 'selected' : ''}>Fair</option>
                            <option value="Good" ${currentSleep === 'Good' ? 'selected' : ''}>Good</option>
                            <option value="Excellent" ${currentSleep === 'Excellent' ? 'selected' : ''}>Excellent</option>
                        </select>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; margin-bottom: 4px; font-size: 12px;">Energy Level</label>
                        <input type="number" class="fjt-entry-edit-energy" value="${currentEnergy === '-' ? '5' : currentEnergy}" min="1" max="10" 
                               style="width: 100%; padding: 8px; border: 2px solid #667eea; border-radius: 6px;">
                    </div>
                    <div style="display: flex; gap: 8px; align-items: flex-end;">
                        <button class="fjt-btn fjt-btn-success fjt-save-entry-edit" data-entry-id="${entryId}" 
                                style="flex: 1; padding: 10px; border-radius: 8px;">💾 Save</button>
                        <button class="fjt-btn fjt-btn-secondary fjt-cancel-entry-edit" 
                                style="flex: 1; padding: 10px; border-radius: 8px;">❌ Cancel</button>
                    </div>
                </div>
            </td>
        `;
        
        $row.addClass('fjt-editing').html(editHTML);
    };

    /**
     * Save entry edit
     */
    $(document).on('click', '.fjt-save-entry-edit', function() {
        const entryId = $(this).data('entry-id');
        const $row = $('tr[data-entry-id="' + entryId + '"]');
        
        const weight = parseFloat($row.find('.fjt-entry-edit-weight').val());
        const feeling = $row.find('.fjt-entry-edit-feeling').val().trim();
        const sleep = $row.find('.fjt-entry-edit-sleep').val();
        const energy = parseInt($row.find('.fjt-entry-edit-energy').val());
        
        if (!weight || weight < 20 || weight > 300) {
            alert('Invalid weight. Must be between 20-300 kg.');
            return;
        }
        
        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_admin_update_entry_full',
                nonce: fjtAdminData.nonce,
                entry_id: entryId,
                weight: weight,
                feeling: feeling,
                sleep: sleep,
                energy: energy
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (response.data.message || 'Update failed'));
                    $row.removeClass('fjt-editing').html($row.data('original-html'));
                }
            },
            error: function() {
                alert('Connection error');
                $row.removeClass('fjt-editing').html($row.data('original-html'));
            }
        });
    });

    /**
     * Cancel entry edit
     */
    $(document).on('click', '.fjt-cancel-entry-edit', function() {
        const $row = $(this).closest('tr');
        $row.removeClass('fjt-editing').html($row.data('original-html'));
    });

    /**
     * Delete entry
     */
    window.fjtDeleteEntry = function(entryId, mobile) {
        if (!confirm('Delete this entry?')) {
            return;
        }

        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_admin_delete_entry',
                nonce: fjtAdminData.nonce,
                entry_id: entryId,
                mobile: mobile
            },
            success: function(response) {
                if (response.success) {
                    showSuccess('Entry deleted');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showError('Delete failed');
                }
            },
            error: function() {
                showError('Connection error');
            }
        });
    };

    /**
     * Initialize user progress chart
     */
    function initUserProgressChart(entries) {
        const canvas = document.getElementById('userProgressChart');
        if (!canvas || entries.length === 0) return;

        const ctx = canvas.getContext('2d');
        
        const labels = [];
        const weights = [];

        entries.forEach(function(entry) {
            const date = new Date(entry.created_at);
            labels.push(formatDate(date));
            weights.push(parseFloat(entry.weight));
        });

        const targetWeight = (typeof window.fjtTargetWeight !== 'undefined' && window.fjtTargetWeight)
            ? parseFloat(window.fjtTargetWeight) : null;

        const datasets = [{
            label: 'Weight (kg)',
            data: weights,
            borderColor: 'rgb(102, 126, 234)',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            borderWidth: 3,
            tension: 0.4,
            fill: true,
            pointRadius: 5,
            pointHoverRadius: 7,
            pointBackgroundColor: 'rgb(102, 126, 234)',
            pointBorderColor: '#fff',
            pointBorderWidth: 2
        }];

        if (targetWeight) {
            datasets.push({
                label: 'Target Weight',
                data: labels.map(function() { return targetWeight; }),
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'transparent',
                borderWidth: 2,
                borderDash: [6, 4],
                tension: 0,
                fill: false,
                pointRadius: 0,
                pointHoverRadius: 0
            });
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        ticks: {
                            callback: function(value) {
                                return value + ' kg';
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Initialize analytics charts
     */
    function initAnalyticsCharts() {
        // Growth chart
        if (window.fjtGrowthData && $('#growthChart').length) {
            const ctx = document.getElementById('growthChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: window.fjtGrowthData.labels,
                    datasets: [{
                        label: 'New Users',
                        data: window.fjtGrowthData.values,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: 'rgb(102, 126, 234)',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    }
                }
            });
        }

        // Status distribution chart
        if (window.fjtStatusData && $('#statusChart').length) {
            const ctx = document.getElementById('statusChart').getContext('2d');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Active', 'Restricted', 'Completed'],
                    datasets: [{
                        data: [
                            window.fjtStatusData.active,
                            window.fjtStatusData.restricted,
                            window.fjtStatusData.completed
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(59, 130, 246, 0.8)'
                        ],
                        borderColor: [
                            'rgb(16, 185, 129)',
                            'rgb(239, 68, 68)',
                            'rgb(59, 130, 246)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    }

    /**
     * Format date
     */
    function formatDate(date) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return months[date.getMonth()] + ' ' + date.getDate();
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Show notice (global, used by entry edit handlers)
     */
    window.fjtShowNotice = function(message, type) {
        type = type || 'success';
        var bg = type === 'success' ? '#10b981' : '#ef4444';
        var safeMsg = $('<div>').text(message).html();
        var $toast = $('<div style="position:fixed;top:32px;right:24px;z-index:99999;padding:14px 22px;border-radius:10px;color:#fff;font-weight:600;font-size:14px;box-shadow:0 6px 24px rgba(0,0,0,.2);background:' + bg + ';opacity:0;transition:opacity .3s ease">' + safeMsg + '</div>');
        $('body').append($toast);
        setTimeout(function() { $toast.css('opacity', 1); }, 50);
        setTimeout(function() { $toast.css('opacity', 0); setTimeout(function() { $toast.remove(); }, 400); }, 3000);
    };

    /**
     * Show error toast
     */
    function showError(message) {
        window.fjtShowNotice(message, 'error');
    }

    /**
     * Show success toast
     */
    function showSuccess(message) {
        window.fjtShowNotice(message, 'success');
    }

    // ==================== INLINE EDITING SYSTEM ====================

    /**
     * Toggle edit mode
     */
    $(document).on('click', '#fjtToggleEdit', function() {
        const $btn = $(this);
        const $container = $('.fjt-info-grid').closest('.fjt-user-card');
        
        if ($container.hasClass('fjt-editing-enabled')) {
            // Disable editing
            $container.removeClass('fjt-editing-enabled');
            $('.fjt-editable-field').removeClass('editing');
            $btn.find('.fjt-edit-toggle-text').text('Enable Editing');
            $btn.removeClass('fjt-btn-warning').addClass('fjt-btn-primary');
        } else {
            // Enable editing
            $container.addClass('fjt-editing-enabled');
            $btn.find('.fjt-edit-toggle-text').text('Disable Editing');
            $btn.removeClass('fjt-btn-primary').addClass('fjt-btn-warning');
        }
    });

    /**
     * Click on field to edit (only when editing enabled)
     */
    $(document).on('click', '.fjt-editing-enabled .fjt-display-mode', function() {
        const $field = $(this).closest('.fjt-editable-field');
        $field.addClass('editing');
        $field.find('.fjt-edit-input').focus();
    });

    /**
     * Save inline edit
     */
    $(document).on('click', '.fjt-save-edit', function() {
        const $field = $(this).closest('.fjt-editable-field');
        const fieldName = $field.data('field');
        const $input = $field.find('.fjt-edit-input');
        const newValue = $input.val().trim();
        const mobile = $('#fjtUserMobile').val();

        if (!mobile) {
            alert('Error: User mobile number not found');
            return;
        }

        // Prepare update data
        const updateData = {
            action: 'fjt_admin_update_user_field',
            nonce: fjtAdminData.nonce,
            mobile: mobile,
            field: fieldName,
            value: newValue
        };

        // Save via AJAX
        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: updateData,
            success: function(response) {
                if (response.success) {
                    // Update display
                    const displayValue = newValue || 'Not provided';
                    $field.find('.fjt-display-mode').text(displayValue);
                    $field.removeClass('editing');
                    
                    // Show success message
                    const $display = $field.find('.fjt-display-mode');
                    $display.css('background', '#d1fae5').css('color', '#065f46');
                    setTimeout(function() {
                        $display.css('background', '').css('color', '');
                    }, 2000);
                } else {
                    alert('Error: ' + (response.data.message || 'Update failed'));
                }
            },
            error: function() {
                alert('Error: Connection failed');
            }
        });
    });

    /**
     * Cancel inline edit
     */
    $(document).on('click', '.fjt-cancel-edit', function() {
        const $field = $(this).closest('.fjt-editable-field');
        $field.removeClass('editing');
    });

    /**
     * Enter key to save, Escape to cancel
     */
    $(document).on('keydown', '.fjt-edit-input', function(e) {
        if (e.key === 'Enter') {
            $(this).siblings('.fjt-save-edit').click();
        } else if (e.key === 'Escape') {
            $(this).siblings('.fjt-cancel-edit').click();
        }
    });

})(jQuery);

// ===== FORM BUILDER FUNCTIONALITY =====

(function($) {
    'use strict';

    $(document).ready(function() {
        if ($('.fjt-form-builder-wrap').length) {
            initFormBuilder();
        }
    });

    function initFormBuilder() {
        // Tab switching
        $('.fjt-fb-tab').on('click', function() {
            const formName = $(this).data('form');
            $('.fjt-fb-tab').removeClass('active');
            $(this).addClass('active');
            $('.fjt-fb-form-content').hide();
            $('#form-' + formName).show();
        });

        // Add field button
        $('.add-field-btn').on('click', function() {
            const formName = $(this).data('form');
            $('#addFieldModal').data('current-form', formName).fadeIn(200);
        });

        // Close modal
        $('.fjt-modal-close').on('click', function() {
            $(this).closest('.fjt-modal').fadeOut(200);
        });

        // Create field
        $('#createFieldBtn').on('click', createNewField);

        // Edit field button
        $(document).on('click', '.edit-field-btn', function() {
            const $item = $(this).closest('.fjt-fb-field-item');
            $item.find('.fjt-fb-field-edit').slideToggle(200);
        });

        // Cancel edit
        $(document).on('click', '.cancel-edit-btn', function() {
            $(this).closest('.fjt-fb-field-edit').slideUp(200);
        });

        // Save field
        $(document).on('click', '.save-field-btn', saveFieldChanges);

        // Delete field
        $(document).on('click', '.delete-field-btn', deleteField);

        // Field type change - show/hide relevant options
        $(document).on('change', '.field-type', function() {
            const $edit = $(this).closest('.fjt-fb-field-edit');
            const type = $(this).val();

            $edit.find('.number-fields').hide();
            $edit.find('.text-fields').hide();
            $edit.find('.range-fields').hide();
            $edit.find('.option-fields').hide();

            if (type === 'number' || type === 'range') {
                $edit.find('.number-fields').show();
            }
            if (type === 'number') {
                $edit.find('.number-fields').eq(2).show(); // step field
            }
            if (type === 'text' || type === 'textarea') {
                $edit.find('.text-fields').show();
            }
            if (type === 'range') {
                $edit.find('.range-fields').show();
            }
            if (type === 'select' || type === 'radio' || type === 'checkbox') {
                $edit.find('.option-fields').show();
            }
        });

        // Initialize Sortable for drag and drop
        $('.fjt-fb-fields-list').each(function() {
            const formName = $(this).attr('id').replace('fields-', '');
            new Sortable(this, {
                handle: '.fjt-fb-drag-handle',
                animation: 150,
                onEnd: function(evt) {
                    saveFieldOrder(formName);
                }
            });
        });
    }

    function createNewField() {
        const formName = $('#addFieldModal').data('current-form');
        const fieldName = $('#newFieldName').val().trim();
        const fieldLabel = $('#newFieldLabel').val().trim();
        const fieldType = $('#newFieldType').val();

        if (!fieldName || !fieldLabel) {
            alert('Please fill in all fields');
            return;
        }

        if (!/^[a-z_]+$/.test(fieldName)) {
            alert('Field name must be lowercase with underscores only (no spaces)');
            return;
        }

        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_fb_add_field',
                nonce: fjtAdminData.nonce,
                form_name: formName,
                field_name: fieldName,
                field_label: fieldLabel,
                field_type: fieldType
            },
            success: function(response) {
                if (response.success) {
                    alert('Field added successfully! Refreshing page...');
                    location.reload();
                } else {
                    alert(response.data.message || 'Failed to add field');
                }
            },
            error: function() {
                alert('Connection error');
            }
        });
    }

    function saveFieldChanges() {
        const $item = $(this).closest('.fjt-fb-field-item');
        const $edit = $item.find('.fjt-fb-field-edit');
        const formName = $item.data('form');
        const fieldName = $item.data('field');

        const fieldConfig = {
            label: $edit.find('.field-label').val(),
            type: $edit.find('.field-type').val(),
            placeholder: $edit.find('.field-placeholder').val() || '',
            validation: $edit.find('.field-validation').val(),
            required: $edit.find('.field-required').is(':checked'),
            order: $item.index() + 1
        };

        // Add type-specific attributes
        if (fieldConfig.type === 'number' || fieldConfig.type === 'range') {
            fieldConfig.min = parseFloat($edit.find('.field-min').val()) || 0;
            fieldConfig.max = parseFloat($edit.find('.field-max').val()) || 100;
        }

        if (fieldConfig.type === 'number') {
            fieldConfig.step = parseFloat($edit.find('.field-step').val()) || 1;
        }

        if (fieldConfig.type === 'range') {
            fieldConfig.default = parseFloat($edit.find('.field-default').val()) || 50;
        }

        if (fieldConfig.type === 'text' || fieldConfig.type === 'textarea') {
            fieldConfig.max_length = parseInt($edit.find('.field-maxlength').val()) || 255;
        }

        if (fieldConfig.type === 'select' || fieldConfig.type === 'radio' || fieldConfig.type === 'checkbox') {
            const optionsText = $edit.find('.field-options').val();
            fieldConfig.options = optionsText.split('\n').map(o => o.trim()).filter(o => o);
        }

        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_fb_save_field',
                nonce: fjtAdminData.nonce,
                form_name: formName,
                field_name: fieldName,
                field_config: JSON.stringify(fieldConfig)
            },
            success: function(response) {
                if (response.success) {
                    alert('Field saved successfully! Refreshing page...');
                    location.reload();
                } else {
                    alert('Failed to save field');
                }
            },
            error: function() {
                alert('Connection error');
            }
        });
    }

    function deleteField() {
        const $item = $(this).closest('.fjt-fb-field-item');
        const formName = $item.data('form');
        const fieldName = $item.data('field');

        if (!confirm('Are you sure you want to delete this field?\n\nNote: Existing data in this field will NOT be deleted, but the field will no longer appear in forms.')) {
            return;
        }

        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_fb_delete_field',
                nonce: fjtAdminData.nonce,
                form_name: formName,
                field_name: fieldName
            },
            success: function(response) {
                if (response.success) {
                    $item.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || 'Failed to delete field');
                }
            },
            error: function() {
                alert('Connection error');
            }
        });
    }

    function saveFieldOrder(formName) {
        const fieldOrder = [];
        $('#fields-' + formName + ' .fjt-fb-field-item').each(function() {
            fieldOrder.push($(this).data('field'));
        });

        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_fb_reorder_fields',
                nonce: fjtAdminData.nonce,
                form_name: formName,
                field_order: JSON.stringify(fieldOrder)
            },
            success: function(response) {
                if (!response.success) {
                    console.error('Failed to save field order');
                }
            }
        });
    }

    // ===== ENTRY ACCORDION & INLINE EDITING =====
    
    /**
     * Toggle entry accordion
     */
    window.fjtToggleEntryAccordion = function(entryId) {
        const $card = $(`.fjt-entry-card[data-entry-id="${entryId}"]`);
        const $body = $card.find('.fjt-entry-body');
        const $icon = $card.find('.fjt-accordion-icon');
        
        if ($body.is(':visible')) {
            $body.slideUp(200);
            $icon.text('▼');
            $card.removeClass('expanded');
        } else {
            $body.slideDown(200);
            $icon.text('▲');
            $card.addClass('expanded');
        }
    };

    /**
     * Toggle entry edit mode
     */
    window.fjtToggleEntryEdit = function(entryId) {
        const $card = $(`.fjt-entry-card[data-entry-id="${entryId}"]`);
        const $editBtn = $card.find('.fjt-entry-edit-btn');
        const $fields = $card.find('.fjt-editable-field');
        
        if ($card.hasClass('editing')) {
            // Disable editing
            $card.removeClass('editing');
            $fields.removeClass('editing');
            $editBtn.text('✏️').attr('title', 'Edit Entry');
        } else {
            // Enable editing
            $card.addClass('editing');
            $fields.addClass('editing');
            $editBtn.text('💾').attr('title', 'Finish Editing');
            
            // Expand accordion if not already
            if (!$card.find('.fjt-entry-body').is(':visible')) {
                fjtToggleEntryAccordion(entryId);
            }
        }
    };

    /**
     * Save entry field (inline editing)
     */
    $(document).on('click', '.fjt-save-entry-field', function() {
        const $field = $(this).closest('.fjt-editable-field');
        const entryId = $field.data('entry-id');
        const fieldName = $field.data('field');
        const value = $field.find('.fjt-edit-input').val();
        
        $.ajax({
            url: fjtAdminData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'fjt_admin_update_entry_full',
                nonce: fjtAdminData.nonce,
                entry_id: entryId,
                field: fieldName,
                value: value
            },
            success: function(response) {
                if (response.success) {
                    // Update display value
                    const displayValue = fieldName === 'weight' ? '<strong>' + value + ' kg</strong>' : value;
                    $field.find('.fjt-display-mode').html(displayValue);
                    
                    // Exit edit mode
                    $field.removeClass('editing');
                    
                    fjtShowNotice('Field updated successfully', 'success');
                } else {
                    fjtShowNotice(response.data.message || 'Update failed', 'error');
                }
            },
            error: function() {
                fjtShowNotice('Connection error', 'error');
            }
        });
    });

    /**
     * Cancel entry field edit
     */
    $(document).on('click', '.fjt-entry-card .fjt-cancel-edit', function() {
        const $field = $(this).closest('.fjt-editable-field');
        $field.removeClass('editing');
    });

})(jQuery);