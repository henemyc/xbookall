@extends('panel.layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
    $panelUser = auth()->user();
    $canPanel = function (...$permissions) use ($panelUser) {
        if (!$panelUser) return false;
        if (in_array($panelUser->type ?? '', ['admin', 'owner'])) return true;
        if (($panelUser->type ?? '') !== 'staff') return false;
        return $panelUser->hasAnyStaffPermission($permissions);
    };
@endphp
<!-- Welcome Banner -->
<div class="table-card mb-4" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: none; overflow: hidden; position: relative;">
    <div style="position: absolute; top: -30px; right: -30px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(255,107,44,0.15) 0%, transparent 70%);"></div>
    <div style="position: absolute; bottom: -50px; right: 100px; width: 150px; height: 150px; background: radial-gradient(circle, rgba(59,158,255,0.1) 0%, transparent 70%);"></div>
    <div class="row align-items-center position-relative">
        <div class="col-md-8">
            <p style="color: rgba(255,255,255,0.5); font-size: 13px; margin-bottom: 4px;">Welcome back,</p>
            <h3 style="color: white; font-family: 'Space Grotesk', sans-serif; font-weight: 700; margin-bottom: 8px;">
                {{ auth()->user()->name }} 👋
            </h3>
            <p style="color: rgba(255,255,255,0.6); font-size: 14px; margin-bottom: 0;">
                Here's what's happening with your gym today.
            </p>
        </div>
        <div class="col-md-4 text-end d-none d-md-block">
            <div style="font-size: 64px; opacity: 0.3;">🏋️</div>
        </div>
    </div>
</div>

<!-- Stats Grid -->
<div class="row g-4 mb-4">
    @if($canPanel('members.view'))
    <div class="col-md-3 col-6">
        <div class="stat-card animate-fade-in">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon" style="background: linear-gradient(135deg, rgba(255, 107, 44, 0.1), rgba(255, 107, 44, 0.05)); color: #ff6b2c;">
                    <i class="bi bi-people"></i>
                </div>
                <span style="font-size: 11px; color: var(--success); font-weight: 600;">
                    <i class="bi bi-arrow-up"></i> Active
                </span>
            </div>
            <div class="value">{{ $stats['members'] }}</div>
            <div class="label">Total Members</div>
        </div>
    </div>
    @endif
    
    @if($canPanel('members.view'))
    <div class="col-md-3 col-6">
        <div class="stat-card animate-fade-in" style="animation-delay: 0.1s;">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon" style="background: linear-gradient(135deg, rgba(22, 199, 132, 0.1), rgba(22, 199, 132, 0.05)); color: #16c784;">
                    <i class="bi bi-verified"></i>
                </div>
                <span style="font-size: 11px; color: var(--success); font-weight: 600;">
                    {{ $stats['members'] > 0 ? round(($stats['active_members'] / $stats['members']) * 100) : 0 }}%
                </span>
            </div>
            <div class="value">{{ $stats['active_members'] }}</div>
            <div class="label">Active Members</div>
        </div>
    </div>
    @endif
    
    @if($canPanel('attendance.view'))
    <div class="col-md-3 col-6">
        <div class="stat-card animate-fade-in" style="animation-delay: 0.2s;">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon" style="background: linear-gradient(135deg, rgba(59, 158, 255, 0.1), rgba(59, 158, 255, 0.05)); color: #3b9eff;">
                    <i class="bi bi-person-check"></i>
                </div>
                <span style="font-size: 11px; color: var(--info); font-weight: 600;">
                    Today
                </span>
            </div>
            <div class="value">{{ $stats['attendance_today'] }}</div>
            <div class="label">Attendance</div>
        </div>
    </div>
    @endif
    
    @if($canPanel('trainers.view'))
    <div class="col-md-3 col-6">
        <div class="stat-card animate-fade-in" style="animation-delay: 0.3s;">
            <div class="d-flex justify-content-between align-items-start">
                <div class="icon" style="background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.05)); color: #8b5cf6;">
                    <i class="bi bi-person-badge"></i>
                </div>
                <span style="font-size: 11px; color: var(--text-secondary); font-weight: 600;">
                    Staff
                </span>
            </div>
            <div class="value">{{ $stats['trainers'] }}</div>
            <div class="label">Trainers</div>
        </div>
    </div>
    @endif
</div>

<!-- Content Row -->
<div class="row g-4">
    <!-- Recent Members -->
    @if($canPanel('members.view'))
    <div class="col-lg-8">
        <div class="table-card animate-fade-in" style="animation-delay: 0.4s;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="mb-0">
                    <i class="bi bi-people me-2" style="color: var(--primary);"></i>
                    Recent Members
                </h6>
                <a href="{{ route('panel.members.index') }}" class="btn btn-sm btn-outline-primary">
                    View All <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentMembers as $member)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3" style="background: linear-gradient(135deg, #ff8a3d, #ff6b2c); font-size: 14px;">
                                        {{ strtoupper(substr($member->name, 0, 1)) }}
                                    </div>
                                    <strong>{{ $member->name }}</strong>
                                </div>
                            </td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $member->phone_number ?? '-' }}</td>
                            <td>
                                <span style="font-size: 12px; color: var(--text-secondary);">
                                    {{ $member->created_at->diffForHumans() }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center py-4">
                                <div style="color: var(--text-secondary);">
                                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                                    No members yet
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Today's Check-ins -->
    @if($canPanel('attendance.view'))
    <div class="col-lg-4">
        <div class="table-card animate-fade-in" style="animation-delay: 0.5s;">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h6 class="mb-0">
                    <i class="bi bi-clock-history me-2" style="color: var(--success);"></i>
                    Today's Check-ins
                </h6>
                <span class="badge bg-success">{{ $todayCheckins->count() }}</span>
            </div>
            
            @forelse($todayCheckins as $checkin)
            <div class="d-flex align-items-center {{ !$loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                <div class="avatar me-3" style="background: linear-gradient(135deg, #16c784, #0d9c5f); font-size: 14px; width: 40px; height: 40px; border-radius: 12px;">
                    {{ strtoupper(substr($checkin->user->name ?? 'U', 0, 1)) }}
                </div>
                <div class="flex-grow-1">
                    <div style="font-weight: 600; font-size: 13px;">{{ $checkin->user->name ?? 'Unknown' }}</div>
                    <div style="font-size: 11px; color: var(--text-secondary);">
                        <i class="bi bi-clock me-1"></i>
                        {{ \Carbon\Carbon::parse($checkin->checked_in_time)->format('h:i A') }}
                        @if($checkin->checked_out_time)
                            → {{ \Carbon\Carbon::parse($checkin->checked_out_time)->format('h:i A') }}
                        @endif
                    </div>
                </div>
                @if($checkin->checked_out_time)
                    <span class="badge bg-secondary" style="font-size: 10px;">Out</span>
                @else
                    <span class="badge bg-success" style="font-size: 10px;">In</span>
                @endif
            </div>
            @empty
            <div class="text-center py-4">
                <div style="color: var(--text-secondary);">
                    <i class="bi bi-calendar-x fs-1 d-block mb-2" style="opacity: 0.3;"></i>
                    <p class="mb-0">No check-ins today</p>
                </div>
            </div>
            @endforelse
        </div>
    </div>
    @endif
</div>

<!-- Quick Actions -->
@if($canPanel('members.create') || $canPanel('invoices.view') || $canPanel('attendance.view') || $canPanel('reports.view'))
<div class="table-card mt-4 animate-fade-in" style="animation-delay: 0.6s;">
    <h6 class="mb-4">
        <i class="bi bi-lightning me-2" style="color: var(--warning);"></i>
        Quick Actions
    </h6>
    
    <div class="row g-3">
        @if($canPanel('members.create'))
        <div class="col-6 col-md-3">
            <a href="{{ route('panel.members.create') }}" class="text-decoration-none">
                <div class="p-3 rounded text-center" style="background: rgba(255, 107, 44, 0.05); border: 1px solid rgba(255, 107, 44, 0.1); transition: all 0.2s;" onmouseover="this.style.background='rgba(255, 107, 44, 0.1)'" onmouseout="this.style.background='rgba(255, 107, 44, 0.05)'">
                    <i class="bi bi-person-plus fs-3" style="color: var(--primary);"></i>
                    <div class="mt-2" style="font-weight: 600; font-size: 13px; color: var(--text);">Add Member</div>
                </div>
            </a>
        </div>
        @endif
        @if($canPanel('invoices.view'))
        <div class="col-6 col-md-3">
            <a href="{{ route('panel.invoices.index') }}" class="text-decoration-none">
                <div class="p-3 rounded text-center" style="background: rgba(22, 199, 132, 0.05); border: 1px solid rgba(22, 199, 132, 0.1); transition: all 0.2s;" onmouseover="this.style.background='rgba(22, 199, 132, 0.1)'" onmouseout="this.style.background='rgba(22, 199, 132, 0.05)'">
                    <i class="bi bi-receipt fs-3" style="color: var(--success);"></i>
                    <div class="mt-2" style="font-weight: 600; font-size: 13px; color: var(--text);">Invoices</div>
                </div>
            </a>
        </div>
        @endif
        @if($canPanel('attendance.view'))
        <div class="col-6 col-md-3">
            <a href="{{ route('panel.attendance.index') }}" class="text-decoration-none">
                <div class="p-3 rounded text-center" style="background: rgba(59, 158, 255, 0.05); border: 1px solid rgba(59, 158, 255, 0.1); transition: all 0.2s;" onmouseover="this.style.background='rgba(59, 158, 255, 0.1)'" onmouseout="this.style.background='rgba(59, 158, 255, 0.05)'">
                    <i class="bi bi-fingerprint fs-3" style="color: var(--info);"></i>
                    <div class="mt-2" style="font-weight: 600; font-size: 13px; color: var(--text);">Attendance</div>
                </div>
            </a>
        </div>
        @endif
        @if($canPanel('reports.view'))
        <div class="col-6 col-md-3">
            <a href="{{ route('panel.reports.index') }}" class="text-decoration-none">
                <div class="p-3 rounded text-center" style="background: rgba(139, 92, 246, 0.05); border: 1px solid rgba(139, 92, 246, 0.1); transition: all 0.2s;" onmouseover="this.style.background='rgba(139, 92, 246, 0.1)'" onmouseout="this.style.background='rgba(139, 92, 246, 0.05)'">
                    <i class="bi bi-bar-chart-line fs-3" style="color: #8b5cf6;"></i>
                    <div class="mt-2" style="font-weight: 600; font-size: 13px; color: var(--text);">Reports</div>
                </div>
            </a>
        </div>
        @endif
    </div>
</div>
@endif
@endsection
