@extends('panel.layouts.app')

@section('title', 'Products')

@section('content')
<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h5 class="mb-0"><i class="bi bi-box me-2"></i> Products</h5>
        <div class="d-flex gap-2">
            <div class="position-relative" style="width: 260px;">
                <input type="text" id="productSearch" class="form-control form-control-sm" placeholder="🔍 Search products...">
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal">
                <i class="bi bi-plus-circle me-2"></i> Add Product
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Description</th>
                    <th>Price</th>
                    <th>Discount</th>
                    <th>Final Price</th>
                    <th style="width: 100px;">Actions</th>
                </tr>
            </thead>
            <tbody id="productsTableBody">
                @forelse($products as $product)
                @php $finalPrice = $product->price - ($product->discount ?? 0); @endphp
                <tr class="product-row" data-id="{{ $product->id }}">
                    <td><strong class="product-title">{{ $product->title }}</strong></td>
                    <td class="product-desc">{{ Str::limit($product->description, 30) ?? '-' }}</td>
                    <td>₹<span class="product-price">{{ number_format($product->price) }}</span></td>
                    <td>{{ $product->discount ? '₹' . number_format($product->discount) : '-' }}</td>
                    <td><strong>₹{{ number_format($finalPrice) }}</strong></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-product-btn"
                                data-id="{{ $product->id }}"
                                data-title="{{ $product->title }}"
                                data-description="{{ $product->description }}"
                                data-price="{{ $product->price }}"
                                data-discount="{{ $product->discount ?? 0 }}">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-product-btn"
                                data-id="{{ $product->id }}">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                @empty
                <tr id="noProductsRow">
                    <td colspan="6" class="text-center py-4">No products added yet</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Product Name *</label>
                    <input type="text" id="prodTitle" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="prodDesc" class="form-control" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col">
                        <label class="form-label">Price (₹) *</label>
                        <input type="number" id="prodPrice" class="form-control" min="0" step="1" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Discount (₹)</label>
                        <input type="number" id="prodDiscount" class="form-control" min="0" step="1" value="0">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitProduct()">Add Product</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <input type="hidden" id="editProdId">
            <div class="modal-header">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Product Name *</label>
                    <input type="text" id="editProdTitle" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea id="editProdDesc" class="form-control" rows="2"></textarea>
                </div>
                <div class="row">
                    <div class="col">
                        <label class="form-label">Price (₹) *</label>
                        <input type="number" id="editProdPrice" class="form-control" min="0" step="1" required>
                    </div>
                    <div class="col">
                        <label class="form-label">Discount (₹)</label>
                        <input type="number" id="editProdDiscount" class="form-control" min="0" step="1">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateProduct()">Update Product</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Add Product (AJAX)
    async function submitProduct() {
        const btn = document.querySelector('#addProductModal .btn-primary');
        const origText = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Adding...`;
        }

        const title = document.getElementById('prodTitle').value.trim();
        const description = document.getElementById('prodDesc').value.trim();
        const price = document.getElementById('prodPrice').value;
        const discount = document.getElementById('prodDiscount').value || 0;

        if (!title || !price) {
            window.showToast('Product name and price are required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            return;
        }

        const fd = new FormData();
        fd.append('title', title);
        fd.append('description', description);
        fd.append('price', price);
        fd.append('discount', discount);

        try {
            const res = await fetch('/panel/products', {
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
                window.showToast(data.message || 'Product added!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('addProductModal')).hide();
                resetProductForm();

                if (data.product) {
                    addProductRow(data.product);
                }
            } else {
                window.showToast(data.error || 'Failed to add product', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText || 'Add Product';
            }
        }
    }

    function resetProductForm() {
        document.getElementById('prodTitle').value = '';
        document.getElementById('prodDesc').value = '';
        document.getElementById('prodPrice').value = '';
        document.getElementById('prodDiscount').value = '0';
    }

    // Add row to table
    function addProductRow(prod) {
        const tbody = document.getElementById('productsTableBody');
        const noRow = document.getElementById('noProductsRow');
        if (noRow) noRow.remove();

        const finalPrice = parseFloat(prod.price) - (parseFloat(prod.discount) || 0);

        const tr = document.createElement('tr');
        tr.className = 'product-row';
        tr.dataset.id = prod.id;

        tr.innerHTML = `
            <td><strong class="product-title">${prod.title}</strong></td>
            <td class="product-desc">${prod.description ? prod.description.substring(0,30) : '-'}</td>
            <td>₹<span class="product-price">${Number(prod.price).toLocaleString()}</span></td>
            <td>${prod.discount ? '₹' + Number(prod.discount).toLocaleString() : '-'}</td>
            <td><strong>₹${finalPrice.toLocaleString()}</strong></td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-primary me-1 edit-product-btn"
                        data-id="${prod.id}"
                        data-title="${prod.title}"
                        data-description="${prod.description || ''}"
                        data-price="${prod.price}"
                        data-discount="${prod.discount || 0}">
                    <i class="bi bi-pencil"></i>
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger delete-product-btn"
                        data-id="${prod.id}">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        tbody.prepend(tr);
        attachProductListeners();
    }

    // Edit modal open
    document.addEventListener('click', function(e) {
        const editBtn = e.target.closest('.edit-product-btn');
        if (editBtn) {
            document.getElementById('editProdId').value = editBtn.dataset.id;
            document.getElementById('editProdTitle').value = editBtn.dataset.title;
            document.getElementById('editProdDesc').value = editBtn.dataset.description || '';
            document.getElementById('editProdPrice').value = editBtn.dataset.price;
            document.getElementById('editProdDiscount').value = editBtn.dataset.discount || 0;

            new bootstrap.Modal(document.getElementById('editProductModal')).show();
        }
    });

    // Update Product (AJAX)
    async function updateProduct() {
        const btn = document.querySelector('#editProductModal .btn-primary');
        const origText = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2"></span> Updating...`;
        }

        const id = document.getElementById('editProdId').value;
        const title = document.getElementById('editProdTitle').value.trim();
        const description = document.getElementById('editProdDesc').value.trim();
        const price = document.getElementById('editProdPrice').value;
        const discount = document.getElementById('editProdDiscount').value || 0;

        if (!title || !price) {
            window.showToast('Title and price are required', 'error');
            if (btn) { btn.disabled = false; btn.innerHTML = origText; }
            return;
        }

        const fd = new FormData();
        fd.append('_method', 'PUT');
        fd.append('title', title);
        fd.append('description', description);
        fd.append('price', price);
        fd.append('discount', discount);

        try {
            const res = await fetch(`/panel/products/${id}`, {
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
                window.showToast('Product updated!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('editProductModal')).hide();

                // Update row in table
                updateProductRow(id, data.product);
            } else {
                window.showToast(data.error || 'Update failed', 'error');
            }
        } catch (e) {
            window.showToast('Network error', 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText || 'Update Product';
            }
        }
    }

    function updateProductRow(id, prod) {
        const row = document.querySelector(`.product-row[data-id="${id}"]`);
        if (!row) return;

        row.querySelector('.product-title').textContent = prod.title;
        row.querySelector('.product-desc').textContent = prod.description ? prod.description.substring(0,30) : '-';
        row.querySelector('.product-price').textContent = Number(prod.price).toLocaleString();

        // Update final price
        const final = parseFloat(prod.price) - (parseFloat(prod.discount) || 0);
        const finalCell = row.querySelector('td:nth-child(5) strong');
        if (finalCell) finalCell.textContent = '₹' + final.toLocaleString();

        // Update edit button data
        const editBtn = row.querySelector('.edit-product-btn');
        if (editBtn) {
            editBtn.dataset.title = prod.title;
            editBtn.dataset.description = prod.description || '';
            editBtn.dataset.price = prod.price;
            editBtn.dataset.discount = prod.discount || 0;
        }
    }

    // Delete using custom modal
    function attachProductListeners() {
        document.querySelectorAll('.delete-product-btn').forEach(btn => {
            // Remove old listeners
            btn.replaceWith(btn.cloneNode(true));
        });

        document.querySelectorAll('.delete-product-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const row = this.closest('.product-row');
                const title = row ? row.querySelector('.product-title')?.textContent : 'this product';

                document.getElementById('deleteConfirmMessage').textContent = `Delete "${title}"?`;

                const modalEl = document.getElementById('deleteConfirmModal');
                const modal = new bootstrap.Modal(modalEl);
                const confirmBtn = document.getElementById('deleteConfirmBtn');

                // Clone to remove previous handlers
                const freshBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(freshBtn, confirmBtn);

                freshBtn.onclick = async () => {
                    modal.hide();

                    const origHtml = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = `<span class="spinner-border spinner-border-sm"></span>`;

                    try {
                        const res = await fetch(`/panel/products/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            }
                        });

                        const data = await res.json();

                        if (data.success) {
                            if (row) row.remove();
                            window.showToast('Product deleted', 'success');

                            // Show empty message if needed
                            const tbody = document.getElementById('productsTableBody');
                            if (tbody && tbody.children.length === 0) {
                                tbody.innerHTML = `<tr id="noProductsRow"><td colspan="6" class="text-center py-4">No products added yet</td></tr>`;
                            }
                        } else {
                            window.showToast(data.error || 'Delete failed', 'error');
                            this.disabled = false;
                            this.innerHTML = origHtml;
                        }
                    } catch (e) {
                        window.showToast('Network error', 'error');
                        this.disabled = false;
                        this.innerHTML = origHtml;
                    }
                };

                modal.show();
            });
        });
    }

    // Live search for products
    function filterProducts() {
        const searchInput = document.getElementById('productSearch');
        if (!searchInput) return;

        const query = searchInput.value.toLowerCase().trim();

        document.querySelectorAll('#productsTableBody .product-row').forEach(row => {
            const title = (row.querySelector('.product-title')?.textContent || '').toLowerCase();
            const desc = (row.querySelector('.product-desc')?.textContent || '').toLowerCase();
            const matches = title.includes(query) || desc.includes(query);
            row.style.display = matches ? '' : 'none';
        });
    }

    // Initial attach
    document.addEventListener('DOMContentLoaded', function() {
        attachProductListeners();

        const searchInput = document.getElementById('productSearch');
        if (searchInput) {
            searchInput.addEventListener('input', filterProducts);
        }
    });
</script>
@endpush
