import 'dart:math' as math;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class _MemberPaymentRow {
  final TextEditingController amountCtrl;
  String method;

  _MemberPaymentRow({String amount = '', this.method = 'cash'}) : amountCtrl = TextEditingController(text: amount);

  double get amount => double.tryParse(amountCtrl.text.trim()) ?? 0;

  void dispose() => amountCtrl.dispose();
}

class AddMemberScreen extends ConsumerStatefulWidget {
  const AddMemberScreen({super.key});
  @override
  ConsumerState<AddMemberScreen> createState() => _AddMemberScreenState();
}

class _AddMemberScreenState extends ConsumerState<AddMemberScreen> {
  final nameCtrl = TextEditingController();
  final emailCtrl = TextEditingController();
  final phoneCtrl = TextEditingController();
  final addressCtrl = TextEditingController();
  final cityCtrl = TextEditingController();
  final regFeeCtrl = TextEditingController();
  final discountCtrl = TextEditingController();
  final List<_MemberPaymentRow> paymentRows = [_MemberPaymentRow()];

  String? selectedPlan;
  String? selectedTrainer;
  String? selectedClass;
  String gender = 'male';
  String? selectedCategory;
  DateTime? startDate = DateTime.now();
  DateTime? expiryDate = DateTime.now().add(const Duration(days: 30));
  DateTime? dob;

  List plans = [];
  List trainers = [];
  List categories = [];
  List classes = [];
  bool loadingMeta = true;
  bool submitting = false;

  @override
  void initState() {
    super.initState();
    _loadMeta();
  }

  @override
  void dispose() {
    nameCtrl.dispose();
    emailCtrl.dispose();
    phoneCtrl.dispose();
    addressCtrl.dispose();
    cityCtrl.dispose();
    regFeeCtrl.dispose();
    discountCtrl.dispose();
    for (final row in paymentRows) {
      row.dispose();
    }
    super.dispose();
  }

  bool get _trainersAllowed {
    final user = ref.read(authProvider).user;
    final tier = user?['current_tier'];
    final tierCode = tier is Map ? (tier['code'] ?? '').toString().toLowerCase() : '';
    final features = user?['plan_features'];
    if (features is Map && features.containsKey('trainers_enabled')) {
      final value = features['trainers_enabled'];
      if (value is bool) return value;
      return !['0', 'false', 'no', 'disabled', 'coming_soon'].contains(value.toString().toLowerCase());
    }
    if (tierCode == 'bronze') return false;
    return true;
  }

  List<DropdownMenuItem<String>> _planItems() {
    return plans.whereType<Map>().map((p) {
      return DropdownMenuItem<String>(
        value: p['id'].toString(),
        child: Text('${p['title'] ?? 'Plan'} - ₹${p['amount'] ?? 0}', overflow: TextOverflow.ellipsis),
      );
    }).toList();
  }

  String? get _validSelectedPlan {
    if (selectedPlan == null) return null;
    return plans.any((p) => p is Map && p['id'].toString() == selectedPlan) ? selectedPlan : null;
  }

  List _extractList(Map<String, dynamic> res, String key, [String? altKey]) {
    final direct = res[key] ?? (altKey == null ? null : res[altKey]) ?? res['data'];
    if (direct is List) return direct;
    if (direct is Map) {
      final nested = direct[key] ?? (altKey == null ? null : direct[altKey]) ?? direct['data'] ?? direct['items'] ?? direct['rows'];
      if (nested is List) return nested;
    }
    return const [];
  }

