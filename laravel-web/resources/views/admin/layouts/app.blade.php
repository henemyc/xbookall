<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - GymXBook Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FIX #3: Changed Inter to Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --sidebar-width: 280px;
            --primary: #ff6b2c;
            --primary-light: #ff8a3d;
            --success: #16c784;
            --danger: #ff4d4f;
            --warning: #ffa726;
            --info: #3b9eff;
            --purple: #8b5cf6;
            --text: #1f2937;
            --text-secondary: #6b7280;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --border: #e5e7eb;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.6;
        }
        
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar .logo {
            padding: 24px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.08);
            flex-shrink: 0;
        }
        
        .sidebar .logo h4 {
            color: white;
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 24px;
            letter-spacing: -0.5px;
        }
        
        .sidebar .logo span { 
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar .logo .badge {
            font-size: 10px;
            padding: 4px 10px;
            border-radius: 6px;
            margin-top: 8px;
            display: inline-block;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(139, 92, 246, 0.1));
            color: #a78bfa;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .sidebar-nav {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            padding-bottom: 20px;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.1) transparent;
        }
        
        .sidebar-nav::-webkit-scrollbar { width: 4px; }
        .sidebar-nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }
        
        .sidebar .nav-section {
            color: rgba(255,255,255,0.3);
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            padding: 20px 24px 8px;
            font-weight: 600;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.6);
            padding: 5px 24px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            font-size: 13.5px;
            font-weight: 500;
            margin: 2px 12px;
            border-radius: 10px;
            text-decoration: none;
            position: relative;
        }
        
        .sidebar .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.08);
        }
        
        .sidebar .nav-link.active {
            color: white;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.2), rgba(139, 92, 246, 0.1));
        }
        
        .sidebar .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: linear-gradient(180deg, #a78bfa, #8b5cf6);
            border-radius: 0 4px 4px 0;
        }
        
        .sidebar .nav-link i { 
            margin-right: 14px; 
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px;
            min-height: 100vh;
        }
        
        .top-bar {
            background: var(--card-bg);
            padding: 18px 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
        }
        
        .top-bar h5 {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 20px;
        }
        
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #8b5cf6, #a78bfa);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            border-color: transparent;
        }
        
        .stat-card:hover::before { opacity: 1; }
        
        .stat-card .icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .stat-card .value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin: 12px 0 4px;
            letter-spacing: -0.5px;
        }
        
        .stat-card .label {
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
        }
        
        .table-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
        }
        
        .table-card h6 {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 16px;
        }
        
        .table { margin-bottom: 0; }
        
        .table thead th {
            background: var(--bg);
            border-bottom: 2px solid var(--border);
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            padding: 12px 16px;
        }
        
        .table tbody td {
            padding: 14px 16px;
            vertical-align: middle;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .table tbody tr { transition: background 0.2s; }
        .table tbody tr:hover { background: rgba(139, 92, 246, 0.02); }
        .table tbody tr:last-child td { border-bottom: none; }
        
        .btn {
            font-weight: 600;
            font-size: 13.5px;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border: none;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
            transform: translateY(-1px);
        }
        
        .btn-success { background: linear-gradient(135deg, #16c784, #0d9c5f); border: none; }
        .btn-danger { background: linear-gradient(135deg, #ff4d4f, #d4380d); border: none; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }
        .btn-lg { padding: 14px 28px; font-size: 15px; border-radius: 12px; }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            border: 1.5px solid var(--border);
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #8b5cf6;
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
        }
        
        .form-label { font-weight: 600; font-size: 13px; margin-bottom: 6px; }
        
        .badge { font-weight: 600; font-size: 11px; padding: 5px 10px; border-radius: 6px; }
        .badge.bg-primary { background: linear-gradient(135deg, #8b5cf6, #7c3aed) !important; }
        .badge.bg-success { background: linear-gradient(135deg, #16c784, #0d9c5f) !important; }
        .badge.bg-danger { background: linear-gradient(135deg, #ff4d4f, #d4380d) !important; }
        .badge.bg-warning { background: linear-gradient(135deg, #ffa726, #f57c00) !important; }
        
        .modal-content { border: none; border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.15); }
        .modal-header { border-bottom: 1px solid var(--border); padding: 20px 24px; }
        .modal-title { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 18px; }
        .modal-body { padding: 24px; }
        .modal-footer { border-top: 1px solid var(--border); padding: 16px 24px; }
        
        .delete-modal .modal-content { max-width: 400px; }
        .delete-modal .modal-body { text-align: center; padding: 32px 24px; }
        .delete-modal .delete-icon {
            width: 64px; height: 64px; border-radius: 50%;
            background: rgba(255, 77, 79, 0.1);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
        }
        .delete-modal .delete-icon i { font-size: 32px; color: var(--danger); }
        
        .toast-container { position: fixed; top: 24px; right: 24px; z-index: 9999; }
        .toast { min-width: 320px; border: none; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.15); overflow: hidden; }
        .toast-success { background: linear-gradient(135deg, #16c784, #0d9c5f); color: white; }
        .toast-error { background: linear-gradient(135deg, #ff4d4f, #d4380d); color: white; }
        .toast-body { padding: 16px 20px; font-weight: 500; font-size: 14px; }
        
        .avatar {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 18px; color: white; flex-shrink: 0;
        }
        
        .pagination .page-link {
            border-radius: 10px; margin: 0 3px; border: 1px solid var(--border);
            color: var(--text-secondary); font-weight: 500; font-size: 13px; padding: 8px 14px;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            border-color: #8b5cf6;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
        
        .search-input { position: relative; }
        .search-input .form-control { padding-left: 44px; }
        .search-input .search-icon {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%); color: var(--text-secondary);
        }
        
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); box-shadow: 20px 0 60px rgba(0,0,0,0.3); }
            .main-content { margin-left: 0; padding: 16px; }
            .stat-card .value { font-size: 24px; }
        }
        
        @media (max-width: 576px) {
            .toast-container { left: 16px; right: 16px; }
            .toast { min-width: auto; }
        }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 3px; }
        
        /* Form Validation Styles */
        .is-invalid {
            border-color: #ff4d4f !important;
            box-shadow: 0 0 0 2px rgba(255, 77, 79, 0.1) !important;
        }
        
        .field-error {
            color: #ff4d4f;
            font-size: 11px;
            margin-top: 4px;
            font-weight: 500;
        }
        
        button.loading {
            opacity: 0.8;
            cursor: not-allowed;
            position: relative;
        }
        
        button.loading::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 1.5s infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    </style>
    @stack('styles')
</head>
<body>
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <h4><span>Gym</span>XBook</h4>
            <div class="badge">SUPER ADMIN</div>
        </div>
        
        <div class="sidebar-nav">
            <ul class="nav flex-column mt-2">
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                        <i class="bi bi-grid-1x2"></i> Dashboard
                    </a>
                </li>
                
                <div class="nav-section">Management</div>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.gyms.*') ? 'active' : '' }}" href="{{ route('admin.gyms.index') }}">
                        <i class="bi bi-building"></i> Gyms
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.plans.*') ? 'active' : '' }}" href="{{ route('admin.plans.index') }}">
                        <i class="bi bi-award"></i> Old Plans
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.saas-plans.*') ? 'active' : '' }}" href="{{ route('admin.saas-plans.index') }}">
                        <i class="bi bi-layers"></i> SaaS Plans
                    </a>
                </li>
                
                <div class="nav-section">Finance</div>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.revenue.*') ? 'active' : '' }}" href="{{ route('admin.revenue.index') }}">
                        <i class="bi bi-bar-chart-line"></i> Revenue
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.payment-gateways.*') ? 'active' : '' }}" href="{{ route('admin.payment-gateways.index') }}">
                        <i class="bi bi-credit-card-2-front"></i> Payment Gateways
                    </a>
                </li>
                
                <div class="nav-section">Communication</div>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}" href="{{ route('admin.notifications.index') }}">
                        <i class="bi bi-bell"></i> Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.bugs.*') ? 'active' : '' }}" href="{{ route('admin.bugs.index') }}">
                        <i class="bi bi-bug"></i> Bug Reports
                    </a>
                </li>
                
                <div class="nav-section">System</div>
                @if(\Illuminate\Support\Facades\Route::has('admin.system-update.index'))
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.system-update.*') ? 'active' : '' }}" href="{{ route('admin.system-update.index') }}">
                        <i class="bi bi-database-gear"></i> System Update
                    </a>
                </li>
                @endif
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                </li>
                <!-- FIX #4: Logout with confirmation modal -->
                <li class="nav-item mt-4 mb-4">
                    <a href="#" class="nav-link" style="color: var(--danger);" onclick="event.preventDefault(); confirmLogout('{{ route('admin.logout') }}')">
                        <i class="bi bi-box-arrow-left"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <div id="sidebarOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999;" onclick="toggleSidebar()"></div>

    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-lg-none me-3 p-0" onclick="toggleSidebar()" style="font-size: 24px; color: var(--text);">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="mb-0">@yield('title', 'Dashboard')</h5>
            </div>
            <div class="d-flex align-items-center">
                <span class="d-none d-md-inline" style="font-size: 13px; color: var(--text-secondary);">
                    <i class="bi bi-shield-check me-1"></i> {{ auth()->user()->name ?? 'Admin' }}
                </span>
            </div>
        </div>

        @yield('content')
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal fade delete-modal" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body">
                    <div class="delete-icon"><i class="bi bi-trash3"></i></div>
                    <h5 class="mb-2">Are you sure?</h5>
                    <p class="text-muted mb-0" id="deleteConfirmMessage">This action cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" id="deleteConfirmBtn">
                        <i class="bi bi-trash3 me-2"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FIX #4: Logout Confirm Modal -->
    <div class="modal fade" id="logoutConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-body text-center" style="padding: 32px 24px;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(139, 92, 246, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="bi bi-box-arrow-left" style="font-size: 32px; color: #8b5cf6;"></i>
                    </div>
                    <h5 class="mb-2">Logout?</h5>
                    <p class="text-muted mb-0">Are you sure you want to logout?</p>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <form id="logoutForm" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-danger px-4">
                            <i class="bi bi-box-arrow-left me-2"></i> Logout
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Return to Admin Banner (shown when impersonating) -->
    @if(session('admin_user_id'))
    <div style="position: fixed; top: 0; left: 0; right: 0; background: linear-gradient(135deg, #8b5cf6, #7c3aed); color: white; padding: 10px 20px; text-align: center; z-index: 9999; font-size: 13px; font-weight: 600;">
        <i class="bi bi-shield-check me-2"></i>
        You are viewing as gym owner
        <a href="{{ route('admin.returnToAdmin') }}" style="color: white; text-decoration: underline; margin-left: 16px;">
            <i class="bi bi-arrow-left me-1"></i> Return to Admin Panel
        </a>
    </div>
    @endif

    <!-- Toast Container -->
    <div class="toast-container">
        @if(session('success'))
            <div class="toast toast-success show animate-fade-in" role="alert">
                <div class="toast-body d-flex align-items-center">
                    <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                    <div class="flex-grow-1">{{ session('success') }}</div>
                    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
        @if(session('error'))
            <div class="toast toast-error show animate-fade-in" role="alert">
                <div class="toast-body d-flex align-items-center">
                    <i class="bi bi-x-circle-fill me-2 fs-5"></i>
                    <div class="flex-grow-1">{{ session('error') }}</div>
                    <button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            sidebar.classList.toggle('active');
            overlay.style.display = sidebar.classList.contains('active') ? 'block' : 'none';
        }
        
        document.querySelectorAll('.sidebar .nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) toggleSidebar();
            });
        });
        
        document.querySelectorAll('.toast').forEach(el => {
            setTimeout(() => {
                el.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
                el.style.opacity = '0';
                el.style.transform = 'translateX(100%)';
                setTimeout(() => el.remove(), 400);
            }, 4000);
        });
        
        function confirmDelete(url, message) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'This action cannot be undone.';
            const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            document.getElementById('deleteConfirmBtn').onclick = function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = url;
                form.innerHTML = `<input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="_method" value="DELETE">`;
                document.body.appendChild(form);
                form.submit();
            };
            modal.show();
        }
        
        // FIX #4: Logout confirmation
        function confirmLogout(url) {
            document.getElementById('logoutForm').action = url;
            const modal = new bootstrap.Modal(document.getElementById('logoutConfirmModal'));
            modal.show();
        }

        // ═══════════════════════════════════════════════════════
        // GLOBAL: Live Form Validation + Double-Entry Prevention
        // ═══════════════════════════════════════════════════════
        document.addEventListener('DOMContentLoaded', function() {
            
            document.querySelectorAll('form').forEach(form => {
                if (form.id === 'logoutForm' || form.classList.contains('delete-form')) return;
                
                const submitBtn = form.querySelector('button[type="submit"]');
                if (!submitBtn) return;
                
                // Live validation
                form.querySelectorAll('input, select, textarea').forEach(input => {
                    input.addEventListener('blur', function() { validateField(this); });
                    input.addEventListener('input', function() { clearFieldError(this); });
                });
                
                // Form submission
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    form.querySelectorAll('input[required], select[required], textarea[required]').forEach(field => {
                        if (!validateField(field)) isValid = false;
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        const firstError = form.querySelector('.is-invalid');
                        if (firstError) firstError.focus();
                        return;
                    }
                    
                    if (submitBtn.disabled || submitBtn.classList.contains('loading')) {
                        e.preventDefault();
                        return;
                    }
                    
                    submitBtn.disabled = true;
                    submitBtn.classList.add('loading');
                    const originalHtml = submitBtn.innerHTML;
                    submitBtn.dataset.originalHtml = originalHtml;
                    
                    submitBtn.innerHTML = `
                        <span style="display: inline-flex; align-items: center; gap: 8px;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                <circle cx="12" cy="12" r="10" opacity="0.3"/>
                                <path d="M12 2a10 10 0 0 1 10 10">
                                    <animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="0.8s" repeatCount="indefinite"/>
                                </path>
                            </svg>
                            Processing...
                        </span>
                    `;
                    
                    setTimeout(() => {
                        if (submitBtn.disabled) {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('loading');
                            submitBtn.innerHTML = submitBtn.dataset.originalHtml || originalHtml;
                        }
                    }, 10000);
                });
            });
        });

        function validateField(field) {
            if (!field.name || field.type === 'hidden' || field.type === 'submit') return true;
            const value = field.value.trim();
            let isValid = true;
            let message = '';
            
            if (field.hasAttribute('required') && !value) {
                isValid = false;
                message = 'This field is required';
            }
            
            if (value && field.type === 'email') {
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                    isValid = false;
                    message = 'Enter a valid email';
                }
            }
            
            if (value && (field.name === 'phone' || field.name === 'phone_number')) {
                if (!/^[6-9]\d{9}$/.test(value)) {
                    isValid = false;
                    message = 'Enter valid 10-digit phone';
                }
            }
            
            if (value && field.type === 'number' && field.hasAttribute('min')) {
                if (parseFloat(value) < parseFloat(field.min)) {
                    isValid = false;
                    message = `Minimum value is ${field.min}`;
                }
            }
            
            if (!isValid) showFieldError(field, message);
            else clearFieldError(field);
            
            return isValid;
        }

        function showFieldError(field, message) {
            field.classList.add('is-invalid');
            field.style.borderColor = '#ff4d4f';
            const existing = field.parentNode.querySelector('.field-error');
            if (existing) existing.remove();
            const error = document.createElement('div');
            error.className = 'field-error';
            error.style.cssText = 'color: #ff4d4f; font-size: 11px; margin-top: 4px; font-weight: 500;';
            error.textContent = message;
            field.parentNode.appendChild(error);
        }

        function clearFieldError(field) {
            field.classList.remove('is-invalid');
            field.style.borderColor = '';
            const error = field.parentNode.querySelector('.field-error');
            if (error) error.remove();
        }
    </script>
    @stack('scripts')
</body>
</html>
