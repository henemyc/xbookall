<div class="table-responsive">
    <table class="table members-table align-middle mb-0">
        <thead>
            <tr>
                <th style="width: 34%; padding-left: 20px;">Member</th>
                <th style="width: 18%;">Phone</th>
                <th style="width: 20%;">Plan</th>
                <th style="width: 16%;">Expiry</th>
                <th style="width: 12%;">Status</th>
            </tr>
        </thead>
        <tbody id="membersTableBody">
            @forelse($members as $member)
            @php
                $isActive = $member->traineeDetails && 
                           $member->traineeDetails->membership_expiry_date && 
                           \Carbon\Carbon::parse($member->traineeDetails->membership_expiry_date)->isFuture();
                $expiryDate = $member->traineeDetails && $member->traineeDetails->membership_expiry_date 
                            ? \Carbon\Carbon::parse($member->traineeDetails->membership_expiry_date)->format('d M Y') 
                            : '-';
                $planTitle = ($member->traineeDetails && $member->traineeDetails->membership) 
                            ? $member->traineeDetails->membership->title 
                            : 'No Plan';
            @endphp

            <tr class="member-row" 
                data-name="{{ strtolower($member->name) }}" 
                data-phone="{{ $member->phone_number ?? '' }}"
                data-email="{{ strtolower($member->email ?? '') }}"
                data-status="{{ $isActive ? 'active' : 'expired' }}"
                onclick="window.location='{{ route('panel.members.show', $member->id) }}'">
                
                <td style="padding-left: 20px;">
                    <div class="d-flex align-items-center">
                        <div class="member-avatar me-3">
                            {{ strtoupper(substr($member->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="member-name">{{ $member->name }}</div>
                            <div class="text-muted" style="font-size:12.5px;">{{ $member->email }}</div>
                        </div>
                    </div>
                </td>
                
                <td>
                    <span class="text-dark fw-medium">{{ $member->phone_number ?? '—' }}</span>
                </td>
                
                <td>
                    <span class="badge px-3 py-1" style="background:#e0f2fe;color:#0369a1;font-weight:600;font-size:12px;">
                        {{ $planTitle }}
                    </span>
                </td>
                
                <td>
                    <span class="text-secondary" style="font-size:14px;">{{ $expiryDate }}</span>
                </td>
                
                <td>
                    @if($isActive)
                        <span class="status-badge status-active">Active</span>
                    @else
                        <span class="status-badge status-expired">Expired</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="text-center py-5">
                    <div class="text-muted">
                        <i class="bi bi-people fs-1 d-block mb-2"></i>
                        No members found
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($members->hasPages())
<div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-4 gap-2">
    <div class="text-muted small">
        Showing <strong>{{ $members->firstItem() }}</strong> to <strong>{{ $members->lastItem() }}</strong> of <strong>{{ $members->total() }}</strong> members
    </div>
    
    <div class="modern-pagination">
        {{ $members->links('pagination::bootstrap-5') }}
    </div>
</div>
@endif
