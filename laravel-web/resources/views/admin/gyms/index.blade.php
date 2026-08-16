@extends('admin.layouts.app')

@section('title', 'Gym Management')

@section('content')
<div class="table-card">
    <!-- Search & Filters -->
    <div class="row mb-4">
        <div class="col-md-6">
            <form action="{{ route('admin.gyms.index') }}" method="GET">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Search gyms..." value="{{ $search }}">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#createGymModal">
                <i class="bi bi-plus-lg"></i> Create Gym
            </button>
            <div class="btn-group">
                <a href="{{ route('admin.gyms.index') }}" class="btn btn-outline-secondary {{ $status === '' ? 'active' : '' }}">All</a>
                <a href="{{ route('admin.gyms.index', ['status' => 'active']) }}" class="btn btn-outline-success {{ $status === 'active' ? 'active' : '' }}">Active</a>
                <a href="{{ route('admin.gyms.index', ['status' => 'inactive']) }}" class="btn btn-outline-danger {{ $status === 'inactive' ? 'active' : '' }}">Inactive</a>
            </div>
        </div>
    </div>

    <!-- Gyms Table -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Gym Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Plan</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Last App Open</th>
                    <th>Joined</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($gyms as $gym)
                <tr>
                    <td>{{ $gym->id }}</td>
                    <td>
                        <strong>{{ $gym->business_name ?? $gym->name }}</strong>
                    </td>
                    <td>{{ $gym->email }}</td>
                    <td>{{ $gym->phone_number ?? '-' }}</td>
                    <td>
                        @if($gym->subscriptionTier)
                            @php
                                $tierColor = \App\Services\SubscriptionFeatureService::tierColor($gym->subscriptionTier->code);
                            @endphp
                            <span class="badge" style="background: {{ $tierColor }};">{{ $gym->subscriptionTier->name }}</span>
                            <small class="d-block text-muted">New SaaS</small>
                        @elseif($gym->subscriptionPlan)
                            <span class="badge bg-primary">{{ $gym->subscriptionPlan->title }}</span>
                            <small class="d-block text-muted">Old format</small>
                        @else
                            <span class="badge bg-secondary">No Plan</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $expiry = $gym->subscription_ends_at ?: $gym->subscription_expire_date;
                        @endphp
                        @if($expiry)
                            @php
                                $expiryDate = \Carbon\Carbon::parse($expiry);
                            @endphp
                            @if($expiryDate->copy()->endOfDay()->isPast())
                                <span class="text-danger">{{ $expiryDate->format('d M Y') }}</span>
                            @else
                                <span class="text-success">{{ $expiryDate->format('d M Y') }}</span>
                            @endif
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if($gym->is_active)
                            <span class="badge-status" style="background: rgba(22, 199, 132, 0.1); color: #16c784;">Active</span>
                        @else
                            <span class="badge-status" style="background: rgba(255, 77, 79, 0.1); color: #ff4d4f;">Inactive</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $lastAccessAt = $gym->last_app_opened_at ?: $gym->last_login_at;
                            $lastAccessSource = $gym->last_app_opened_at ? 'app' : ($gym->last_login_at ? 'web' : null);
                        @endphp
                        @if($lastAccessAt)
                            <div class="fw-semibold">{{ $lastAccessAt->diffForHumans() }}</div>
                            <small class="text-muted">
                                {{ $lastAccessAt->format('d M Y, h:i A') }}
                                @if($lastAccessSource) • {{ $lastAccessSource }} @endif
                                @if($gym->last_app_opened_at && $gym->last_app_version)
                                    • v{{ $gym->last_app_version }}
                                @endif
                            </small>
                        @else
                            <span class="text-muted">Never</span>
                        @endif
                    </td>
                    <td>{{ $gym->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('admin.gyms.show', $gym->id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="text-center py-4">No gyms found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center">
        {{ $gyms->links() }}
    </div>
</div>

<div class="modal fade" id="createGymModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" id="createGymForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-building-add me-2"></i>Create Gym Account</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small">Creates a Gym Owner account directly. WhatsApp OTP verification is intentionally not required for Super Admin creation.</div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Gym Name *</label><input class="form-control" name="business_name" required></div>
                    <div class="col-md-6"><label class="form-label">Owner Name *</label><input class="form-control" name="owner_name" required></div>
                    <div class="col-md-6"><label class="form-label">Phone *</label><input class="form-control" name="phone_number" inputmode="numeric" required></div>
                    <div class="col-md-6"><label class="form-label">Email (optional)</label><input class="form-control" type="email" name="email"></div>
                    <div class="col-md-6"><label class="form-label">Password *</label><input class="form-control" type="password" name="password" minlength="6" required></div>
                    <div class="col-md-6"><label class="form-label">Source</label><select class="form-select" name="acquisition_source"><option value="super_admin">Super Admin</option><option value="google_search">Google Search</option><option value="play_store">Google Play Store</option><option value="social_media">Instagram / Facebook</option><option value="referral">Referral</option><option value="other">Other</option></select></div>
                    <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="2"></textarea></div>
                    <div class="col-12"><label class="form-label">Source detail / referral (optional)</label><input class="form-control" name="acquisition_detail"></div>
                </div>
                <div class="text-danger small mt-3 d-none" id="createGymError"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="createGymSubmit" type="submit">Create Gym</button></div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('createGymForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    const form = event.currentTarget;
    const error = document.getElementById('createGymError');
    const button = document.getElementById('createGymSubmit');
    error.classList.add('d-none');
    button.disabled = true;
    button.textContent = 'Creating...';
    try {
        const response = await fetch('{{ route('admin.gyms.store') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': form.querySelector('[name=_token]').value, 'Accept': 'application/json' },
            body: new FormData(form)
        });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'Could not create gym account');
        window.location.href = data.redirect;
    } catch (e) {
        error.textContent = e.message;
        error.classList.remove('d-none');
        button.disabled = false;
        button.textContent = 'Create Gym';
    }
});
</script>
@endpush
@endsection