  Future<void> _loadMeta() async {
    setState(() => loadingMeta = true);
    final api = ref.read(apiClientProvider);
    List loadedPlans = [];
    List loadedTrainers = [];
    List loadedCategories = [];
    List loadedClasses = [];

    try {
      final res = await api.getMemberships();
      loadedPlans = _extractList(res, 'memberships', 'plans');
    } catch (e) {
      // ignore: avoid_print
      print('AddMember plans error: $e');
    }

    if (_trainersAllowed) {
      try {
        final res = await api.getTrainers();
        loadedTrainers = _extractList(res, 'trainers');
      } catch (e) {
        // Trainer module can be locked by plan; do not block plans/classes.
        // ignore: avoid_print
        print('AddMember trainers skipped/error: $e');
      }
    }

    try {
      final res = await api.getCategories();
      loadedCategories = _extractList(res, 'categories');
    } catch (e) {
      // ignore: avoid_print
      print('AddMember categories error: $e');
    }

    try {
      final res = await api.getClasses();
      loadedClasses = _extractList(res, 'classes');
    } catch (e) {
      // ignore: avoid_print
      print('AddMember classes error: $e');
    }

    if (!mounted) return;
    setState(() {
      plans = loadedPlans;
      trainers = loadedTrainers;
      categories = loadedCategories;
      classes = loadedClasses;
      if (!_trainersAllowed) selectedTrainer = null;
      loadingMeta = false;
    });
  }

  double get _planAmount {
    if (selectedPlan == null) return 0;
    try {
      final plan = plans.firstWhere((p) => p is Map && p['id'].toString() == selectedPlan) as Map;
      return double.tryParse(plan['amount'].toString()) ?? 0;
    } catch (_) {
      return 0;
    }
  }

  double get _classFee {
    if (selectedClass == null) return 0;
    try {
      final cls = classes.firstWhere((c) => c is Map && c['id'].toString() == selectedClass) as Map;
      return double.tryParse((cls['fees'] ?? 0).toString()) ?? 0;
    } catch (_) {
      return 0;
    }
  }

  int _planMonths(Map plan) {
    final pkg = (plan['package'] ?? '').toString().toLowerCase().trim();
    if (pkg.isEmpty) return 1;
    final n = int.tryParse(pkg);
    if (n != null && n > 0) return n;
    if (pkg.contains('year') || pkg.contains('annual') || pkg.contains('12')) return 12;
    if (pkg.contains('half') || pkg.contains('6')) return 6;
    if (pkg.contains('quarter') || pkg.contains('3')) return 3;
    if (pkg.contains('2 month') || pkg.contains('bimonth')) return 2;
    if (pkg.contains('week')) return 0;
    return 1;
  }

  DateTime _addMonthsNoOverflow(DateTime start, int months) {
    final targetMonth = start.month + months;
    final year = start.year + ((targetMonth - 1) ~/ 12);
    final month = ((targetMonth - 1) % 12) + 1;
    final lastDay = DateTime(year, month + 1, 0).day;
    return DateTime(year, month, start.day > lastDay ? lastDay : start.day);
  }

  void _recalcExpiry() {
    if (selectedPlan == null || startDate == null) return;
    Map? plan;
    try {
      plan = plans.firstWhere((p) => p is Map && p['id'].toString() == selectedPlan) as Map;
    } catch (_) {
      plan = null;
    }
    if (plan == null) return;
    final months = _planMonths(plan);
    if (months <= 0) return;
    expiryDate = _addMonthsNoOverflow(startDate!, months).subtract(const Duration(days: 1));
  }

  double get _regFee => double.tryParse(regFeeCtrl.text.trim()) ?? 0;
  double get _discount => double.tryParse(discountCtrl.text.trim()) ?? 0;
  double get _paid => paymentRows.fold<double>(0, (sum, row) => sum + row.amount);
  double get _subtotal => _planAmount + _classFee + _regFee;
  double get _total => math.max(0, _subtotal - _discount);
  double get _balance => _total - _paid;

  String _dateYmd(DateTime d) => '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

