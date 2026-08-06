@extends('admin.layouts.app')

@section('title', 'SaaS Plans')

@push('styles')
<style>
    .saas-hero { background: linear-gradient(135deg, #111827, #1e293b 58%, #4c1d95); color:#fff; border-radius:24px; padding:26px; overflow:hidden; position:relative; box-shadow:0 18px 45px rgba(76,29,149,.18); }
    .saas-hero::after { content:''; position:absolute; right:-70px; top:-80px; width:230px; height:230px; border-radius:50%; background:radial-gradient(circle, rgba(255,255,255,.18), transparent 65%); }
    .tier-card { background:#fff; border:1px solid var(--border); border-radius:24px; height:100%; box-shadow:0 10px 34px rgba(15,23,42,.06); overflow:hidden; position:relative; }
    .tier-card::before { content:''; position:absolute; left:0; right:0; top:0; height:6px; background:var(--tier-color, #ff6b2c); }
    .tier-card-body { padding:22px; }
    .tier-icon { width:54px; height:54px; border-radius:18px; display:flex; align-items:center; justify-content:center; background:var(--tier-soft, rgba(255,107,44,.12)); color:var(--tier-color, #ff6b2c); font-size:25px; }
    .tier-code { display:inline-flex; align-items:center; border-radius:999px; padding:5px 10px; font-size:11px; letter-spacing:.08em; text-transform:uppercase; font-weight:900; color:var(--tier-color, #ff6b2c); background:var(--tier-soft, rgba(255,107,44,.12)); border:1px solid var(--tier-border, rgba(255,107,44,.25)); }
    .price-pill { border:1px solid #e5e7eb; border-radius:16px; padding:12px; background:linear-gradient(180deg,#fff,#f8fafc); }
    .feature-chip { display:flex; align-items:center; justify-content:space-between; gap:10px; border:1px solid #e5e7eb; border-radius:14px; padding:9px 10px; background:#fff; font-size:12px; }
    .feature-chip .label { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#475569; font-weight:700; }
    .feature-chip .value { flex-shrink:0; border-radius:999px; padding:3px 8px; font-weight:800; font-size:11px; }
    .feature-on { background:#dcfce7; color:#166534; }
    .feature-off { background:#fee2e2; color:#991b1b; }
    .feature-number { background:#dbeafe; color:#1d4ed8; }
    .feature-text { background:#fef3c7; color:#92400e; }
    .card-feature-line { display:flex; align-items:flex-start; gap:9px; padding:9px 10px; border:1px solid #e5e7eb; border-radius:14px; background:#fff; font-size:13px; }
    .card-feature-line .feature-title { flex:1; min-width:0; font-weight:700; color:#334155; }
    .tooltip-dot { color:#f59e0b; cursor:help; flex-shrink:0; }
    .mini-muted { color:var(--text-secondary); font-size:12px; }
    .modal-feature-card { border:1px solid #e5e7eb; border-radius:18px; padding:14px; background:linear-gradient(180deg,#fff,#f8fafc); margin-bottom:12px; }
    .modal-feature-key { font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#64748b; font-weight:800; }
    .modal-xl-custom { max-width: 1120px; }
</style>
@endpush

@section('content')
<div class="saas-hero mb-4">
    <div class="position-relative" style="z-index:1">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">SaaS Plans</h3>
                <div style="opacity:.72;font-size:13px;">Manage Bronze, Silver and Gold names, pricing, backend limits, and card display features.</div>
            </div>
            <form action="{{ route('admin.saas-plans.seed-defaults') }}" method="POST" onsubmit="return confirm('Sync default Bronze/Silver/Gold features and prices? Custom plan names are preserved.');">
                @csrf
                <button class="btn btn-light"><i class="bi bi-arrow-repeat me-2"></i>Sync Defaults</button>
            </form>
        </div>
    </div>
</div>

@if($missingSchema)
    <div class="alert alert-warning rounded-4">
        <strong>Subscription tier tables are missing.</strong> Go to <a href="{{ route('admin.system-update.index') }}" class="alert-link">System Update</a> and click Update Now.
    </div>
@else
<div class="row g-4">
    @foreach($tiers as $tier)
        @php
            $tierCode = strtolower((string) $tier->code);
            $tierColor = \App\Services\SubscriptionFeatureService::tierColor($tierCode);
            $tierSoft = $tierColor . '18';
            $tierBorder = $tierColor . '44';
            $tierIcon = $tierCode === 'gold' ? 'bi-trophy' : ($tierCode === 'silver' ? 'bi-stars' : 'bi-shield-check');
            $visibleCardFeatures = $tier->cardFeatures->where('is_visible', true)->sortBy('sort_order')->values();
        @endphp
        <div class="col-xl-4">
            <div class="tier-card" style="--tier-color: {{ $tierColor }}; --tier-soft: {{ $tierSoft }}; --tier-border: {{ $tierBorder }};">
                <div class="tier-card-body">
                    <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="tier-icon"><i class="bi {{ $tierIcon }}"></i></div>
                            <div>
                                <div class="tier-code mb-1">{{ $tier->code }}</div>
                                <h4 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;">{{ $tier->name }}</h4>
                                <div class="d-flex gap-1 flex-wrap">
                                    @if($tier->badge_text)<span class="badge bg-primary">{{ $tier->badge_text }}</span>@endif
                                    @if($tier->is_coming_soon)<span class="badge bg-warning text-dark">Coming Soon</span>@endif
                                    <span class="badge {{ $tier->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $tier->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editTier{{ $tier->id }}"><i class="bi bi-pencil"></i></button>
                    </div>

                    <p class="text-muted small mb-3">{{ $tier->description }}</p>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="bi bi-card-checklist me-1"></i> Card Features</h6>
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#manageCardFeatures{{ $tier->id }}">
                            <i class="bi bi-plus-circle me-1"></i>Manage
                        </button>
                    </div>
                    <div class="d-grid gap-2 mb-4">
                        @forelse($visibleCardFeatures->take(7) as $cardFeature)
                            <div class="card-feature-line">
                                <i class="bi {{ $cardFeature->is_included ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' }}"></i>
                                <span class="feature-title">{{ $cardFeature->feature_label }}</span>
                                @if($cardFeature->tooltip_text)
                                    <i class="bi bi-exclamation-circle-fill tooltip-dot" title="{{ $cardFeature->tooltip_text }}"></i>
                                @endif
                            </div>
                        @empty
                            <div class="mini-muted">No card features added yet.</div>
                        @endforelse
                        @if($visibleCardFeatures->count() > 7)
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#manageCardFeatures{{ $tier->id }}">
                                View all {{ $visibleCardFeatures->count() }} card features
                            </button>
                        @endif
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="bi bi-sliders me-1"></i> System Limits</h6>
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#manageFeatures{{ $tier->id }}">
                            Manage
                        </button>
                    </div>
                    <div class="d-grid gap-2 mb-4">
                        @foreach($tier->features->take(5) as $feature)
                            @php
                                $value = $feature->castValue();
                                $labelValue = is_bool($value) ? ($value ? 'Yes' : 'No') : ($value === 'coming_soon' ? 'Coming Soon' : $value);
                                $valueClass = is_bool($value) ? ($value ? 'feature-on' : 'feature-off') : ($feature->value_type === 'number' ? 'feature-number' : 'feature-text');
                            @endphp
                            <div class="feature-chip">
                                <span class="label">{{ $feature->feature_label }}</span>
                                <span class="value {{ $valueClass }}">{{ $labelValue }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0"><i class="bi bi-currency-rupee me-1"></i> Pricing</h6>
                        <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#addPrice{{ $tier->id }}"><i class="bi bi-plus-circle me-1"></i>Add</button>
                    </div>
                    <div class="d-grid gap-2">
                        @forelse($tier->prices as $price)
                            <div class="price-pill">
                                <div class="d-flex justify-content-between align-items-center gap-2">
                                    <div>
                                        <strong>{{ ucfirst($price->billing_cycle) }}</strong>
                                        <span class="mini-muted"> • {{ $price->duration_months }} month{{ $price->duration_months == 1 ? '' : 's' }}</span>
                                        @if($price->discount_text)<div class="mini-muted text-success">{{ $price->discount_text }}</div>@endif
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold" style="color:{{ $tierColor }};">₹{{ number_format($price->price) }}</div>
                                        @if($price->strike_price)<small class="text-muted text-decoration-line-through">₹{{ number_format($price->strike_price) }}</small>@endif
                                    </div>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button class="btn btn-sm btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#editPrice{{ $price->id }}">Edit</button>
                                    <form action="{{ route('admin.saas-plans.prices.destroy', $price->id) }}" method="POST" onsubmit="return confirm('Delete this price option?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash3"></i></button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <div class="mini-muted">No pricing options yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="editTier{{ $tier->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius:20px;">
                    <form action="{{ route('admin.saas-plans.tiers.update', $tier->id) }}" method="POST">
                        @csrf @method('PUT')
                        <div class="modal-header"><h5 class="modal-title">Edit {{ $tier->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body row g-3">
                            <div class="col-md-6"><label class="form-label">Display Name</label><input class="form-control" name="name" value="{{ $tier->name }}" required></div>
                            <div class="col-md-6"><label class="form-label">Badge</label><input class="form-control" name="badge_text" value="{{ $tier->badge_text }}"></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3">{{ $tier->description }}</textarea></div>
                            <div class="col-md-4"><label class="form-label">Sort Order</label><input class="form-control" type="number" name="sort_order" value="{{ $tier->sort_order }}"></div>
                            <div class="col-md-4"><label class="form-label">Active</label><select class="form-select" name="is_active"><option value="1" @selected($tier->is_active)>Yes</option><option value="0" @selected(!$tier->is_active)>No</option></select></div>
                            <div class="col-md-4"><label class="form-label">Coming Soon</label><select class="form-select" name="is_coming_soon"><option value="1" @selected($tier->is_coming_soon)>Yes</option><option value="0" @selected(!$tier->is_coming_soon)>No</option></select></div>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Tier</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="manageCardFeatures{{ $tier->id }}" tabindex="-1">
            <div class="modal-dialog modal-xl modal-xl-custom modal-dialog-scrollable">
                <div class="modal-content" style="border-radius:22px;">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1">{{ $tier->name }} Card Features</h5>
                            <div class="mini-muted">These rows are shown on Gym Panel subscription cards. Tick/cross controls inclusion; exclamation icon appears only when tooltip text is added.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="modal-feature-card border-primary mb-4">
                            <form action="{{ route('admin.saas-plans.card-features.store', $tier->id) }}" method="POST">
                                @csrf
                                <div class="row g-2 align-items-end">
                                    <div class="col-lg-4">
                                        <label class="form-label small mb-1">Feature Name</label>
                                        <input class="form-control" name="feature_label" placeholder="eg. Free QR Sticker" required>
                                    </div>
                                    <div class="col-lg-2">
                                        <label class="form-label small mb-1">Tick / Cross</label>
                                        <select class="form-select" name="is_included">
                                            <option value="1">Tick - Included</option>
                                            <option value="0">Cross - Not Included</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-3">
                                        <label class="form-label small mb-1">Tooltip Text</label>
                                        <input class="form-control" name="tooltip_text" placeholder="eg. 3 stickers included">
                                    </div>
                                    <div class="col-lg-1">
                                        <label class="form-label small mb-1">Order</label>
                                        <input class="form-control" type="number" name="sort_order" value="0">
                                    </div>
                                    <div class="col-lg-1">
                                        <input type="hidden" name="is_visible" value="0">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="is_visible" value="1" checked>
                                            <label class="form-check-label small">Show</label>
                                        </div>
                                    </div>
                                    <div class="col-lg-1">
                                        <button class="btn btn-primary w-100"><i class="bi bi-plus"></i></button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        @forelse($tier->cardFeatures as $cardFeature)
                            <div class="modal-feature-card">
                                <form action="{{ route('admin.saas-plans.card-features.update', $cardFeature->id) }}" method="POST">
                                    @csrf @method('PUT')
                                    <div class="row g-2 align-items-end">
                                        <div class="col-lg-4">
                                            <label class="form-label small mb-1">Feature Name</label>
                                            <input class="form-control form-control-sm" name="feature_label" value="{{ $cardFeature->feature_label }}" required>
                                        </div>
                                        <div class="col-lg-2">
                                            <label class="form-label small mb-1">Tick / Cross</label>
                                            <select class="form-select form-select-sm" name="is_included">
                                                <option value="1" @selected($cardFeature->is_included)>Tick - Included</option>
                                                <option value="0" @selected(!$cardFeature->is_included)>Cross - Not Included</option>
                                            </select>
                                        </div>
                                        <div class="col-lg-3">
                                            <label class="form-label small mb-1">Tooltip Text</label>
                                            <input class="form-control form-control-sm" name="tooltip_text" value="{{ $cardFeature->tooltip_text }}" placeholder="Optional hover text">
                                        </div>
                                        <div class="col-lg-1">
                                            <label class="form-label small mb-1">Order</label>
                                            <input class="form-control form-control-sm" type="number" name="sort_order" value="{{ $cardFeature->sort_order }}">
                                        </div>
                                        <div class="col-lg-1">
                                            <input type="hidden" name="is_visible" value="0">
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="checkbox" name="is_visible" value="1" @checked($cardFeature->is_visible)>
                                                <label class="form-check-label small">Show</label>
                                            </div>
                                        </div>
                                        <div class="col-lg-1 d-flex gap-1">
                                            <button class="btn btn-sm btn-primary flex-fill" title="Save"><i class="bi bi-check2"></i></button>
                                        </div>
                                    </div>
                                </form>
                                <form action="{{ route('admin.saas-plans.card-features.destroy', $cardFeature->id) }}" method="POST" class="mt-2 text-end" onsubmit="return confirm('Delete this card feature?');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash3 me-1"></i>Delete</button>
                                </form>
                            </div>
                        @empty
                            <div class="alert alert-light border mb-0">No card features yet. Add the first feature above.</div>
                        @endforelse
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="manageFeatures{{ $tier->id }}" tabindex="-1">
            <div class="modal-dialog modal-xl modal-xl-custom modal-dialog-scrollable">
                <div class="modal-content" style="border-radius:22px;">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1">System Limits - {{ $tier->name }}</h5>
                            <div class="mini-muted">These values control backend access and limits. For card text, use Card Features.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            @foreach($tier->features as $feature)
                                <div class="col-lg-6">
                                    <div class="modal-feature-card">
                                        <form action="{{ route('admin.saas-plans.features.update', $feature->id) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="modal-feature-key mb-2">{{ $feature->feature_key }}</div>
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Feature Label</label>
                                                    <input class="form-control form-control-sm" name="feature_label" value="{{ $feature->feature_label }}" required>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Type</label>
                                                    <select class="form-select form-select-sm" name="value_type">
                                                        @foreach($featureTypes as $key => $label)
                                                            <option value="{{ $key }}" @selected($feature->value_type === $key)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Value</label>
                                                    <input class="form-control form-control-sm" name="value" value="{{ $feature->value }}" placeholder="1 / 0 / 150">
                                                </div>
                                                <div class="col-md-3">
                                                    <label class="form-label small mb-1">Order</label>
                                                    <input class="form-control form-control-sm" type="number" name="sort_order" value="{{ $feature->sort_order }}">
                                                </div>
                                                <div class="col-md-5">
                                                    <div class="form-check mt-3">
                                                        <input type="hidden" name="is_highlighted" value="0">
                                                        <input class="form-check-input" type="checkbox" name="is_highlighted" value="1" @checked($feature->is_highlighted)>
                                                        <label class="form-check-label small fw-semibold">Show in old system-preview list</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-md-end">
                                                    <button class="btn btn-sm btn-primary w-100"><i class="bi bi-check2 me-1"></i>Save Limit</button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="addPrice{{ $tier->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content" style="border-radius:20px;">
                    <form action="{{ route('admin.saas-plans.prices.store', $tier->id) }}" method="POST">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Add Price - {{ $tier->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body row g-3">@include('admin.saas-plans.partials.price-form', ['price' => null])</div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Price</button></div>
                    </form>
                </div>
            </div>
        </div>

        @foreach($tier->prices as $price)
            <div class="modal fade" id="editPrice{{ $price->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content" style="border-radius:20px;">
                        <form action="{{ route('admin.saas-plans.prices.update', $price->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="modal-header"><h5 class="modal-title">Edit Price - {{ $tier->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                            <div class="modal-body row g-3">@include('admin.saas-plans.partials.price-form', ['price' => $price])</div>
                            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save Price</button></div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endforeach
</div>
@endif
@endsection
