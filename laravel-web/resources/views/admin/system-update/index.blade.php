@extends('admin.layouts.app')

@section('title', 'System Update')

@push('styles')
<style>
    .update-hero {
        background: linear-gradient(135deg, #111827, #1e293b 58%, #4c1d95);
        color: #fff;
        border-radius: 24px;
        padding: 26px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 18px 45px rgba(76, 29, 149, .20);
    }
    .update-hero::after {
        content: '';
        position: absolute;
        right: -70px;
        top: -80px;
        width: 230px;
        height: 230px;
        border-radius: 50%;
        background: radial-gradient(circle, rgba(255,255,255,.18), transparent 65%);
    }
    .status-card {
        background: var(--card-bg);
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 18px;
        height: 100%;
        box-shadow: 0 1px 3px rgba(0,0,0,.04);
    }
    .status-icon {
        width: 46px;height:46px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:22px;
        background: rgba(139,92,246,.12); color: var(--purple);
    }
    .code-output {
        background: #0f172a;
        color: #e5e7eb;
        border-radius: 16px;
        padding: 16px;
        white-space: pre-wrap;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 12.5px;
        max-height: 360px;
        overflow: auto;
    }
    .migration-list { max-height: 340px; overflow: auto; }
</style>
@endpush

@section('content')
@php
    $pendingCount = count($status['pending_migrations']);
    $missingTablesCount = count($status['missing_tables']);
    $missingColumnsCount = collect($status['missing_columns'])->flatten()->count();
    $needsUpdate = $pendingCount > 0 || $missingTablesCount > 0 || $missingColumnsCount > 0;
@endphp

<div class="update-hero mb-4">
    <div class="position-relative" style="z-index:1">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div style="width:58px;height:58px;border-radius:18px;background:rgba(255,255,255,.14);display:flex;align-items:center;justify-content:center;font-size:28px;">
                    <i class="bi bi-database-gear"></i>
                </div>
                <div>
                    <h3 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">System Database Update</h3>
                    <div style="opacity:.72;font-size:13px;">Detect missing tables/columns and run Laravel migrations safely from Super Admin.</div>
                </div>
            </div>
            @if($needsUpdate)
                <form action="{{ route('admin.system-update.run') }}" method="POST" onsubmit="return confirm('Run database update now? Make sure you have a database backup before continuing.');">
                    @csrf
                    <button class="btn btn-light btn-lg"><i class="bi bi-cloud-arrow-up me-2"></i> Update Now</button>
                </form>
            @else
                <span class="badge bg-success px-3 py-2">Database Up To Date</span>
            @endif
        </div>
    </div>
</div>

@if(session('update_output'))
    <div class="table-card mb-4">
        <h6 class="mb-3"><i class="bi bi-terminal me-2"></i>Last Update Output</h6>
        <div class="code-output">{{ session('update_output') }}</div>
    </div>
@endif

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="status-card"><div class="d-flex align-items-center gap-3"><div class="status-icon"><i class="bi bi-database"></i></div><div><div class="text-muted small">Database</div><strong>{{ $status['database'] }}</strong></div></div></div></div>
    <div class="col-md-3"><div class="status-card"><div class="d-flex align-items-center gap-3"><div class="status-icon"><i class="bi bi-clock-history"></i></div><div><div class="text-muted small">Pending Migrations</div><h3 class="mb-0 {{ $pendingCount ? 'text-warning' : 'text-success' }}">{{ $pendingCount }}</h3></div></div></div></div>
    <div class="col-md-3"><div class="status-card"><div class="d-flex align-items-center gap-3"><div class="status-icon"><i class="bi bi-table"></i></div><div><div class="text-muted small">Missing Tables</div><h3 class="mb-0 {{ $missingTablesCount ? 'text-danger' : 'text-success' }}">{{ $missingTablesCount }}</h3></div></div></div></div>
    <div class="col-md-3"><div class="status-card"><div class="d-flex align-items-center gap-3"><div class="status-icon"><i class="bi bi-columns-gap"></i></div><div><div class="text-muted small">Missing Columns</div><h3 class="mb-0 {{ $missingColumnsCount ? 'text-danger' : 'text-success' }}">{{ $missingColumnsCount }}</h3></div></div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="table-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Pending Migrations</h6>
                <span class="badge {{ $pendingCount ? 'bg-warning' : 'bg-success' }}">{{ $pendingCount }}</span>
            </div>
            <div class="migration-list">
                @forelse($status['pending_migrations'] as $migration)
                    <div class="py-2 border-bottom"><code>{{ $migration }}</code></div>
                @empty
                    <div class="text-muted py-3">No pending migrations.</div>
                @endforelse
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="table-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Missing Schema</h6>
                <span class="badge {{ ($missingTablesCount || $missingColumnsCount) ? 'bg-danger' : 'bg-success' }}">{{ $missingTablesCount + $missingColumnsCount }}</span>
            </div>
            <div class="migration-list">
                @forelse($status['missing_tables'] as $table => $migrations)
                    <div class="py-2 border-bottom">
                        <strong class="text-danger">Missing table:</strong> <code>{{ $table }}</code>
                        <div class="text-muted small">From: {{ implode(', ', $migrations) }}</div>
                    </div>
                @empty
                    <div class="text-muted py-2">No missing tables.</div>
                @endforelse

                @foreach($status['missing_columns'] as $table => $columns)
                    <div class="py-2 border-bottom">
                        <strong class="text-danger">Missing columns in {{ $table }}:</strong>
                        <div class="small mt-1">
                            @foreach($columns as $column)
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1 mb-1">{{ $column }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<div class="table-card mt-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div>
            <h6 class="mb-1"><i class="bi bi-shield-check me-2"></i>Update Checklist</h6>
            <small class="text-muted">Use this after copying new Laravel files to live server.</small>
        </div>
        @if($needsUpdate)
            <form action="{{ route('admin.system-update.run') }}" method="POST" onsubmit="return confirm('Run database update now?');">
                @csrf
                <button class="btn btn-primary"><i class="bi bi-cloud-arrow-up me-2"></i>Run Update Now</button>
            </form>
        @endif
    </div>
    <ol class="mb-0 text-muted">
        <li>Take database backup from cPanel/phpMyAdmin before running production update.</li>
        <li>Copy all new Laravel files and migrations to server.</li>
        <li>Open this page and click <strong>Update Now</strong>.</li>
        <li>After update, test the affected page again.</li>
    </ol>
    <div class="mt-3 small text-muted">Checked at: {{ $status['checked_at'] }} • Migrations table: {{ $status['migrations_table_exists'] ? 'YES' : 'NO' }}</div>
</div>
@endsection
