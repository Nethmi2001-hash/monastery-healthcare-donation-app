/**
 * Advanced Search System
 * Smart filtering across monks, donations, appointments
 */

class AdvancedSearch {
    constructor(searchType) {
        this.searchType = searchType;
        this.filters = {};
        this.results = [];
    }

    /**
     * Initialize search UI
     */
    init() {
        this.createSearchUI();
        this.attachEventListeners();
    }

    /**
     * Create advanced search UI
     */
    createSearchUI() {
        const container = document.getElementById('advanced-search');
        if (!container) return;

        const searchHTML = `
            <div class="advanced-search-panel" style="background: white; border-radius: 15px; padding: 25px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); margin-bottom: 25px;">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label"><i class="bi bi-search"></i> Search</label>
                        <input type="text" id="search-query" class="form-control" placeholder="Type to search...">
                    </div>
                    ${this.getFilterFields()}
                    <div class="col-md-12 d-flex gap-2 align-items-end">
                        <button class="btn btn-primary" onclick="advancedSearch.performSearch()">
                            <i class="bi bi-search"></i> Search
                        </button>
                        <button class="btn btn-outline-secondary" onclick="advancedSearch.resetFilters()">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </button>
                        <button class="btn btn-success" onclick="advancedSearch.exportResults()">
                            <i class="bi bi-file-earmark-excel"></i> Export
                        </button>
                        <div class="ms-auto">
                            <span id="results-count" class="badge bg-info"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div id="search-results"></div>
        `;

        container.innerHTML = searchHTML;
    }

