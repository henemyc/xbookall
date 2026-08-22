@extends('admin.layouts.app')

@section('title', 'SaaS Plans')

@push('styles')
<style>
    :root {
        --saas-accent: #ff6b2c;
        --saas-ink: #0f172a;
        --saas-muted: #64748b;
        --saas-line: #e2e8f0;
        --saas-bg-soft: #f8fafc;
    }
    /* ── Hero ─────────────────────────────────────────────── */
    .saas-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #7c3aed 130%);
        color: #fff; border-radius: 24px; padding: 30px 32px;
        position: relative; overflow: hidden;
        box-shadow: 0 24px 60px rgba(15,23,42,.22);
    }
    .saas-hero::before {
        content:''; position:absolute; right:-90px; top:-110px; width:300px; height:300px;
        border-radius:50%; background: radial-gradient(circle, rgba(255,107,44,.35), transparent 65%);
    }
    .saas-hero::after {
        content:''; position:absolute; right:140px; bottom:-140px; width:260px; height:260px;
        border-radius:50%; background: radial-gradient(circle, rgba(139,92,246,.28), transparent 65%);
    }
    .saas-hero > div { position: relative; z-index: 1; }
    .saas-stat {
        background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.14);
        border-radius: 16px; padding: 12px 18px; min-width: 120px;
        backdrop-filter: blur(4px);
    }
    .saas-stat .num { font-family:'Space Grotesk',sans-serif; font-size: 22px; font-weight: 700; line-height: 1; }
    .saas-stat .lbl { font-size: 11px; opacity:.65; letter-spacing:.04em; text-transform: uppercase; font-weight: 600; }

    /* ── Tier card ────────────────────────────────────────── */
    .tier-card {
        background:#fff; border:1px solid var(--saas-line); border-radius:22px;
        box-shadow:0 8px 30px rgba(15,23,42,.05); overflow:hidden; position:relative;
        display:flex; flex-direction:column; height:100%;
        transition: transform .18s ease, box-shadow .18s ease;
    }
    .tier-card:hover { transform: translateY(-3px); box-shadow:0 18px 44px rgba(15,23,42,.10); }
    .tier-card::before { content:''; position:absolute; left:0; right:0; top:0; height:5px; background: var(--tier-color, #ff6b2c); }
    .tier-card-body { padding: 24px; display:flex; flex-direction:column; flex:1; }
    .tier-icon { width:52px; height:52px; border-radius:16px; display:flex; align-items:center; justify-content:center; background: var(--tier-soft, rgba(255,107,44,.12)); color: var(--tier-color, #ff6b2c); font-size:24px; flex-shrink:0; }
    .tier-code {
        display:inline-flex; align-items:center; border-radius:999px; padding:4px 10px;
        font-size:10.5px; letter-spacing:.1em; text-transform:uppercase; font-weight:800;
        color:var(--tier-color,#ff6b2c); background:var(--tier-soft, rgba(255,107,44,.12));
        border:1px solid var(--tier-border, rgba(255,107,44,.25));
    }
    .section-head { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:10px; }
    .section-label { font-size:11px; letter-spacing:.1em; text-transform:uppercase; font-weight:800; color:var(--saas-muted); }
    .section-label i { margin-right:5px; }

    /* card-feature checklist (in tier card) */
    .cf-list { display:grid; gap:8px; max-height: 230px; overflow:auto; padding-right:2px; }
    .cf-line {
        display:flex; align-items:center; gap:9px; padding:8px 12px;
        border:1px solid var(--saas-line); border-radius:12px; background:var(--saas-bg-soft); font-size:12.5px;
    }
    .cf-line .t { flex:1; min-width:0; font-weight:600; color:#334155; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .cf-line .ico-inc { color:#16a34a; font-size:15px; flex-shrink:0; }
    .cf-line .ico-exc { color:#cbd5e1; font-size:15px; flex-shrink:0; }
    .cf-line .tip { color:#f59e0b; cursor:help; font-size:13px; flex-shrink:0; }

    /* system limit chips */
    .limit-chip {
        display:flex; align-items:center; justify-content:space-between; gap:8px;
        border:1px solid var(--saas-line); border-radius:12px; padding:8px 12px; background:#fff; font-size:12px;
    }
    .limit-chip .label { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#475569; font-weight:600; }
    .val { flex-shrink:0; border-radius:999px; padding:3px 9px; font-weight:800; font-size:11px; }
    .val-on { background:#dcfce7; color:#166534; }
    .val-off { background:#fee2e2; color:#991b1b; }
    .val-num { background:#dbeafe; color:#1d4ed8; }
    .val-txt { background:#fef3c7; color:#92400e; }

    /* price rows */
    .price-row {
        display:flex; align-items:center; gap:12px; border:1px solid var(--saas-line);
        border-radius:14px; padding:10px 14px; background:var(--saas-bg-soft);
    }
    .price-row .amt { font-family:'Space Grotesk',sans-serif; font-weight:700; font-size:16px; color:var(--tier-color,#ff6b2c); }
    .mini-muted { color:var(--saas-muted); font-size:12px; }

    /* ── Modals ───────────────────────────────────────────── */
    .modal-xl-custom { max-width: 1180px; }
    .modal-sheet { border-radius:22px; border:none; }
    .modal-sheet .modal-header { border-bottom:1px solid var(--saas-line); padding:20px 24px; }
    .modal-sheet .modal-body { padding:24px; }
    .modal-sheet .modal-footer { border-top:1px solid var(--saas-line); padding:16px 24px; }
    .editor-add {
        border:1.5px dashed #c7d2fe; border-radius:16px; padding:18px;
        background:linear-gradient(180deg,#eef2ff,#ffffff);
    }
    .editor-row { border:1px solid var(--saas-line); border-radius:14px; padding:14px; background:#fff; margin-bottom:10px; }
    .editor-row:hover { border-color:#cbd5e1; }

    /* toggle switch for tick/cross */
    .switch { position:relative; display:inline-block; width:46px; height:26px; flex-shrink:0; }
    .switch input { opacity:0; width:0; height:0; }
    .switch .track {
        position:absolute; cursor:pointer; inset:0; background:#e2e8f0; border-radius:999px; transition:.2s;
    }
    .switch .track::before {
        content:""; position:absolute; height:20px; width:20px; left:3px; top:3px;
        background:#fff; border-radius:50%; transition:.2s; box-shadow:0 1px 3px rgba(0,0,0,.25);
    }
    .switch input:checked + .track { background:#16a34a; }
    .switch input:checked + .track::before { transform: translateX(20px); }

    /* live app preview */
    .app-preview {
        background:#0f172a; border-radius:18px; padding:18px; color:#fff;
        position:relative; overflow:hidden;
    }
    .app-preview::before {
        content:''; position:absolute; inset:0; border-radius:18px;
        background: radial-gradient(circle at 85% 0%, rgba(255,107,44,.25), transparent 55%);
    }
    .preview-line { display:flex; align-items:center; gap:8px; padding:6px 0; font-size:13px; position:relative; z-index:1; }
    .preview-line .p-ok { color:#34d399; }
    .preview-line .p-no { color:#64748b; }
    .preview-line .p-tip { color:#f59e0b; font-size:12px; }

    /* Sticky app-preview inside the scrollable modal body */
    .cf-sticky-preview { position: sticky; top: 0; }
</style>
@endpush

@section('content')
@php
    $tierCount = $tiers->count();
    $cardFeatureCount = $cardFeatureCount ?? 0;
    $limitCount = $limitCount ?? 0;
@endphp

{{-- ─── Hero ──────────────────────────────────────────────── --}}
<div class="saas-hero mb-4">
    <div>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="mb-1" style="font-family:'Space Grotesk';font-weight:800;font-size:26px;">SaaS Plans</h3>
                <div style="opacity:.72;font-size:13.5px;">Control what every plan shows on the app, its backend limits, and pricing.</div>
            </div>
            <form action="{{ route('admin.saas-plans.seed-defaults') }}" method="POST" onsubmit="return cfFormConfirm(this, 'Sync default Bronze/Silver/Gold features and prices? Custom plan names are preserved.');">
                @csrf
                <button class="btn btn-light fw-semibold"><i class="bi bi-arrow-repeat me-2"></i>Sync Defaults</button>
            </form>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-4">
            <div class="saas-stat"><div class="num">{{ $tierCount }}</div><div class="lbl mt-1">Plans</div></div>
            <div class="saas-stat"><div class="num">{{ $cardFeatureCount }}</div><div class="lbl mt-1">Card features</div></div>
            <div class="saas-stat"><div class="num">{{ $limitCount }}</div><div class="lbl mt-1">System limits</div></div>
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
            $tierSoft = $tierColor . '1a';
            $tierBorder = $tierColor . '44';
            $tierIcon = $tierCode === 'gold' ? 'bi-trophy' : ($tierCode === 'silver' ? 'bi-stars' : 'bi-shield-check');
        @endphp
        <div class="col-xl-4 col-md-6">
            <div class="tier-card" style="--tier-color: {{ $tierColor }}; --tier-soft: {{ $tierSoft }}; --tier-border: {{ $tierBorder }};">
                <div class="tier-card-body">

                    {{-- header --}}
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
                        <button class="btn btn-sm btn-outline-secondary rounded-3" data-bs-toggle="modal" data-bs-target="#editTier{{ $tier->id }}" title="Edit plan"><i class="bi bi-pencil"></i></button>
                    </div>

                    @if($tier->description)
                        <p class="text-muted small mb-4">{{ $tier->description }}</p>
                    @endif

                    {{-- Card features — the app-facing points --}}
                    @if($cardFeaturesAvailable)
                        <div class="section-head">
                            <span class="section-label"><i class="bi bi-card-checklist"></i>App Features</span>
                            <button class="btn btn-sm btn-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#manageCardFeatures{{ $tier->id }}">
                                <i class="bi bi-plus-lg me-1"></i>Manage
                            </button>
                        </div>
                        <div class="cf-list mb-4" id="cfCard{{ $tier->id }}"></div>
                    @else
                        <div class="mb-4 p-3 rounded-3" style="background:#fff7ed;border:1px solid #fed7aa;">
                            <span style="font-size:12px;color:#9a3412;"><i class="bi bi-exclamation-triangle me-1"></i>App features unavailable.</span>
                        </div>
                    @endif

                    {{-- System limits --}}
                    <div class="section-head">
                        <span class="section-label"><i class="bi bi-sliders"></i>System Limits</span>
                        <button class="btn btn-sm btn-outline-secondary fw-semibold" data-bs-toggle="modal" data-bs-target="#manageFeatures{{ $tier->id }}">Manage</button>
                    </div>
                    <div class="d-grid gap-2 mb-4">
                        @foreach(collect($tier->features ?? [])->take(5) as $feature)
                            @php
                                $value = $feature->castValue();
                                $labelValue = is_bool($value) ? ($value ? 'Yes' : 'No') : ($value === 'coming_soon' ? 'Coming Soon' : $value);
                                $valueClass = is_bool($value) ? ($value ? 'val-on' : 'val-off') : ($feature->value_type === 'number' ? 'val-num' : 'val-txt');
                            @endphp
                            <div class="limit-chip">
                                <span class="label">{{ $feature->feature_label }}</span>
                                <span class="val {{ $valueClass }}">{{ $labelValue }}</span>
                            </div>
                        @endforeach
                        @if(collect($tier->features ?? [])->count() > 5)
                            <div class="mini-muted text-center">+{{ collect($tier->features ?? [])->count() - 5 }} more limits</div>
                        @endif
                    </div>

                    {{-- Pricing --}}
                    <div class="section-head">
                        <span class="section-label"><i class="bi bi-currency-rupee"></i>Pricing</span>
                        <button class="btn btn-sm btn-outline-success fw-semibold" data-bs-toggle="modal" data-bs-target="#addPrice{{ $tier->id }}"><i class="bi bi-plus-lg me-1"></i>Add</button>
                    </div>
                    <div class="d-grid gap-2 mt-auto">
                        @forelse(collect($tier->prices ?? []) as $price)
                            <div class="price-row">
                                <div class="flex-grow-1 min-width-0">
                                    <div class="fw-semibold small">{{ ucfirst($price->billing_cycle) }} <span class="mini-muted">· {{ $price->duration_months }} mo</span></div>
                                    @if($price->discount_text)<div class="text-success" style="font-size:11px;font-weight:600;">{{ $price->discount_text }}</div>@endif
                                </div>
                                <div class="amt">₹{{ number_format($price->price) }}</div>
                                <button class="btn btn-sm btn-outline-secondary px-2" data-bs-toggle="modal" data-bs-target="#editPrice{{ $price->id }}" title="Edit"><i class="bi bi-pencil"></i></button>
                                <form action="{{ route('admin.saas-plans.prices.destroy', $price->id) }}" method="POST" onsubmit="return cfFormConfirm(this, 'This price option will be permanently removed.');">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger px-2" type="submit" title="Delete"><i class="bi bi-trash3"></i></button>
                                </form>
                            </div>
                        @empty
                            <div class="mini-muted">No pricing options yet.</div>
                        @endforelse
                    </div>

                </div>
            </div>
        </div>

        {{-- ─── Edit tier modal ─────────────────────────────── --}}
        <div class="modal fade" id="editTier{{ $tier->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content modal-sheet">
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

        {{-- ─── Manage card features modal ───────────────────── --}}
        @if($cardFeaturesAvailable)
        <div class="modal fade" id="manageCardFeatures{{ $tier->id }}" tabindex="-1" data-tier="{{ $tier->id }}">
            <div class="modal-dialog modal-xl modal-xl-custom modal-dialog-scrollable">
                <div class="modal-content modal-sheet">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1">{{ $tier->name }} — App Features</h5>
                            <div class="mini-muted">Points shown on the subscription cards. <b>✓</b> included, <b>✗</b> not included. One save applies all changes.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-4">
                            {{-- editor column --}}
                            <div class="col-lg-7">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="section-label"><i class="bi bi-list-ul"></i> Feature points</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold cf-add-btn" data-tier="{{ $tier->id }}">
                                        <i class="bi bi-plus-lg me-1"></i>Add feature
                                    </button>
                                </div>
                                <div id="cfList{{ $tier->id }}"></div>
                            </div>

                            {{-- live preview column (sticky while scrolling) --}}
                            <div class="col-lg-5">
                                <div class="cf-sticky-preview">
                                    <div class="section-label mb-2"><i class="bi bi-phone"></i> App preview</div>
                                    <div class="app-preview">
                                        <div class="d-flex align-items-center gap-2 mb-3 position-relative" style="z-index:1">
                                            <span class="badge" style="background:{{ $tierSoft }};color:{{ $tierColor }};border:1px solid {{ $tierBorder }}">{{ strtoupper($tier->code) }}</span>
                                            <span class="fw-bold">{{ $tier->name }}</span>
                                        </div>
                                        <div id="cfPreview{{ $tier->id }}"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <span class="me-auto cf-dirty text-muted small" style="display:none;"><i class="bi bi-circle-fill me-1" style="font-size:7px;color:#f59e0b"></i>Unsaved changes</span>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary fw-semibold cf-save-all" data-tier="{{ $tier->id }}">
                            <i class="bi bi-check2 me-1"></i>Save changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- ─── Manage system limits modal ───────────────────── --}}
        <div class="modal fade" id="manageFeatures{{ $tier->id }}" tabindex="-1">
            <div class="modal-dialog modal-xl modal-xl-custom modal-dialog-scrollable">
                <div class="modal-content modal-sheet">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-1">{{ $tier->name }} — System Limits</h5>
                            <div class="mini-muted">These values control backend access and enforced limits. For app card text, use App Features.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            @foreach(collect($tier->features ?? []) as $feature)
                                <div class="col-lg-6">
                                    <div class="editor-row mb-0">
                                        <form action="{{ route('admin.saas-plans.features.update', $feature->id) }}" method="POST">
                                            @csrf @method('PUT')
                                            <div class="d-flex align-items-center gap-2 mb-2">
                                                <code style="font-size:11px;font-weight:700;color:#6366f1;background:#eef2ff;border-radius:6px;padding:2px 7px;">{{ $feature->feature_key }}</code>
                                            </div>
                                            <div class="row g-2 align-items-end">
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Label</label>
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
                                                        <label class="form-check-label small fw-semibold">Show in legacy preview list</label>
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

        {{-- ─── Price modals ─────────────────────────────────── --}}
        <div class="modal fade" id="addPrice{{ $tier->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content modal-sheet">
                    <form action="{{ route('admin.saas-plans.prices.store', $tier->id) }}" method="POST">
                        @csrf
                        <div class="modal-header"><h5 class="modal-title">Add Price — {{ $tier->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body row g-3">@include('admin.saas-plans.partials.price-form', ['price' => null])</div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Create Price</button></div>
                    </form>
                </div>
            </div>
        </div>

        @foreach(collect($tier->prices ?? []) as $price)
            <div class="modal fade" id="editPrice{{ $price->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content modal-sheet">
                        <form action="{{ route('admin.saas-plans.prices.update', $price->id) }}" method="POST">
                            @csrf @method('PUT')
                            <div class="modal-header"><h5 class="modal-title">Edit Price — {{ $tier->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
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

@push('scripts')
<script>
(function () {
    const CF_DATA = @json($cardFeaturesByTier);
    const CF_TIER_IDS = @json($tiers->pluck('id')->all());
    const CF_SYNC_URL = @json(route('admin.saas-plans.card-features.sync', ['tierId' => '__ID__']));
    const CF_DELETE_URL = @json(route('admin.saas-plans.card-features.destroy', ['id' => '__ID__']));
    const CF_CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const included = (cf) => (cf.is_included == 1 || cf.is_included == true);

    // ── toast — reuse the admin layout's global toast container ─
    function cfToast(msg, ok = true) {
        const container = document.querySelector('.toast-container');
        if (!container) { console.warn(msg); return; }
        const toast = document.createElement('div');
        toast.className = 'toast ' + (ok ? 'toast-success' : 'toast-error') + ' show animate-fade-in';
        toast.setAttribute('role', 'alert');
        toast.innerHTML =
            '<div class="toast-body d-flex align-items-center">' +
                '<i class="bi ' + (ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill') + ' me-2 fs-5"></i>' +
                '<div class="flex-grow-1"></div>' +
                '<button type="button" class="btn-close btn-close-white ms-2" data-bs-dismiss="toast"></button>' +
            '</div>';
        toast.querySelector('.flex-grow-1').textContent = msg;
        container.appendChild(toast);
        // Same auto-dismiss behaviour as the layout (fade out + remove).
        setTimeout(() => {
            toast.style.transition = 'all 0.4s cubic-bezier(0.4, 0, 0.2, 1)';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 400);
        }, 4000);
    }

    // ── delete confirm — reuse the admin layout's global modal ──
    function cfConfirm(message, onOk) {
        const modalEl = document.getElementById('deleteConfirmModal');
        if (!modalEl) { if (onOk) onOk(); return; }
        document.getElementById('deleteConfirmMessage').textContent = message || 'This action cannot be undone.';
        const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
        document.getElementById('deleteConfirmBtn').onclick = function () {
            bootstrap.Modal.getInstance(modalEl).hide();
            if (onOk) onOk();
        };
        inst.show();
    }
    // Form-based confirms (Sync Defaults, price delete): open the global modal
    // but submit the ORIGINAL form so its method/CSRF stays intact.
    window.cfFormConfirm = function (form, message) {
        const modalEl = document.getElementById('deleteConfirmModal');
        if (!modalEl) { form.submit(); return false; }
        document.getElementById('deleteConfirmMessage').textContent = message || 'This action cannot be undone.';
        const inst = bootstrap.Modal.getOrCreateInstance(modalEl);
        document.getElementById('deleteConfirmBtn').onclick = function () {
            bootstrap.Modal.getInstance(modalEl).hide();
            form.onsubmit = null;
            form.submit();
        };
        inst.show();
        return false;
    };

    // ── dirty indicator ───────────────────────────────────────
    function markDirty(tierId) {
        const modal = document.getElementById('manageCardFeatures' + tierId);
        if (!modal) return;
        const dirty = modal.querySelector('.cf-dirty');
        if (dirty) dirty.style.display = 'inline';
    }
    function clearDirty(tierId) {
        const modal = document.getElementById('manageCardFeatures' + tierId);
        if (!modal) return;
        const dirty = modal.querySelector('.cf-dirty');
        if (dirty) dirty.style.display = 'none';
    }

    function cfRowHtml(cf) {
        const id = cf ? cf.id : '';
        const label = cf ? cf.feature_label : '';
        const inc = cf ? included(cf) : true;
        const tooltip = cf ? cf.tooltip_text : '';
        const order = cf ? cf.sort_order : 0;
        const vis = cf ? (cf.is_visible == 1 || cf.is_visible == true) : true;
        return '<div class="editor-row cf-item" data-id="' + esc(id) + '">' +
            '<div class="row g-2 align-items-end">' +
                '<div class="col-md-12">' +
                    '<label class="form-label small mb-1">Feature text</label>' +
                    '<input class="form-control cf-label" value="' + esc(label) + '" placeholder="eg. Free QR Sticker, Trainer management…">' +
                '</div>' +
                '<div class="col-md-4">' +
                    '<label class="form-label small mb-1">Included?</label>' +
                    '<div class="d-flex align-items-center gap-2 py-1">' +
                        '<label class="switch"><input type="checkbox" class="cf-included" ' + (inc ? 'checked' : '') + '><span class="track"></span></label>' +
                        '<span class="small fw-semibold cf-inc-txt">' + (inc ? '✓ Included' : '✗ Not included') + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="col-md-5">' +
                    '<label class="form-label small mb-1">Tooltip</label>' +
                    '<input class="form-control cf-tooltip" value="' + esc(tooltip) + '" placeholder="Optional">' +
                '</div>' +
                '<div class="col-md-3">' +
                    '<label class="form-label small mb-1">Order</label>' +
                    '<input class="form-control cf-order" type="number" value="' + esc(order) + '">' +
                '</div>' +
                '<div class="col-12 mt-2 d-flex justify-content-between align-items-center">' +
                    '<div class="form-check form-switch mb-0">' +
                        '<input class="form-check-input cf-visible" type="checkbox" ' + (vis ? 'checked' : '') + '>' +
                        '<label class="form-check-label small">Visible on app</label>' +
                    '</div>' +
                    '<button class="btn btn-sm btn-link text-danger cf-del p-0" type="button" ' + (id ? '' : 'style="display:none"') + '><i class="bi bi-trash3 me-1"></i>Delete</button>' +
                '</div>' +
            '</div>' +
        '</div>';
    }

    function cfRender(tierId) {
        const list = CF_DATA[tierId] || [];
        const listEl = document.getElementById('cfList' + tierId);
        const cardEl = document.getElementById('cfCard' + tierId);
        const prevEl = document.getElementById('cfPreview' + tierId);
        const visible = list.filter(cf => (cf.is_visible == 1 || cf.is_visible == true)).sort((a, b) => (a.sort_order || 0) - (b.sort_order || 0));

        if (listEl) {
            listEl.innerHTML = list.length
                ? list.map(cf => cfRowHtml(cf)).join('')
                : '<div class="alert alert-light border mb-0">No features yet — click “Add feature”.</div>';
            bindRows(listEl, tierId);
        }
        if (cardEl) {
            cardEl.innerHTML = visible.length
                ? visible.map(cf =>
                    '<div class="cf-line">' +
                        '<i class="bi ' + (included(cf) ? 'bi-check-circle-fill ico-inc' : 'bi-dash-circle ico-exc') + '"></i>' +
                        '<span class="t">' + esc(cf.feature_label) + '</span>' +
                        (cf.tooltip_text ? '<i class="bi bi-question-circle-fill tip" title="' + esc(cf.tooltip_text) + '"></i>' : '') +
                    '</div>').join('')
                : '<span class="mini-muted">No app features yet.</span>';
        }
        if (prevEl) {
            prevEl.innerHTML = visible.length
                ? visible.map(cf =>
                    '<div class="preview-line">' +
                        '<i class="bi ' + (included(cf) ? 'bi-check-circle-fill p-ok' : 'bi-x-circle-fill p-no') + '"></i>' +
                        '<span class="flex-grow-1" style="' + (included(cf) ? '' : 'color:#94a3b8') + '">' + esc(cf.feature_label) + '</span>' +
                        (cf.tooltip_text ? '<i class="bi bi-question-circle-fill p-tip" title="' + esc(cf.tooltip_text) + '"></i>' : '') +
                    '</div>').join('')
                : '<div class="preview-line" style="opacity:.6">Add features to preview them here.</div>';
        }
    }

    function bindRows(container, tierId) {
        container.querySelectorAll('.cf-item').forEach(row => {
            if (row.dataset.bound === '1') return;
            row.dataset.bound = '1';

            const incToggle = row.querySelector('.cf-included');
            const incTxt = row.querySelector('.cf-inc-txt');
            if (incToggle && incTxt) {
                incToggle.addEventListener('change', () => {
                    incTxt.textContent = incToggle.checked ? '✓ Included' : '✗ Not included';
                    markDirty(tierId);
                });
            }
            row.querySelectorAll('input').forEach(inp => {
                inp.addEventListener('input', () => markDirty(tierId));
            });

            const delBtn = row.querySelector('.cf-del');
            if (delBtn) {
                delBtn.addEventListener('click', () => {
                    const id = row.dataset.id;
                    const label = row.querySelector('.cf-label').value || 'this feature';
                    if (!id) { row.remove(); cfRender(tierId); markDirty(tierId); return; }
                    cfConfirm('“' + label + '” will be permanently removed.', async () => {
                        try {
                            const res = await fetch(CF_DELETE_URL.replace('__ID__', id), {
                                method: 'DELETE',
                                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CF_CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            if (!res.ok) { cfToast('Delete failed. Please try again.', false); return; }
                            CF_DATA[tierId] = (CF_DATA[tierId] || []).filter(x => String(x.id) !== String(id));
                            cfRender(tierId);
                            clearDirty(tierId);
                            cfToast('Feature deleted.');
                        } catch (e) {
                            cfToast('Network error: ' + e.message, false);
                        }
                    });
                });
            }
        });
    }

    function cfAddRow(tierId) {
        const listEl = document.getElementById('cfList' + tierId);
        if (!listEl) return;
        const empty = listEl.querySelector('.alert');
        if (empty) empty.remove();
        const wrap = document.createElement('div');
        wrap.innerHTML = cfRowHtml(null);
        const row = wrap.firstElementChild;
        listEl.appendChild(row);
        bindRows(listEl, tierId);
        markDirty(tierId);
        const label = row.querySelector('.cf-label');
        if (label) label.focus();
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // ── single "Save changes" per modal ───────────────────────
    async function cfSaveAll(tierId) {
        const listEl = document.getElementById('cfList' + tierId);
        if (!listEl) return;
        const rows = Array.from(listEl.querySelectorAll('.cf-item'));
        const payload = [];
        let invalid = false;
        rows.forEach((row, i) => {
            const label = row.querySelector('.cf-label').value.trim();
            if (!label) { invalid = true; row.querySelector('.cf-label').classList.add('is-invalid'); return; }
            row.querySelector('.cf-label').classList.remove('is-invalid');
            payload.push({
                id: row.dataset.id || null,
                feature_label: label,
                is_included: row.querySelector('.cf-included').checked ? 1 : 0,
                tooltip_text: row.querySelector('.cf-tooltip').value.trim(),
                sort_order: parseInt(row.querySelector('.cf-order').value || '0', 10) || i,
                is_visible: row.querySelector('.cf-visible').checked ? 1 : 0,
            });
        });
        if (invalid) { cfToast('Every feature needs text.', false); return; }

        const btn = document.querySelector('.cf-save-all[data-tier="' + tierId + '"]');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving…'; }
        try {
            const res = await fetch(CF_SYNC_URL.replace('__ID__', tierId), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CF_CSRF, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ features: payload }),
            });
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                const msg = err.message || (err.errors ? Object.values(err.errors).flat()[0] : null) || 'Save failed.';
                cfToast(msg, false);
                return;
            }
            await res.json().catch(() => ({}));
            // Reload the page so the tier cards + stats are fully in sync.
            cfToast('Features saved.');
            setTimeout(() => window.location.reload(), 700);
        } catch (e) {
            cfToast('Network error: ' + e.message, false);
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Save changes'; }
        }
    }

    // ── wire global buttons ──────────────────────────────────
    document.addEventListener('click', (e) => {
        const add = e.target.closest('.cf-add-btn');
        if (add) { cfAddRow(add.dataset.tier); return; }
        const save = e.target.closest('.cf-save-all');
        if (save) { cfSaveAll(save.dataset.tier); }
    });

    // Initial render for every tier (main cards + modal lists + previews).
    CF_TIER_IDS.forEach(tierId => cfRender(tierId));
})();
</script>
@endpush
