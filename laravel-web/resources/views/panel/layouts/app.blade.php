<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - GymXBook</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
        
        /* Sidebar */
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
            overflow: hidden;
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
            background: linear-gradient(135deg, #ff8a3d, #ff6b2c);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .sidebar .gym-name {
            color: rgba(255,255,255,0.5);
            font-size: 11px;
            margin-top: 6px;
            font-weight: 500;
            text-transform: uppercase;
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
            background: linear-gradient(135deg, rgba(255, 107, 44, 0.2), rgba(244, 63, 28, 0.1));
        }
        
        .sidebar .nav-link.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: linear-gradient(180deg, #ff8a3d, #ff6b2c);
            border-radius: 0 4px 4px 0;
        }
        
        .sidebar .nav-link i { 
            margin-right: 14px; 
            font-size: 18px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 24px 28px;
            min-height: 100vh;
        }
        
        /* Fix modal z-index stacking - modals must appear above backdrop */
        .modal {
            z-index: 1055 !important;
        }
        .modal-backdrop {
            z-index: 1050 !important;
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
        
        /* Cards */
        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            border: 1px solid var(--border);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }
        
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
        
        /* Tables */
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
        .table tbody tr:hover { background: rgba(255, 107, 44, 0.02); }
        .table tbody tr:last-child td { border-bottom: none; }
        
        /* Buttons */
        .btn {
            font-weight: 600;
            font-size: 13.5px;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff8a3d, #ff6b2c, #f43f1c);
            border: none;
            box-shadow: 0 4px 12px rgba(255, 107, 44, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #f43f1c, #e55a1f, #d4380d);
            transform: translateY(-1px);
        }
        
        .btn-primary:disabled {
            background: #ccc;
            box-shadow: none;
            transform: none;
            cursor: not-allowed;
        }
        
        .btn-success { background: linear-gradient(135deg, #16c784, #0d9c5f); border: none; }
        .btn-danger { background: linear-gradient(135deg, #ff4d4f, #d4380d); border: none; }
        .btn-sm { padding: 6px 12px; font-size: 12px; border-radius: 8px; }
        
        /* Forms */
        .form-control, .form-select {
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 14px;
            border: 1.5px solid var(--border);
            transition: all 0.2s ease;
            background-color: white;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 107, 44, 0.1);
        }
        
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(255, 77, 79, 0.1);
        }
        
        .form-label { font-weight: 600; font-size: 13px; margin-bottom: 6px; }
        
        /* Badges */
        .badge { font-weight: 600; font-size: 11px; padding: 5px 10px; border-radius: 6px; }
        .badge.bg-primary { background: linear-gradient(135deg, #ff8a3d, #ff6b2c) !important; }
        .badge.bg-success { background: linear-gradient(135deg, #16c784, #0d9c5f) !important; }
        .badge.bg-danger { background: linear-gradient(135deg, #ff4d4f, #d4380d) !important; }
        .badge.bg-warning { background: linear-gradient(135deg, #ffa726, #f57c00) !important; }
        
        /* Modals */
        .modal-content { border: none; border-radius: 20px; box-shadow: 0 25px 50px rgba(0,0,0,0.15); }
        .modal-header { border-bottom: 1px solid var(--border); padding: 20px 24px; }
        .modal-title { font-family: 'Space Grotesk', sans-serif; font-weight: 700; font-size: 18px; }
        .modal-body { padding: 24px; }
        .modal-footer { border-top: 1px solid var(--border); padding: 16px 24px; }
        
        /* Toast */
        .toast-container {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .toast {
            min-width: 320px;
            max-width: 420px;
            border: none;
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            animation: slideInRight 0.3s ease-out;
        }
        
        .toast-success { background: linear-gradient(135deg, #16c784, #0d9c5f); color: white; }
        .toast-error { background: linear-gradient(135deg, #ff4d4f, #d4380d); color: white; }
        .toast-body { padding: 16px 20px; font-weight: 500; font-size: 14px; display: flex; align-items: center; }
        
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(100%); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        /* Avatar */
        .avatar {
            width: 48px; height: 48px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 18px; color: white; flex-shrink: 0;
        }
        
        /* Search */
        .search-input { position: relative; }
        .search-input .form-control { padding-left: 44px; }
        .search-input .search-icon {
            position: absolute; left: 16px; top: 50%;
            transform: translateY(-50%); color: var(--text-secondary);
        }
        
        /* Pagination */
        .pagination .page-link {
            border-radius: 10px; margin: 0 3px; border: 1px solid var(--border);
            color: var(--text-secondary); font-weight: 500; font-size: 13px; padding: 8px 14px;
        }
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #ff8a3d, #ff6b2c);
            border-color: var(--primary);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fadeIn 0.3s ease-out forwards; }
        
        /* Responsive */
        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); box-shadow: 20px 0 60px rgba(0,0,0,0.3); }
            .main-content { margin-left: 0; padding: 16px; }
        }
        
        /* Ensure modals always render properly regardless of parent stacking context */
        .modal.show {
            display: block !important;
        }
        
        /* Fix for Bootstrap 5 modal backdrop z-index conflict */
        body.modal-open {
            overflow: hidden;
        }
        
        @media (max-width: 576px) {
            .toast-container { left: 16px; right: 16px; }
            .toast { min-width: auto; }
        }
        
        @media print {
            .sidebar, .top-bar, .btn, .toast-container, .modal { display: none !important; }
            .main-content { margin: 0 !important; padding: 20px !important; }
        }
    </style>
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="logo">
            <h4><span>Gym</span>XBook</h4>
            <div class="gym-name">{{ auth()->user()->name ?? 'Gym Panel' }}</div>
        </div>
        
        <div class="sidebar-nav">
            @php
                $panelUser = auth()->user();
                $canPanel = function (...$permissions) use ($panelUser) {
                    if (!$panelUser) return false;
                    if (in_array($panelUser->type ?? '', ['admin', 'owner'])) return true;
                    if (($panelUser->type ?? '') !== 'staff') return false;
                    if (empty($permissions)) return true;
                    return $panelUser->hasAnyStaffPermission($permissions);
                };
                $gymOwnerId = in_array($panelUser->type ?? '', ['admin', 'owner']) ? (int) $panelUser->id : (int) ($panelUser->parent_id ?? 0);
                $planEnabled = fn($feature, $default = true) => \App\Services\SubscriptionFeatureService::enabled($gymOwnerId, $feature, $default);
            @endphp
            <ul class="nav flex-column mt-2">
                @if($canPanel('dashboard.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.dashboard') ? 'active' : '' }}" href="{{ route('panel.dashboard') }}"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>
                @endif
                
                <div class="nav-section">Management</div>
                @if($canPanel('members.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.members.*') ? 'active' : '' }}" href="{{ route('panel.members.index') }}"><i class="bi bi-people"></i> Members</a></li>
                @endif
                @if($canPanel('trainers.view') && $planEnabled('trainers_enabled'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.trainers.*') ? 'active' : '' }}" href="{{ route('panel.trainers.index') }}"><i class="bi bi-person-badge"></i> Trainers</a></li>
                @endif
                @if(in_array(auth()->user()->type ?? '', ['admin', 'owner']) && $planEnabled('staff_enabled'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.staff.*') ? 'active' : '' }}" href="{{ route('panel.staff.users.index') }}"><i class="bi bi-shield-lock"></i> Staff & Roles</a></li>
                @endif
                @if($canPanel('attendance.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.attendance.*') ? 'active' : '' }}" href="{{ route('panel.attendance.index') }}"><i class="bi bi-fingerprint"></i> Attendance</a></li>
                @endif
                @if($canPanel('plans.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.plans.*') ? 'active' : '' }}" href="{{ route('panel.plans.index') }}"><i class="bi bi-award"></i> Plans</a></li>
                @endif
                @if($canPanel('classes.view') && $planEnabled('classes_enabled'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.classes.*') ? 'active' : '' }}" href="{{ route('panel.classes.index') }}"><i class="bi bi-book"></i> Classes</a></li>
                @endif
                @if($canPanel('lockers.view') && $planEnabled('lockers_enabled'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.lockers.*') ? 'active' : '' }}" href="{{ route('panel.lockers.index') }}"><i class="bi bi-lock"></i> Lockers</a></li>
                @endif
                
                <div class="nav-section">Finance</div>
                @if($canPanel('invoices.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.invoices.*') ? 'active' : '' }}" href="{{ route('panel.invoices.index') }}"><i class="bi bi-receipt"></i> Invoices</a></li>
                @endif
                @if($canPanel('expenses.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.expenses.*') ? 'active' : '' }}" href="{{ route('panel.expenses.index') }}"><i class="bi bi-wallet2"></i> Expenses</a></li>
                @endif
                @if($canPanel('transactions.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.transactions.*') ? 'active' : '' }}" href="{{ route('panel.transactions.index') }}"><i class="bi bi-arrow-left-right"></i> Transactions</a></li>
                @endif
                @if($canPanel('reports.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.reports.*') ? 'active' : '' }}" href="{{ route('panel.reports.index') }}"><i class="bi bi-bar-chart-line"></i> Reports</a></li>
                @endif
                
                <div class="nav-section">Other</div>
                @if($canPanel('products.view') && $planEnabled('products_enabled'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.products.*') ? 'active' : '' }}" href="{{ route('panel.products.index') }}"><i class="bi bi-box-seam"></i> Products</a></li>
                @endif
                @if($canPanel('events.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.events.*') ? 'active' : '' }}" href="{{ route('panel.events.index') }}"><i class="bi bi-calendar-event"></i> Events</a></li>
                @endif
                @if($canPanel('notices.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.notices.*') ? 'active' : '' }}" href="{{ route('panel.notices.index') }}"><i class="bi bi-megaphone"></i> Notices</a></li>
                @endif
                @if($canPanel('attendance.qr'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.qr.*') ? 'active' : '' }}" href="{{ route('panel.qr.index') }}"><i class="bi bi-qr-code"></i> QR Code</a></li>
                @endif
                @if($canPanel('workouts.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.workouts.activities') ? 'active' : '' }}" href="{{ route('panel.workouts.activities') }}"><i class="bi bi-lightning"></i> Activities</a></li>
                @endif
                
                <div class="nav-section">Account</div>
                @if($canPanel('subscription.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.subscription.*') ? 'active' : '' }}" href="{{ route('panel.subscription.index') }}"><i class="bi bi-gem"></i> Subscription</a></li>
                @endif
                @if($canPanel('settings.view'))
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('panel.settings.*') ? 'active' : '' }}" href="{{ route('panel.settings.index') }}"><i class="bi bi-gear"></i> Settings</a></li>
                @endif
                
                <li class="nav-item mt-4 mb-4">
                    <a href="#" class="nav-link" style="color: var(--danger);" onclick="event.preventDefault(); confirmLogout('{{ route('panel.logout') }}')">
                        <i class="bi bi-box-arrow-left"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- Mobile Overlay -->
    <div id="sidebarOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999;" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
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
                    <i class="bi bi-person-circle me-1"></i> {{ auth()->user()->name ?? 'Owner' }}
                </span>
            </div>
        </div>

        @yield('content')
    </div>

    <!-- Delete Confirm Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-body text-center" style="padding: 32px 24px;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 77, 79, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="bi bi-trash3" style="font-size: 32px; color: var(--danger);"></i>
                    </div>
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

    <!-- Logout Confirm Modal -->
    <div class="modal fade" id="logoutConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="max-width: 400px;">
                <div class="modal-body text-center" style="padding: 32px 24px;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 107, 44, 0.1); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="bi bi-box-arrow-left" style="font-size: 32px; color: var(--primary);"></i>
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

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer" style="position:fixed;top:24px;right:24px;z-index:999999;display:flex;flex-direction:column;gap:10px;"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ═══════════════════════════════════════════════════════
        // ULTRA RELIABLE TOAST - DEFINED BEFORE ANYTHING ELSE
        // ═══════════════════════════════════════════════════════
        (function initToast() {
            function showToast(message, type = 'success') {
                // Always ensure container exists
                let container = document.getElementById('toastContainer');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'toastContainer';
                    container.style.cssText = 'position:fixed;top:16px;right:16px;z-index:999999999;display:flex;flex-direction:column;gap:8px;';
                    document.body.appendChild(container);
                }

                const isSuccess = type === 'success';
                const bg = isSuccess 
                    ? 'linear-gradient(135deg, #16c784, #0d9c5f)' 
                    : 'linear-gradient(135deg, #ff4d4f, #d4380d)';

                const toast = document.createElement('div');
                toast.style.cssText = `min-width:310px;max-width:400px;background:${bg};color:white;border-radius:12px;padding:14px 18px;box-shadow:0 15px 40px rgba(0,0,0,0.3);display:flex;align-items:center;gap:10px;font-size:14.5px;font-weight:500;`;

                toast.innerHTML = `
                    <span style="font-size:20px;opacity:.95;">${isSuccess ? '✓' : '✕'}</span>
                    <span style="flex:1;">${message}</span>
                    <span style="cursor:pointer;opacity:.8;font-size:20px;line-height:1;margin-left:6px;" onclick="this.closest('div').remove()">×</span>
                `;

                container.appendChild(toast);

                // Auto remove
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.style.transition = 'opacity .3s, transform .3s';
                        toast.style.opacity = '0';
                        toast.style.transform = 'translateX(30px)';
                        setTimeout(() => {
                            if (toast.parentNode) toast.parentNode.removeChild(toast);
                        }, 180);
                    }
                }, 5200);
            }

            // Expose everywhere
            window.showToast = showToast;
            window.gymxShowToast = showToast;
            
            // Also expose as global function for old code
            if (typeof window !== 'undefined') {
                window.showToast = showToast;
            }
        })();
        
        // Final fallback
        if (typeof window.showToast !== 'function') {
            window.showToast = function(msg) { 
                const d = document.createElement('div');
                d.style.cssText = 'position:fixed;top:20px;right:20px;background:#333;color:#fff;padding:12px 16px;border-radius:8px;z-index:9999999;';
                d.textContent = msg;
                document.body.appendChild(d);
                setTimeout(() => d.remove(), 4000);
            };
        }

        // ═══════════════════════════════════════════════════════
        // SIDEBAR
        // ═══════════════════════════════════════════════════════
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

        // ═══════════════════════════════════════════════════════
        // DELETE CONFIRMATION
        // ═══════════════════════════════════════════════════════
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

        function confirmLogout(url) {
            const modalEl = document.getElementById('logoutConfirmModal');
            const form = document.getElementById('logoutForm');
            form.action = url;

            const modal = new bootstrap.Modal(modalEl);
            modal.show();

            const logoutBtn = document.querySelector('#logoutConfirmModal .btn-danger');
            if (logoutBtn) {
                // Remove any previous handlers
                const freshBtn = logoutBtn.cloneNode(true);
                logoutBtn.parentNode.replaceChild(freshBtn, logoutBtn);

                freshBtn.onclick = async function() {
                    const originalText = freshBtn.innerHTML;
                    freshBtn.disabled = true;
                    freshBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Logging out...`;

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: new FormData(form)
                        });

                        const data = await res.json();

                        if (res.ok && data.success) {
                            // Redirect after successful logout
                            window.location.href = data.redirect || '/panel/login';
                        } else {
                            // Fallback to normal form submit
                            form.submit();
                        }
                    } catch (e) {
                        // Fallback
                        form.submit();
                    }
                };
            }
        }

        // ═══════════════════════════════════════════════════════
        // SESSION TOASTS (from server)
        // ═══════════════════════════════════════════════════════
        @if(session('success'))
            document.addEventListener('DOMContentLoaded', function() {
                showToast('{{ addslashes(session("success")) }}', 'success');
            });
        @endif
        
        @if(session('error'))
            document.addEventListener('DOMContentLoaded', function() {
                showToast('{{ addslashes(session("error")) }}', 'error');
            });
        @endif
    </script>
    @stack('scripts')
</body>
</html>