    /**
     * Get filter fields based on search type
     */
    getFilterFields() {
        const fields = {
            monks: `
                <div class="col-md-2">
                    <label class="form-label">Blood Group</label>
                    <select id="filter-blood" class="form-select">
                        <option value="">All</option>
                        <option>A+</option><option>A-</option>
                        <option>B+</option><option>B-</option>
                        <option>O+</option><option>O-</option>
                        <option>AB+</option><option>AB-</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select id="filter-status" class="form-select">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Has Chronic Condition</label>
                    <select id="filter-chronic" class="form-select">
                        <option value="">All</option>
                        <option value="yes">Yes</option>
                        <option value="no">No</option>
                    </select>
                </div>
            `,
            donations: `
                <div class="col-md-2">
                    <label class="form-label">Min Amount</label>
                    <input type="number" id="filter-min-amount" class="form-control" placeholder="0">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Max Amount</label>
                    <input type="number" id="filter-max-amount" class="form-control" placeholder="999999">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select id="filter-status" class="form-select">
                        <option value="">All</option>
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="verified">Verified</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Payment Method</label>
                    <select id="filter-method" class="form-select">
                        <option value="">All</option>
                        <option value="bank">Bank</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                    </select>
                </div>
            `,
            appointments: `
                <div class="col-md-2">
                    <label class="form-label">From Date</label>
                    <input type="date" id="filter-from-date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">To Date</label>
                    <input type="date" id="filter-to-date" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select id="filter-status" class="form-select">
                        <option value="">All</option>
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            `
        };

        return fields[this.searchType] || '';
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Real-time search
        const searchInput = document.getElementById('search-query');
        if (searchInput) {
            let timeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => {
                    this.performSearch();
                }, 500);
            });
        }

        // Filter changes
        document.querySelectorAll('select[id^="filter-"], input[id^="filter-"]').forEach(element => {
            element.addEventListener('change', () => this.performSearch());
        });
    }

    /**
     * Perform search with filters
     */
    async performSearch() {
        const query = document.getElementById('search-query')?.value || '';
        
        // Collect filters
        this.filters = {
            query: query,
            searchType: this.searchType
        };

        // Add type-specific filters
        document.querySelectorAll('[id^="filter-"]').forEach(input => {
            const key = input.id.replace('filter-', '');
            if (input.value) {
                this.filters[key] = input.value;
            }
        });

        // Show loading
        const resultsContainer = document.getElementById('search-results');
        resultsContainer.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p>Searching...</p></div>';

        try {
            // Send search request
            const params = new URLSearchParams(this.filters);
            const response = await fetch(`api/advanced_search.php?${params}`);
            const data = await response.json();

            this.results = data.results || [];
            this.displayResults();
        } catch (error) {
            resultsContainer.innerHTML = '<div class="alert alert-danger">Search failed. Please try again.</div>';
        }
    }

    /**
     * Display search results
     */
    displayResults() {
        const container = document.getElementById('search-results');
        const countBadge = document.getElementById('results-count');

        if (this.results.length === 0) {
            container.innerHTML = '<div class="alert alert-info"><i class="bi bi-search"></i> No results found</div>';
            countBadge.textContent = '0 results';
            return;
        }

        countBadge.textContent = `${this.results.length} result(s)`;

        const resultsHTML = this.results.map(item => this.renderResultItem(item)).join('');
        container.innerHTML = `<div class="row">${resultsHTML}</div>`;
    }

    /**
     * Render individual result item
     */
    renderResultItem(item) {
        const templates = {
            monks: `
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-person"></i> ${item.full_name}</h5>
                            <p class="mb-1"><strong>Blood Group:</strong> ${item.blood_group || 'N/A'}</p>
                            <p class="mb-1"><strong>Phone:</strong> ${item.phone || 'N/A'}</p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-${item.status === 'active' ? 'success' : 'secondary'}">${item.status}</span></p>
                            ${item.chronic_conditions ? `<p class="small text-danger"><strong>Chronic:</strong> ${item.chronic_conditions}</p>` : ''}
                            <a href="monk_management.php?view=${item.monk_id}" class="btn btn-sm btn-primary mt-2">View Details</a>
                        </div>
                    </div>
                </div>
            `,
            donations: `
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-cash-coin"></i> ${item.donor_name}</h5>
                            <p class="mb-1"><strong>Amount:</strong> <span class="text-success">Rs. ${parseFloat(item.amount).toLocaleString()}</span></p>
                            <p class="mb-1"><strong>Method:</strong> ${item.method}</p>
                            <p class="mb-1"><strong>Date:</strong> ${new Date(item.created_at).toLocaleDateString()}</p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-info">${item.status}</span></p>
                            <a href="donation_management.php?view=${item.donation_id}" class="btn btn-sm btn-primary mt-2">View Details</a>
                        </div>
                    </div>
                </div>
            `,
            appointments: `
                <div class="col-md-6 mb-3">
                    <div class="card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-calendar-check"></i> Appointment #${item.app_id}</h5>
                            <p class="mb-1"><strong>Monk:</strong> ${item.monk_name}</p>
                            <p class="mb-1"><strong>Doctor:</strong> ${item.doctor_name}</p>
                            <p class="mb-1"><strong>Date:</strong> ${item.app_date} ${item.app_time}</p>
                            <p class="mb-1"><strong>Status:</strong> <span class="badge bg-primary">${item.status}</span></p>
                            <a href="patient_appointments.php?view=${item.app_id}" class="btn btn-sm btn-primary mt-2">View Details</a>
                        </div>
                    </div>
                </div>
            `
        };

        return templates[this.searchType]?.replace(/\$\{item\.(\w+)\}/g, (_, key) => item[key] || 'N/A') || '';
    }

    /**
     * Reset all filters
     */
    resetFilters() {
        document.getElementById('search-query').value = '';
        document.querySelectorAll('[id^="filter-"]').forEach(input => {
            input.value = '';
        });
        this.filters = {};
        this.performSearch();
    }

    /**
     * Export results to CSV
     */
    exportResults() {
        if (this.results.length === 0) {
            alert('No results to export');
            return;
        }

        const csvContent = this.convertToCSV(this.results);
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `search_results_${this.searchType}_${new Date().toISOString().split('T')[0]}.csv`;
        link.click();
    }

    /**
     * Convert results to CSV
     */
    convertToCSV(data) {
        if (data.length === 0) return '';

        const headers = Object.keys(data[0]);
        const rows = data.map(row => headers.map(header => `"${row[header] || ''}"`).join(','));
        
        return [headers.join(','), ...rows].join('\n');
    }
}

// Initialize advanced search when DOM ready
let advancedSearch;
document.addEventListener('DOMContentLoaded', () => {
    const searchContainer = document.getElementById('advanced-search');
    if (searchContainer) {
        const searchType = searchContainer.dataset.type || 'monks';
        advancedSearch = new AdvancedSearch(searchType);
        advancedSearch.init();
    }
});