  String _friendlyError(dynamic e) {
    final msg = e.toString();
    if (msg.contains('connection') || msg.contains('SocketException') || msg.contains('Connection refused')) return 'No internet. Check connection.';
    if (msg.contains('401')) return 'Session expired. Login again.';
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map && data['error'] != null) return data['error'].toString();
      if (data is Map && data['message'] != null) return data['message'].toString();
    } catch (_) {}
    return 'Failed. Please try again.';
  }

  List<Map<String, dynamic>> _paymentPayload() {
    return paymentRows
        .where((row) => row.amount > 0)
        .map((row) => {
              'amount': row.amount,
              'payment_type': row.method,
              'payment_date': startDate != null ? _dateYmd(startDate!) : _dateYmd(DateTime.now()),
            })
        .toList();
  }

  Future<void> _submit() async {
    if (nameCtrl.text.trim().isEmpty) {
      Toast.error(context, 'Name required');
      return;
    }
    final phone = phoneCtrl.text.trim();
    if (phone.isEmpty || phone.length != 10 || !RegExp(r'^[6-9]').hasMatch(phone)) {
      Toast.error(context, 'Enter valid 10 digit phone');
      return;
    }
    if (selectedPlan == null || selectedPlan == '0' || selectedPlan!.isEmpty) {
      Toast.error(context, 'Please select a membership plan');
      return;
    }
    if (_discount < 0 || _discount > _subtotal) {
      Toast.error(context, 'Discount cannot exceed subtotal ₹${_subtotal.toStringAsFixed(0)}');
      return;
    }
    if (_paid > _total) {
      Toast.error(context, 'Total payment cannot exceed due amount ₹${_total.toStringAsFixed(0)}');
      return;
    }

    setState(() => submitting = true);
    try {
      final api = ref.read(apiClientProvider);
      final payments = _paymentPayload();
      await api.createMember({
        'name': nameCtrl.text.trim(),
        'email': emailCtrl.text.trim(),
        'phone_number': phoneCtrl.text.trim(),
        'address': addressCtrl.text.trim(),
        'city': cityCtrl.text.trim(),
        'dob': dob != null ? _dateYmd(dob!) : null,
        'gender': gender,
        'membership_plan': int.tryParse(selectedPlan ?? '0') ?? 0,
        'trainer_assign': _trainersAllowed ? (int.tryParse(selectedTrainer ?? '0') ?? 0) : 0,
        'class_id': int.tryParse(selectedClass ?? '0') ?? 0,
        'category': int.tryParse(selectedCategory ?? '0') ?? 0,
        'membership_start_date': startDate != null ? _dateYmd(startDate!) : null,
        'membership_expiry_date': expiryDate != null ? _dateYmd(expiryDate!) : null,
        'paid_amount': _paid,
        'payments': payments,
        'registration_fee': _regFee,
        'discount_amount': _discount,
        'payment_method': payments.isNotEmpty ? payments.first['payment_type'] : 'cash',
        'fitness_goal': '',
      });

      if (mounted) {
        Toast.success(context, 'Member added successfully');
        Navigator.pop(context, true);
      }
    } catch (e) {
      if (mounted) Toast.error(context, _friendlyError(e));
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  void _addPaymentRow() {
    setState(() => paymentRows.add(_MemberPaymentRow()));
  }

  void _removePaymentRow(int index) {
    if (paymentRows.length <= 1) return;
    final row = paymentRows.removeAt(index);
    row.dispose();
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final planItems = _planItems();
    return Scaffold(
      appBar: AppBar(title: const Text('Add Member')),
      bottomNavigationBar: const AppBottomNav(selected: 1),
      body: loadingMeta
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _card('Personal Info', Icons.person_rounded, AppTheme.brand, [
                    TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name*', prefixIcon: Icon(Icons.person_outline_rounded))),
                    const SizedBox(height: 12),
                    TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone*', prefixIcon: Icon(Icons.phone_rounded)), keyboardType: TextInputType.phone),
                    const SizedBox(height: 12),
                    TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email optional', prefixIcon: Icon(Icons.email_outlined)), keyboardType: TextInputType.emailAddress),
                    const SizedBox(height: 12),
                    Row(children: [
                      Expanded(child: InkWell(onTap: () async { final d = await showDatePicker(context: context, initialDate: dob ?? DateTime(1995, 1, 1), firstDate: DateTime(1950), lastDate: DateTime.now()); if (d != null) setState(() => dob = d); }, child: InputDecorator(decoration: const InputDecoration(labelText: 'DOB', prefixIcon: Icon(Icons.cake_outlined)), child: Text(dob != null ? '${dob!.day.toString().padLeft(2, '0')}-${dob!.month.toString().padLeft(2, '0')}-${dob!.year}' : 'Select', style: context.typo.bodyLarge)))),
                      const SizedBox(width: 12),
                      Expanded(child: DropdownButtonFormField<String>(value: gender, decoration: const InputDecoration(labelText: 'Gender', prefixIcon: Icon(Icons.wc_rounded)), items: const [DropdownMenuItem(value: 'male', child: Text('Male')), DropdownMenuItem(value: 'female', child: Text('Female')), DropdownMenuItem(value: 'other', child: Text('Other'))], onChanged: (v) => setState(() => gender = v ?? 'male'))),
                    ]),
                    const SizedBox(height: 12),
                    TextField(controller: addressCtrl, decoration: const InputDecoration(labelText: 'Address', prefixIcon: Icon(Icons.location_on_outlined))),
                    const SizedBox(height: 12),
                    TextField(controller: cityCtrl, decoration: const InputDecoration(labelText: 'City', prefixIcon: Icon(Icons.location_city_rounded))),
                  ]),
                  const SizedBox(height: 14),
                  _card('Membership', Icons.card_membership_rounded, AppTheme.info, [
                    DropdownButtonFormField<String>(
                      value: _validSelectedPlan,
                      decoration: const InputDecoration(labelText: 'Membership Plan*', prefixIcon: Icon(Icons.card_membership_rounded)),
                      hint: Text(planItems.isEmpty ? 'No plans found' : 'Select Plan'),
                      items: planItems,
                      onChanged: planItems.isEmpty ? null : (v) => setState(() { selectedPlan = v; _recalcExpiry(); }),
                    ),
                    const SizedBox(height: 12),
                    if (_trainersAllowed) ...[
                      DropdownButtonFormField<String>(value: selectedTrainer, decoration: const InputDecoration(labelText: 'Assign Trainer', prefixIcon: Icon(Icons.sports_martial_arts_rounded)), hint: const Text('Select Trainer'), items: trainers.whereType<Map>().map((t) => DropdownMenuItem<String>(value: t['id'].toString(), child: Text(t['name']?.toString() ?? 'Trainer'))).toList(), onChanged: (v) => setState(() => selectedTrainer = v)),
                      const SizedBox(height: 12),
                    ],
                    DropdownButtonFormField<String>(value: selectedClass, decoration: const InputDecoration(labelText: 'Choose Class', prefixIcon: Icon(Icons.self_improvement_rounded)), hint: const Text('Select Class'), items: classes.whereType<Map>().map((c) => DropdownMenuItem<String>(value: c['id'].toString(), child: Text('${c['title']} - ₹${c['fees'] ?? 0}'))).toList(), onChanged: (v) => setState(() => selectedClass = v)),
                    const SizedBox(height: 12),
                    Row(children: [
                      Expanded(child: InkWell(onTap: () async { final d = await showDatePicker(context: context, initialDate: startDate ?? DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2030)); if (d != null) setState(() { startDate = d; _recalcExpiry(); }); }, child: InputDecorator(decoration: const InputDecoration(labelText: 'Start Date'), child: Text(startDate != null ? '${startDate!.day}-${startDate!.month}-${startDate!.year}' : '-', style: context.typo.bodyLarge)))),
                      const SizedBox(width: 12),
                      Expanded(child: InkWell(onTap: () async { final d = await showDatePicker(context: context, initialDate: expiryDate ?? DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2030)); if (d != null) setState(() => expiryDate = d); }, child: InputDecorator(decoration: const InputDecoration(labelText: 'Expiry Date'), child: Text(expiryDate != null ? '${expiryDate!.day}-${expiryDate!.month}-${expiryDate!.year}' : '-', style: context.typo.bodyLarge)))),
                    ]),
                  ]),
                  const SizedBox(height: 14),
                  _card('Payments', Icons.payments_rounded, AppTheme.success, [
                    TextField(controller: regFeeCtrl, decoration: const InputDecoration(labelText: 'Registration Fee', prefixText: '₹ '), keyboardType: TextInputType.number, onChanged: (_) => setState(() {})),
                    const SizedBox(height: 12),
                    TextField(controller: discountCtrl, decoration: const InputDecoration(labelText: 'Discount', prefixText: '₹ ', helperText: 'Discount cannot exceed subtotal'), keyboardType: TextInputType.number, onChanged: (_) => setState(() {})),
                    const SizedBox(height: 14),
                    ...paymentRows.asMap().entries.map((entry) => _paymentRow(entry.key, entry.value)),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(onPressed: _addPaymentRow, icon: const Icon(Icons.add_rounded), label: const Text('Add another payment')),
                    if (_paid > _total)
                      Padding(padding: const EdgeInsets.only(top: 8), child: Text('Payments exceed total by ₹${(_paid - _total).toStringAsFixed(0)}', style: context.typo.bodySmall?.copyWith(color: AppTheme.danger, fontWeight: FontWeight.w700))),
                  ]),
                  const SizedBox(height: 14),
                  _summaryCard(),
                  const SizedBox(height: 22),
                  FireButton(label: 'Add Member  •  ₹${_total.toStringAsFixed(0)}', icon: Icons.check_rounded, loading: submitting, onPressed: submitting ? null : _submit),
                ],
              ),
            ),
    );
  }

  Widget _paymentRow(int index, _MemberPaymentRow row) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(children: [
        Expanded(flex: 5, child: TextField(controller: row.amountCtrl, decoration: InputDecoration(labelText: 'Payment ${index + 1}', prefixText: '₹ '), keyboardType: TextInputType.number, onChanged: (_) => setState(() {}))),
        const SizedBox(width: 8),
        Expanded(flex: 5, child: DropdownButtonFormField<String>(value: row.method, decoration: const InputDecoration(labelText: 'Method'), items: const [DropdownMenuItem(value: 'cash', child: Text('Cash')), DropdownMenuItem(value: 'upi', child: Text('UPI')), DropdownMenuItem(value: 'card', child: Text('Card')), DropdownMenuItem(value: 'online', child: Text('Online'))], onChanged: (v) => setState(() => row.method = v ?? 'cash'))),
        IconButton(onPressed: paymentRows.length <= 1 ? null : () => _removePaymentRow(index), icon: const Icon(Icons.close_rounded), color: AppTheme.danger),
      ]),
    );
  }

  Widget _card(String title, IconData icon, Color color, List<Widget> children) {
    return SurfaceCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [IconBadge(icon, color: color, size: 34, iconSize: 17), const SizedBox(width: 10), Text(title, style: context.typo.titleMedium)]),
        const SizedBox(height: 16),
        ...children,
      ]),
    );
  }

  Widget _summaryCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(22)),
      child: Column(children: [
        _sumRow('Plan Amount', _planAmount),
        _sumRow('Class Fee', _classFee),
        _sumRow('Registration Fee', _regFee),
        _sumRow('Subtotal', _subtotal),
        if (_discount > 0) _sumRow('Discount', -_discount, color: AppTheme.warning),
        Divider(height: 22, color: Colors.white.withOpacity(0.12)),
        _sumRow('Total After Discount', _total, bold: true),
        const SizedBox(height: 8),
        _sumRow('Paid', _paid, color: AppTheme.success),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: (_balance > 0 ? AppTheme.danger : AppTheme.success).withOpacity(0.18), borderRadius: BorderRadius.circular(12)),
          child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
            Text(_balance > 0 ? 'Balance Due' : 'Fully Paid', style: GoogleFonts.poppins(fontSize: 13, fontWeight: FontWeight.w700, color: _balance > 0 ? AppTheme.danger : AppTheme.success)),
            Text('₹${_balance.abs().toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: 16, fontWeight: FontWeight.w700, color: _balance > 0 ? AppTheme.danger : AppTheme.success)),
          ]),
        ),
      ]),
    );
  }

  Widget _sumRow(String label, double value, {bool bold = false, Color? color}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(mainAxisAlignment: MainAxisAlignment.spaceBetween, children: [
        Text(label, style: GoogleFonts.poppins(fontSize: bold ? 14 : 12.5, fontWeight: bold ? FontWeight.w700 : FontWeight.w500, color: color ?? Colors.white.withOpacity(bold ? 1 : 0.7))),
        Text('₹${value.toStringAsFixed(0)}', style: GoogleFonts.spaceGrotesk(fontSize: bold ? 16 : 13.5, fontWeight: FontWeight.w700, color: color ?? Colors.white)),
      ]),
    );
  }
}
