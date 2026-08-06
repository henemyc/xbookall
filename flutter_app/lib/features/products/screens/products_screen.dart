import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class ProductsScreen extends ConsumerStatefulWidget {
  const ProductsScreen({super.key});
  @override
  ConsumerState<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends ConsumerState<ProductsScreen> {
  List products = [];
  bool loading = true;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await ref.read(apiClientProvider).getProducts();
      if (mounted) setState(() { products = res['products'] ?? []; loading = false; });
    } catch (_) { if (mounted) setState(() => loading = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: loading
          ? const SkeletonList()
          : products.isEmpty
              ? EmptyState(icon: Icons.storefront_rounded, title: 'No products yet', subtitle: 'Add supplements, gear, and more', actionLabel: 'Add Product', onAction: _showAdd)
              : RefreshIndicator(
                  color: AppTheme.brand,
                  onRefresh: _load,
                  child: ListView.separated(
                    padding: EdgeInsets.fromLTRB(16, 8, 16, context.navSpace + 16),
                    itemCount: products.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 10),
                    itemBuilder: (ctx, i) {
                      final p = products[i];
                      return FadeInUp(delayMs: (i * 20).clamp(0, 240), offset: 10, child: SurfaceCard(
                        padding: const EdgeInsets.all(12),
                        onTap: () => _showEdit(Map<String, dynamic>.from(p)),
                        child: Row(children: [
                          IconBadge(Icons.storefront_rounded, color: AppTheme.warning, size: 46, iconSize: 22),
                          const SizedBox(width: 12),
                          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(p['title'] ?? '', style: context.typo.titleSmall),
                            if ((p['description'] ?? '') != '') Text(p['description'] ?? '', style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary), maxLines: 1, overflow: TextOverflow.ellipsis),
                          ])),
                          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                            Text('₹${p['price']}', style: GoogleFonts.spaceGrotesk(fontSize: 16, fontWeight: FontWeight.w700, color: context.tokens.text)),
                            if (p['discount'] != null) Text('${p['discount']}% off', style: context.typo.labelSmall?.copyWith(color: AppTheme.success)),
                          ]),
                          PopupMenuButton<String>(
                            icon: Icon(Icons.more_vert_rounded, size: 20, color: context.tokens.textTertiary),
                            onSelected: (v) { if (v == 'edit') _showEdit(Map<String, dynamic>.from(p)); if (v == 'delete') _deleteProduct(p); },
                            itemBuilder: (_) => const [
                              PopupMenuItem(value: 'edit', child: Text('Edit')),
                              PopupMenuItem(value: 'delete', child: Text('Delete', style: TextStyle(color: AppTheme.danger))),
                            ],
                          ),
                        ]),
                      ));
                    },
                  ),
                ),
      floatingActionButtonLocation: const AboveNavFabLocation(),
      floatingActionButton: FloatingActionButton.extended(onPressed: _showAdd, icon: const Icon(Icons.add_rounded), label: const Text('Add Product', style: TextStyle(fontWeight: FontWeight.w700)), backgroundColor: AppTheme.brand),
    );
  }

  void _showAdd() => _showProductSheet();

  void _showEdit(Map p) => _showProductSheet(product: p);

  Future<void> _deleteProduct(Map p) async {
    final ok = await showDialog<bool>(context: context, builder: (ctx) => AlertDialog(title: const Text('Delete Product?'), content: const Text('This cannot be undone.'), actions: [TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')), ElevatedButton(style: ElevatedButton.styleFrom(backgroundColor: AppTheme.danger), onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete'))]));
    if (ok != true) return;
    try { await ref.read(apiClientProvider).deleteProduct(p['id']); _load(); if (mounted) Toast.success(context, 'Deleted'); } catch (e) { if (mounted) Toast.error(context, 'Failed'); }
  }

  void _showProductSheet({Map? product}) {
    final editing = product != null;
    final titleCtrl = TextEditingController(text: product?['title'] ?? '');
    final priceCtrl = TextEditingController(text: product != null ? '${product['price'] ?? ''}' : '');
    final descCtrl = TextEditingController(text: product?['description'] ?? '');
    final discountCtrl = TextEditingController(text: product != null && product['discount'] != null ? '${product['discount']}' : '');
    showAppSheet(context, child: SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
      child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [IconBadge(editing ? Icons.edit_rounded : Icons.storefront_rounded, color: AppTheme.warning), const SizedBox(width: 12), Text(editing ? 'Edit Product' : 'Add Product', style: context.typo.titleLarge)]),
        const SizedBox(height: 18),
        TextField(controller: titleCtrl, decoration: const InputDecoration(labelText: 'Title*')),
        const SizedBox(height: 12),
        Row(children: [
          Expanded(child: TextField(controller: priceCtrl, decoration: const InputDecoration(labelText: 'Price*', prefixText: '₹ '), keyboardType: TextInputType.number)),
          const SizedBox(width: 12),
          Expanded(child: TextField(controller: discountCtrl, decoration: const InputDecoration(labelText: 'Discount %'), keyboardType: TextInputType.number)),
        ]),
        const SizedBox(height: 12),
        TextField(controller: descCtrl, decoration: const InputDecoration(labelText: 'Description')),
        const SizedBox(height: 20),
        FireButton(label: editing ? 'Save Changes' : 'Add Product', onPressed: () async {
          if (titleCtrl.text.trim().isEmpty) { Toast.error(context, 'Title required'); return; }
          final data = {'title': titleCtrl.text.trim(), 'price': double.tryParse(priceCtrl.text) ?? 0, 'description': descCtrl.text.trim(), 'discount': discountCtrl.text.trim().isEmpty ? null : double.tryParse(discountCtrl.text.trim())};
          try {
            final api = ref.read(apiClientProvider);
            if (editing) {
              await api.updateProduct(product['id'], data);
            } else {
              await api.createProduct(data);
            }
            if (mounted) Navigator.pop(context); _load();
            if (mounted) Toast.success(context, editing ? 'Product updated' : 'Product added');
          } catch (_) { Toast.error(context, 'Failed'); }
        }),
      ]),
    ));
  }
}
