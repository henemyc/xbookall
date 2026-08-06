@extends('panel.layouts.app')

@section('title', 'Membership Plans')

@section('content')
<div class="table-card">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h5 class="mb-1"><i class="bi bi-award me-2" style="color: var(--primary);"></i> Membership Plans</h5>
            <small class="text-muted" id="plansCount">{{ $plans->count() }} plans</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
            <i class="bi bi-plus-circle me-2"></i> Add Plan
        </button>
    </div>

    <div class="row g-3" id="plansGrid">
        @forelse($plans as $plan)
        <div class="col-md-4 plan-card" data-id="{{ $plan->id }}" data-title="{{ strtolower($plan->title) }}" data-amount="{{ $plan->amount }}" data-package="{{ strtolower($plan->package) }}">
            <div class="p-4 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1" style="font-family: 'Space Grotesk', sans-serif;">{{ $plan->title }}</h5>
                        <span class="badge bg-primary">{{ ucfirst($plan->package) }}</span>
                    </div>
                    <div class="text-end">
                        <div style="font-family: 'Space Grotesk', sans-serif; font-size: 24px; font-weight: 700; color: var(--primary);">₹{{ number_format($plan->amount) }}</div>
                        <small class="text-muted">/ {{ $plan->package }}</small>
                    </div>
                </div>

                <div class="d-flex justify-content-between py-2 border-top">
                    <span class="text-muted">Members</span>
                    <span class="fw-bold plan-member-count">{{ $plan->member_count ?? 0 }}</span>
                </div>

                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary flex-grow-1 edit-plan-btn" 
                            data-id="{{ $plan->id }}"
                            data-title="{{ $plan->title }}"
                            data-amount="{{ $plan->amount }}"
                            data-package="{{ $plan->package }}"
                            data-notes="{{ $plan->notes }}">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>

                    @if(($plan->member_count ?? 0) > 0)
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1" disabled title="Cannot delete - members using this plan">
                            <i class="bi bi-lock me-1"></i> In Use
                        </button>
                    @else
                        <button class="btn btn-sm btn-outline-danger flex-grow-1 delete-plan-btn" 
                                data-id="{{ $plan->id }}" 
                                data-title="{{ $plan->title }}">
                            <i class="bi bi-trash3 me-1"></i> Delete
                        </button>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="col-12 text-center py-5" id="noPlansMessage">
            <i class="bi bi-award fs-1 d-block mb-3" style="opacity: 0.2;"></i>
            <p class="text-muted">No plans created yet</p>
        </div>
        @endforelse
    </div>
</div>

