/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Dynamic Navbar & Global Layout Initializer
 */

document.addEventListener('DOMContentLoaded', async function() {
    await renderNavbar();
    renderFooter();
    updateEmergencyCounter();
});

async function renderNavbar() {
    const navMount = document.getElementById('navbar-mount');
    if (!navMount) return;

    const user = await getCurrentUser();
    const currentPage = window.location.pathname.split('/').pop() || 'index.html';

    let roleNavItems = '';
    let authNavItems = '';

    if (user) {
        if (user.role === 'ADMIN') {
            roleNavItems = `
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'admin_dashboard.html' ? 'active fw-bold' : ''}" href="admin_dashboard.html">
                        <i class="bi bi-speedometer2 me-1"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'admin_inventory.html' ? 'active fw-bold' : ''}" href="admin_inventory.html">
                        <i class="bi bi-box-seam me-1"></i> Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'admin_requests.html' ? 'active fw-bold' : ''}" href="admin_requests.html">
                        <i class="bi bi-clipboard-pulse me-1"></i> Requests
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'admin_donations.html' ? 'active fw-bold' : ''}" href="admin_donations.html">
                        <i class="bi bi-droplet me-1"></i> Intake Session
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'admin_donors.html' ? 'active fw-bold' : ''}" href="admin_donors.html">
                        <i class="bi bi-people me-1"></i> Donors
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-primary fw-semibold ${currentPage === 'admin_reports.html' ? 'active fw-bold text-decoration-underline' : ''}" href="admin_reports.html">
                        <i class="bi bi-database-check me-1"></i> DBMS Queries Demo
                    </a>
                </li>
            `;
        } else if (user.role === 'DONOR') {
            roleNavItems = `
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'donor_dashboard.html' ? 'active fw-bold' : ''}" href="donor_dashboard.html">
                        <i class="bi bi-person-badge me-1"></i> Donor Portal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'search.html' ? 'active fw-bold' : ''}" href="search.html">
                        <i class="bi bi-search me-1"></i> Smart Match
                    </a>
                </li>
            `;
        } else if (user.role === 'REQUESTER') {
            roleNavItems = `
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'requester_dashboard.html' ? 'active fw-bold' : ''}" href="requester_dashboard.html">
                        <i class="bi bi-hospital me-1"></i> Requisitions Portal
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'new_request.html' ? 'active fw-bold' : ''}" href="new_request.html">
                        <i class="bi bi-plus-circle me-1"></i> New Request
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link ${currentPage === 'search.html' ? 'active fw-bold' : ''}" href="search.html">
                        <i class="bi bi-search me-1"></i> Smart Match
                    </a>
                </li>
            `;
        }

        const roleBadgeClass = user.role === 'ADMIN' ? 'bg-danger' : 
                               user.role === 'DONOR' ? 'bg-success' : 'bg-primary';

        authNavItems = `
            <div class="dropdown">
                <button class="btn btn-outline-dark dropdown-toggle btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="badge ${roleBadgeClass}">${user.role}</span>
                    <span class="fw-semibold">${user.full_name}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li><h6 class="dropdown-header">${user.email}</h6></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="javascript:void(0)" onclick="logout()">
                            <i class="bi bi-box-arrow-right me-2"></i> Sign Out
                        </a>
                    </li>
                </ul>
            </div>
        `;
    } else {
        authNavItems = `
            <a href="login.html" class="btn btn-outline-danger btn-sm fw-semibold">Sign In</a>
            <a href="register.html" class="btn btn-danger btn-sm fw-semibold">Register</a>
        `;
    }

    navMount.innerHTML = `
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm sticky-top">
            <div class="container-fluid px-lg-4">
                <a class="navbar-brand d-flex align-items-center gap-2" href="index.html">
                    <i class="bi bi-heart-pulse-fill brand-icon"></i>
                    <div>
                        <span class="text-danger fw-bold fs-4">Life</span><span class="fw-bold fs-4 text-dark">Link</span>
                        <div style="font-size: 0.65rem; line-height: 1; color: #64748B;">DBMS LAB &bull; CSE 3522</div>
                    </div>
                </a>

                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="mainNavbar">
                    <ul class="navbar-nav me-auto mb-2 mb-lg-0 align-items-lg-center">
                        <li class="nav-item">
                            <a class="nav-link ${currentPage === 'index.html' ? 'active fw-bold' : ''}" href="index.html">
                                <i class="bi bi-house-door me-1"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ${currentPage === 'search.html' ? 'active fw-bold' : ''}" href="search.html">
                                <i class="bi bi-search me-1"></i> Smart Match
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link ${currentPage === 'emergency.html' ? 'active fw-bold' : ''}" href="emergency.html">
                                <i class="bi bi-exclamation-triangle-fill text-danger me-1"></i> Emergency
                                <span id="emergency-counter-badge" class="badge rounded-pill bg-danger d-none ms-1 pulsing-badge">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="javascript:void(0)" onclick="promptTrackBag()">
                                <i class="bi bi-qr-code me-1"></i> Track Bag
                            </a>
                        </li>
                        ${roleNavItems}
                    </ul>

                    <div class="d-flex align-items-center gap-2 mt-3 mt-lg-0">
                        ${authNavItems}
                    </div>
                </div>
            </div>
        </nav>
    `;
}

async function updateEmergencyCounter() {
    try {
        const res = await apiRequest('emergency.php');
        if (res.success && res.data && res.data.length > 0) {
            const badge = document.getElementById('emergency-counter-badge');
            if (badge) {
                badge.innerText = res.data.length;
                badge.classList.remove('d-none');
            }
        }
    } catch (e) {
        // Silently ignore if guest or error
    }
}

function renderFooter() {
    const footerMount = document.getElementById('footer-mount');
    if (!footerMount) return;

    footerMount.innerHTML = `
        <footer class="mt-auto py-4 bg-white border-top">
            <div class="container text-center text-md-start">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="d-flex align-items-center gap-2 justify-content-center justify-content-md-start mb-1">
                            <i class="bi bi-heart-pulse-fill text-danger fs-5"></i>
                            <span class="fw-bold fs-5"><span class="text-danger">Life</span>Link</span>
                            <span class="badge bg-secondary">v2.0 PHP/HTML</span>
                        </div>
                        <p class="text-muted small mb-0">
                            Database Management Systems Laboratory &bull; CSE 3522 &bull; 3NF Relational Architecture
                        </p>
                    </div>
                    <div class="col-md-6 text-center text-md-end">
                        <div class="d-flex gap-3 justify-content-center justify-content-md-end text-muted small">
                            <span><i class="bi bi-shield-check text-success me-1"></i>ACID Compliant</span>
                            <span><i class="bi bi-database me-1"></i>MySQL Views & Indexes</span>
                            <span><i class="bi bi-arrow-repeat text-primary me-1"></i>Chain of Custody</span>
                        </div>
                        <div class="text-muted small mt-1">
                            &copy; ${new Date().getFullYear()} LifeLink Blood Bank Management System. All rights reserved.
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    `;
}
