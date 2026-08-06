@php($p = $price)
<div class="col-md-6">
    <label class="form-label">Billing Cycle</label>
    <select name="billing_cycle" class="form-select" required>
        @foreach($billingCycles as $key => $label)
            <option value="{{ $key }}" @selected(($p->billing_cycle ?? 'monthly') === $key)>{{ $label }}</option>
        @endforeach
    </select>
</div>
<div class="col-md-6">
    <label class="form-label">Duration Months</label>
    <input type="number" name="duration_months" class="form-control" min="1" max="120" value="{{ $p->duration_months ?? 1 }}" required>
</div>
<div class="col-md-6">
    <label class="form-label">Price</label>
    <input type="number" name="price" class="form-control" min="0" step="0.01" value="{{ $p->price ?? 0 }}" required>
</div>
<div class="col-md-6">
    <label class="form-label">Strike Price</label>
    <input type="number" name="strike_price" class="form-control" min="0" step="0.01" value="{{ $p->strike_price ?? '' }}">
</div>
<div class="col-md-6">
    <label class="form-label">Discount Text</label>
    <input type="text" name="discount_text" class="form-control" value="{{ $p->discount_text ?? '' }}" placeholder="Save ₹500">
</div>
<div class="col-md-3">
    <label class="form-label">Active</label>
    <select name="is_active" class="form-select">
        <option value="1" @selected(($p->is_active ?? true) == true)>Yes</option>
        <option value="0" @selected(($p->is_active ?? true) == false)>No</option>
    </select>
</div>
<div class="col-md-3">
    <label class="form-label">Sort</label>
    <input type="number" name="sort_order" class="form-control" value="{{ $p->sort_order ?? 0 }}">
</div>
