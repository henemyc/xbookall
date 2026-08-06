import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class NewInvoiceScreen extends ConsumerStatefulWidget {
  const NewInvoiceScreen({super.key});
  @override
  ConsumerState<NewInvoiceScreen> createState() => _NewInvoiceScreenState();
}

class _NewInvoiceScreenState extends ConsumerState<NewInvoiceScreen> {
  final memberSearchCtrl = TextEditingController();
  List membersSearch = [];
  Map<String, dynamic>? selectedMember;

  List products = [];
  Map<int, Map<String, dynamic>> selectedProducts = {};

  List<Map<String, dynamic>> customItems = [];
  final customTitleCtrl = TextEditingController();
  final customAmountCtrl = TextEditingController();

  final paidCtrl = TextEditingController();
  String paymentMethod = 'cash';
  final notesCtrl = TextEditingController();

  bool loading = false;
  bool loadingProducts = true;

  @override
  void initState() {
    super.initState();
    _loadProducts();
  }

  Future<void> _loadProducts() async {
    try {
      final res = await ref.read(apiClientProvider).getProducts();
      setState(() { products = res['products'] ?? []; loadingProducts = false; });
    } catch (_) { setState(() => loadingProducts = false); }
  }

  Future<void> _searchMembers(String q) async {
    if (q.isEmpty) { setState(() => membersSearch = []); return; }
    try {
      // Use real members endpoint with search (gym-scoped)
      final res = await ref.read(apiClientProvider).getMembers(search: q);
      setState(() => membersSearch = res['members'] ?? []);
    } catch (_) {}
  }

  double get total {
    double t = 0;
    selectedProducts.forEach((id, data) {
      if (data['checked'] == true) {
        final price = double.tryParse(data['product']['price'].toString()) ?? 0;
        final qty = data['qty'] ?? 1;
        t += price * qty;
      }
    });
    for (var item in customItems) { t += (item['amount'] as double); }
    return t;
  }

  double get balance => total - (double.tryParse(paidCtrl.text) ?? 0);