<!-- Add Plan Modal -->
<div class="modal fade" id="addPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div style="background: linear-gradient(135deg, #0f172a, #1e293b); padding: 20px; border-radius: 20px 20px 0 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: white;">Add New Plan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                        <input type="text" id="planTitle" class="form-control" placeholder="e.g., Monthly Premium" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" id="planAmount" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Package <span class="text-danger">*</span></label>
                        <select id="planPackage" class="form-select" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="half-yearly">Half Yearly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea id="planNotes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="addPlanBtn" onclick="submitPlan()">
                    <i class="bi bi-check-circle me-2"></i> Create Plan
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Plan Modal -->
<div class="modal fade" id="editPlanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 20px;">
            <div style="background: linear-gradient(135deg, #0f172a, #1e293b); padding: 20px; border-radius: 20px 20px 0 0;">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0" style="color: white;">Edit Plan</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editPlanId">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Plan Name <span class="text-danger">*</span></label>
                        <input type="text" id="editPlanTitle" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Amount (₹) <span class="text-danger">*</span></label>
                        <input type="number" id="editPlanAmount" class="form-control" min="0" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Package <span class="text-danger">*</span></label>
                        <select id="editPlanPackage" class="form-select" required>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="half-yearly">Half Yearly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <textarea id="editPlanNotes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="updatePlanBtn" onclick="updatePlan()">
                    <i class="bi bi-save me-2"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Prevent duplicate plans (name + price + duration)
    function isDuplicatePlan(title, amount, package, excludeId = null) {
        const cards = document.querySelectorAll('.plan-card');
        for (let card of cards) {
            const id = card.dataset.id;
            if (excludeId && id == excludeId) continue;

            const cardTitle = (card.dataset.title || '').toLowerCase();
            const cardAmount = parseFloat(card.dataset.amount || '0');
            const cardPackage = (card.dataset.package || '').toLowerCase();

            if (
                cardTitle === title.toLowerCase() &&
                cardAmount === parseFloat(amount || '0') &&
                cardPackage === package.toLowerCase()
            ) {
                return true;
            }
        }
        return false;
    }

    // Submit new plan (AJAX)
    async function submitPlan() {
        const btn = document.getElementById('addPlanBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Creating...';

        const title = document.getElementById('planTitle').value.trim();
        const amount = document.getElementById('planAmount').value;
        const pkg = document.getElementById('planPackage').value;
        const notes = document.getElementById('planNotes').value.trim();

        if (!title || !amount || !pkg) {
            window.showToast('Please fill required fields', 'error');
            btn.disabled = false;
            btn.innerHTML = orig;
            return;
        }

        if (isDuplicatePlan(title, amount, pkg)) {
            window.showToast('A plan with same name, price and duration already exists!', 'error');
            btn.disabled = false;
            btn.innerHTML = orig;
            return;
        }

        const fd = new FormData();
        fd.append('title', title);
        fd.append('amount', amount);
        fd.append('package', pkg);
        if (notes) fd.append('notes', notes);

        try {
            const res = await fetch('/panel/plans', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.showToast(data.message || 'Plan created successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addPlanModal')).hide();
                document.getElementById('addPlanModal').querySelectorAll('input, textarea').forEach(el => el.value = '');
                document.getElementById('planPackage').value = 'monthly';
                
                // Add card dynamically
                addPlanCard(data.plan);
            } else {
                window.showToast(data.error || 'Failed to create plan', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // Open edit modal
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-plan-btn')) {
            const btn = e.target.closest('.edit-plan-btn');
            document.getElementById('editPlanId').value = btn.dataset.id;
            document.getElementById('editPlanTitle').value = btn.dataset.title;
            document.getElementById('editPlanAmount').value = btn.dataset.amount;
            document.getElementById('editPlanPackage').value = btn.dataset.package;
            document.getElementById('editPlanNotes').value = btn.dataset.notes || '';
            
            new bootstrap.Modal(document.getElementById('editPlanModal')).show();
        }
    });

    // Update plan (AJAX)
    async function updatePlan() {
        const btn = document.getElementById('updatePlanBtn');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '⏳ Saving...';

        const id = document.getElementById('editPlanId').value;
        const title = document.getElementById('editPlanTitle').value.trim();
        const amount = document.getElementById('editPlanAmount').value;
        const pkg = document.getElementById('editPlanPackage').value;
        const notes = document.getElementById('editPlanNotes').value.trim();

        if (isDuplicatePlan(title, amount, pkg, id)) {
            window.showToast('Another plan with same name, price and duration exists!', 'error');
            btn.disabled = false;
            btn.innerHTML = orig;
            return;
        }

        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('title', title);
        fd.append('amount', amount);
        fd.append('package', pkg);
        fd.append('notes', notes);

        try {
            const res = await fetch(`/panel/plans/${id}`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: fd
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.showToast('Plan updated successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editPlanModal')).hide();
                updatePlanCard(id, data.plan || { title, amount, package: pkg, notes });
            } else {
                window.showToast(data.error || 'Failed to update', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }

    // Add plan card dynamically
    function addPlanCard(plan) {
        const grid = document.getElementById('plansGrid');
        const noPlans = document.getElementById('noPlansMessage');
        if (noPlans) noPlans.remove();

        const col = document.createElement('div');
        col.className = 'col-md-4 plan-card';
        col.dataset.id = plan.id;
        col.dataset.title = (plan.title || '').toLowerCase();
        col.dataset.amount = plan.amount || 0;
        col.dataset.package = (plan.package || '').toLowerCase();

        col.innerHTML = `
            <div class="p-4 rounded" style="background: var(--bg); border: 1px solid var(--border);">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1" style="font-family: 'Space Grotesk', sans-serif;">${plan.title}</h5>
                        <span class="badge bg-primary">${plan.package}</span>
                    </div>
                    <div class="text-end">
                        <div style="font-family: 'Space Grotesk', sans-serif; font-size: 24px; font-weight: 700; color: var(--primary);">₹${Number(plan.amount).toLocaleString()}</div>
                        <small class="text-muted">/ ${plan.package}</small>
                    </div>
                </div>
                <div class="d-flex justify-content-between py-2 border-top">
                    <span class="text-muted">Members</span>
                    <span class="fw-bold plan-member-count">0</span>
                </div>
                <div class="d-flex gap-2 mt-3">
                    <button class="btn btn-sm btn-outline-primary flex-grow-1 edit-plan-btn" 
                            data-id="${plan.id}"
                            data-title="${plan.title}"
                            data-amount="${plan.amount}"
                            data-package="${plan.package}"
                            data-notes="${plan.notes || ''}">
                        <i class="bi bi-pencil me-1"></i> Edit
                    </button>
                    <button class="btn btn-sm btn-outline-danger flex-grow-1 delete-plan-btn" 
                            data-id="${plan.id}" 
                            data-title="${plan.title}">
                        <i class="bi bi-trash3 me-1"></i> Delete
                    </button>
                </div>
            </div>
        `;

        grid.appendChild(col);
        attachPlanListeners(col);

        // Update count
        updatePlansCount();
    }

    function updatePlanCard(id, plan) {
        const card = document.querySelector(`.plan-card[data-id="${id}"]`);
        if (!card) return;

        card.dataset.title = (plan.title || '').toLowerCase();
        card.dataset.amount = plan.amount || 0;
        card.dataset.package = (plan.package || '').toLowerCase();
        card.querySelector('h5').textContent = plan.title;
        card.querySelector('.badge').textContent = plan.package || '';
        
        const amountEl = card.querySelector('.text-end div');
        if (amountEl) amountEl.textContent = `₹${Number(plan.amount).toLocaleString()}`;
        const packageSmall = card.querySelector('.text-end small');
        if (packageSmall) packageSmall.textContent = `/ ${plan.package || ''}`;

        // Update data attributes
        const editBtn = card.querySelector('.edit-plan-btn');
        if (editBtn) {
            editBtn.dataset.title = plan.title;
            editBtn.dataset.amount = plan.amount;
            editBtn.dataset.package = plan.package;
            editBtn.dataset.notes = plan.notes || '';
        }

        updatePlansCount();
    }

    function attachPlanListeners(container = document) {
        // Delete handlers
        container.querySelectorAll('.delete-plan-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const title = this.dataset.title;

                document.getElementById('deleteConfirmMessage').textContent = `Delete "${title}"?`;

                const modal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
                const confirmBtn = document.getElementById('deleteConfirmBtn');

                const fresh = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(fresh, confirmBtn);

                fresh.onclick = () => {
                    modal.hide();
                    deletePlan(id, this);
                };

                modal.show();
            });
        });

        // Edit buttons are handled by delegation above
    }

    async function deletePlan(id, btnElement) {
        const orig = btnElement.innerHTML;
        btnElement.disabled = true;
        btnElement.innerHTML = '⏳';

        try {
            const res = await fetch(`/panel/plans/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            const data = await res.json();

            if (data.success) {
                const card = btnElement.closest('.plan-card');
                if (card) card.remove();
                window.showToast('Plan deleted successfully', 'success');
                updatePlansCount();
            } else {
                window.showToast(data.error || 'Cannot delete plan', 'error');
                btnElement.innerHTML = orig;
                btnElement.disabled = false;
            }
        } catch (e) {
            window.showToast('Error deleting plan', 'error');
            btnElement.innerHTML = orig;
            btnElement.disabled = false;
        }
    }

    function updatePlansCount() {
        const countEl = document.getElementById('plansCount');
        const cards = document.querySelectorAll('.plan-card');
        if (countEl) countEl.textContent = `${cards.length} plans`;
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        attachPlanListeners();
    });
</script>
@endpush