  @override
  Widget build(BuildContext context) {
    final tt = context.tokens;
    return Scaffold(
      appBar: AppBar(title: const Text('New Invoice')),
      bottomNavigationBar: const AppBottomNav(),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _card('Select Member', Icons.person_rounded, AppTheme.brand, [
            TextField(controller: memberSearchCtrl, decoration: const InputDecoration(hintText: 'Search by name, phone…', prefixIcon: Icon(Icons.search_rounded)), onChanged: _searchMembers),
            if (membersSearch.isNotEmpty)
              Container(
                margin: const EdgeInsets.only(top: 10),
                constraints: const BoxConstraints(maxHeight: 220),
                child: SingleChildScrollView(child: Column(children: membersSearch.map((m) => ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: GxAvatar(name: m['name'] ?? 'M', size: 36),
                  title: Text(m['name'], style: context.typo.titleSmall),
                  subtitle: Text(m['phone_number'] ?? '', style: context.typo.bodySmall),
                  onTap: () => setState(() { selectedMember = Map<String, dynamic>.from(m); membersSearch = []; memberSearchCtrl.text = m['name']; }),
                )).toList())),
              ),
            if (selectedMember != null)
              Container(
                margin: const EdgeInsets.only(top: 12),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(color: AppTheme.brand.withOpacity(0.10), borderRadius: BorderRadius.circular(14)),
                child: Row(children: [GxAvatar(name: selectedMember!['name'], size: 36), const SizedBox(width: 10), Expanded(child: Text(selectedMember!['name'], style: context.typo.titleSmall)), IconButton(onPressed: () => setState(() { selectedMember = null; memberSearchCtrl.clear(); }), icon: const Icon(Icons.close_rounded, size: 18))]),
              ),
          ]),
          const SizedBox(height: 14),
          _card('Products', Icons.storefront_rounded, AppTheme.info, [
            loadingProducts
                ? const Center(child: Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator()))
                : products.isEmpty
                    ? Text('No products found. Add products in PWA first.', style: context.typo.bodySmall?.copyWith(color: tt.textTertiary))
                    : Column(children: products.map((p) {
                        final id = int.tryParse(p['id'].toString()) ?? 0;
                        final sel = selectedProducts[id];
                        final checked = sel?['checked'] ?? false;
                        final qty = sel?['qty'] ?? 1;
                        return Container(
                          margin: const EdgeInsets.only(bottom: 8),
                          decoration: BoxDecoration(color: checked ? AppTheme.brand.withOpacity(0.08) : tt.surfaceAlt, borderRadius: BorderRadius.circular(14), border: Border.all(color: checked ? AppTheme.brand.withOpacity(0.3) : Colors.transparent)),
                          child: CheckboxListTile(
                            value: checked,
                            activeColor: AppTheme.brand,
                            title: Text(p['title'] ?? '', style: context.typo.titleSmall?.copyWith(fontSize: 13.5)),
                            subtitle: Text('₹${p['price']} ${p['discount'] != null ? '(-${p['discount']}% off)' : ''}', style: context.typo.bodySmall),
                            secondary: checked
                                ? Row(mainAxisSize: MainAxisSize.min, children: [
                                    _qtyBtn(Icons.remove_rounded, qty > 1 ? () => setState(() => selectedProducts[id]!['qty'] = qty - 1) : null),
                                    Padding(padding: const EdgeInsets.symmetric(horizontal: 8), child: Text('$qty', style: context.typo.titleSmall)),
                                    _qtyBtn(Icons.add_rounded, () => setState(() => selectedProducts[id]!['qty'] = qty + 1)),
                                  ])
                                : null,
                            onChanged: (v) => setState(() { if (v == true) { selectedProducts[id] = {'product': p, 'qty': 1, 'checked': true}; } else { selectedProducts.remove(id); } }),
                          ),
                        );
                      }).toList()),
          ]),
          const SizedBox(height: 14),
          _card('Custom Items', Icons.add_box_rounded, AppTheme.warning, [
            Row(children: [
              Expanded(flex: 2, child: TextField(controller: customTitleCtrl, decoration: const InputDecoration(hintText: 'Title (e.g. Registration)'))),
              const SizedBox(width: 8),
              Expanded(child: TextField(controller: customAmountCtrl, decoration: const InputDecoration(hintText: 'Amount'), keyboardType: TextInputType.number)),
              const SizedBox(width: 8),
              Pressable(radius: 14, onTap: () {
                if (customTitleCtrl.text.trim().isEmpty || customAmountCtrl.text.trim().isEmpty) return;
                setState(() { customItems.add({'title': customTitleCtrl.text.trim(), 'amount': double.tryParse(customAmountCtrl.text.trim()) ?? 0}); customTitleCtrl.clear(); customAmountCtrl.clear(); });
              }, child: Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(gradient: AppTheme.fireGradient, borderRadius: BorderRadius.circular(14)), child: const Icon(Icons.add_rounded, color: Colors.white, size: 20))),
            ]),
            if (customItems.isNotEmpty) ...[
              const SizedBox(height: 10),
              ...customItems.asMap().entries.map((e) => Container(margin: const EdgeInsets.only(bottom: 6), padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10), decoration: BoxDecoration(color: tt.surfaceAlt, borderRadius: BorderRadius.circular(12)), child: Row(children: [Expanded(child: Text(e.value['title'], style: context.typo.titleSmall?.copyWith(fontSize: 13))), Text('₹${e.value['amount']}', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w700)), const SizedBox(width: 8), GestureDetector(onTap: () => setState(() => customItems.removeAt(e.key)), child: const Icon(Icons.close_rounded, size: 18, color: AppTheme.danger))]))),
            ],
          ]),
          const SizedBox(height: 14),
          _summaryCard(tt),
          const SizedBox(height: 14),
          TextField(controller: notesCtrl, decoration: const InputDecoration(labelText: 'Notes (optional)', hintText: 'Thank you note…'), maxLines: 2),
          const SizedBox(height: 22),
          FireButton(label: 'Create Invoice  •  ₹${total.toStringAsFixed(0)}', icon: Icons.check_rounded, loading: loading, onPressed: loading ? null : _submit),
        ]),
      ),
    );
  }

  Widget _qtyBtn(IconData icon, VoidCallback? onTap) => Material(color: context.tokens.surface, shape: const CircleBorder(), child: InkWell(customBorder: const CircleBorder(), onTap: onTap, child: Padding(padding: const EdgeInsets.all(5), child: Icon(icon, size: 16, color: onTap == null ? context.tokens.textTertiary : AppTheme.brand))));

  Widget _card(String title, IconData icon, Color color, List<Widget> children) => SurfaceCard(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Row(children: [IconBadge(icon, color: color, size: 34, iconSize: 17), const SizedBox(width: 10), Text(title, style: context.typo.titleMedium)]), const SizedBox(height: 14), ...children]));

  Widget _summaryCard(dynamic tt) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(22)),
      child: Column(children: [
        Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text('Subtotal', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.8), fontWeight: FontWeight.w600)), Text('₹${total.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700))]),
        Divider(height: 22, color: Colors.white.withOpacity(0.12)),
        TextField(controller: paidCtrl, style: const TextStyle(color: Colors.white), decoration: InputDecoration(labelText: 'Amount Received', labelStyle: TextStyle(color: Colors.white.withOpacity(0.7)), prefixText: '₹ ', prefixStyle: const TextStyle(color: Colors.white), fillColor: Colors.white.withOpacity(0.08)), keyboardType: TextInputType.number, onChanged: (_) => setState(() {})),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(value: paymentMethod, dropdownColor: const Color(0xFF241B17), style: const TextStyle(color: Colors.white), decoration: InputDecoration(labelText: 'Payment Method', labelStyle: TextStyle(color: Colors.white.withOpacity(0.7)), fillColor: Colors.white.withOpacity(0.08)), items: const [DropdownMenuItem(value: 'cash', child: Text('Cash')), DropdownMenuItem(value: 'upi', child: Text('UPI')), DropdownMenuItem(value: 'card', child: Text('Card')), DropdownMenuItem(value: 'online', child: Text('Online'))], onChanged: (v) => setState(() => paymentMethod = v ?? 'cash')),
        const SizedBox(height: 14),
        Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: (balance > 0 ? AppTheme.danger : AppTheme.success).withOpacity(0.18), borderRadius: BorderRadius.circular(12)), child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [Text(balance > 0 ? 'Balance Due' : 'Change / Paid', style: GoogleFonts.poppins(fontWeight: FontWeight.w700, color: balance > 0 ? AppTheme.danger : AppTheme.success)), Text('₹${balance.abs().toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontWeight: FontWeight.w700, fontSize: 16, color: balance > 0 ? AppTheme.danger : AppTheme.success))])),
      ]),
    );
  }

  Future<void> _submit() async {
    if (selectedMember == null) { Toast.error(context, 'Select member first'); return; }
    if (total <= 0) { Toast.error(context, 'Add at least one product or custom item'); return; }
    setState(() => loading = true);
    try {
      List<Map<String, dynamic>> items = [];
      selectedProducts.forEach((id, data) {
        if (data['checked'] == true) {
          final p = data['product'];
          final qty = data['qty'] ?? 1;
          final price = double.tryParse(p['price'].toString()) ?? 0;
          for (int i = 0; i < qty; i++) { items.add({'type_id': id, 'title': p['title'], 'amount': price, 'description': p['description'] ?? ''}); }
        }
      });
      for (var c in customItems) { items.add({'type_id': 0, 'title': c['title'], 'amount': c['amount'], 'description': 'Custom'}); }
      final paid = double.tryParse(paidCtrl.text.trim()) ?? 0;
      await ref.read(apiClientProvider).createInvoice({
        'user_id': selectedMember!['id'],
        'invoice_date': DateTime.now().toIso8601String().split('T')[0],
        'items': items,
        'paid_amount': paid,
        'payment_method': paymentMethod,
        'notes': notesCtrl.text.trim(),
      });
      if (mounted) { Toast.success(context, 'Invoice created'); Navigator.pop(context, true); }
    } catch (e) { if (mounted) Toast.error(context, 'Failed to create invoice'); }
    finally { if (mounted) setState(() => loading = false); }
  }
}
