import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class AddTrainerScreen extends ConsumerStatefulWidget {
  final Map<String, dynamic>? trainer;
  const AddTrainerScreen({super.key, this.trainer});

  @override
  ConsumerState<AddTrainerScreen> createState() => _AddTrainerScreenState();
}

class _AddTrainerScreenState extends ConsumerState<AddTrainerScreen> {
  late final TextEditingController nameCtrl;
  late final TextEditingController emailCtrl;
  late final TextEditingController phoneCtrl;
  late final TextEditingController qualCtrl;
  late final TextEditingController specCtrl;
  late final TextEditingController expCtrl;
  late final TextEditingController salaryCtrl;
  late final TextEditingController addressCtrl;
  late final TextEditingController cityCtrl;
  late final TextEditingController bioCtrl;
  late final TextEditingController emergencyCtrl;

  String gender = 'male';
  DateTime? dob;
  DateTime? joining;
  bool submitting = false;

  bool get editing => widget.trainer != null;

  @override
  void initState() {
    super.initState();
    final t = widget.trainer ?? <String, dynamic>{};
    nameCtrl = TextEditingController(text: _v(t, 'name'));
    emailCtrl = TextEditingController(text: _v(t, 'email'));
    phoneCtrl = TextEditingController(text: _v(t, 'phone_number'));
    qualCtrl = TextEditingController(text: _v(t, 'qualification'));
    specCtrl = TextEditingController(text: _v(t, 'specialization'));
    expCtrl = TextEditingController(text: _v(t, 'experience_years', fallback: '0'));
    salaryCtrl = TextEditingController(text: _v(t, 'salary', fallback: '0'));
    addressCtrl = TextEditingController(text: _v(t, 'address'));
    cityCtrl = TextEditingController(text: _v(t, 'city'));
    bioCtrl = TextEditingController(text: _v(t, 'bio'));
    emergencyCtrl = TextEditingController(text: _v(t, 'emergency_contact'));
    final g = _v(t, 'gender', fallback: 'male').toLowerCase();
    gender = ['male', 'female', 'other'].contains(g) ? g : 'male';
    dob = _parseDate(_field(t, 'dob'));
    joining = _parseDate(_field(t, 'joining_date')) ?? DateTime.now();
  }

  @override
  void dispose() {
    nameCtrl.dispose();
    emailCtrl.dispose();
    phoneCtrl.dispose();
    qualCtrl.dispose();
    specCtrl.dispose();
    expCtrl.dispose();
    salaryCtrl.dispose();
    addressCtrl.dispose();
    cityCtrl.dispose();
    bioCtrl.dispose();
    emergencyCtrl.dispose();
    super.dispose();
  }

  dynamic _field(Map t, String key) {
    if (t[key] != null) return t[key];
    final d = t['trainerDetails'] ?? t['trainer_details'] ?? t['trainer_detail'];
    if (d is Map) return d[key];
    return null;
  }

  String _v(Map t, String key, {String fallback = ''}) {
    final value = _field(t, key);
    if (value == null || value.toString() == 'null') return fallback;
    return value.toString();
  }

  String _friendlyError(dynamic e) {
    try {
      final data = (e as dynamic).response?.data;
      if (data is Map) {
        final msg = data['error'] ?? data['message'];
        if (msg != null) return msg.toString();
        if (data['errors'] is Map) {
          final first = (data['errors'] as Map).values.first;
          if (first is List && first.isNotEmpty) return first.first.toString();
          return first.toString();
        }
      }
    } catch (_) {}
    final msg = e.toString();
    if (msg.contains('connection') || msg.contains('SocketException')) return 'No internet. Please check connection.';
    return 'Failed. Please try again.';
  }

  Future<void> _submit() async {
    final phone = phoneCtrl.text.replaceAll(RegExp(r'[^0-9]'), '');
    if (nameCtrl.text.trim().isEmpty) { Toast.error(context, 'Name required'); return; }
    if (phone.length != 10 || !RegExp(r'^[6-9]').hasMatch(phone)) { Toast.error(context, 'Valid 10-digit phone required'); return; }

    setState(() => submitting = true);
    final data = {
      'name': nameCtrl.text.trim(),
      'email': emailCtrl.text.trim(),
      'phone_number': phone,
      'gender': gender,
      'dob': _dateValue(dob),
      'qualification': qualCtrl.text.trim(),
      'specialization': specCtrl.text.trim(),
      'experience_years': int.tryParse(expCtrl.text.trim()) ?? 0,
      'joining_date': _dateValue(joining),
      'salary': double.tryParse(salaryCtrl.text.trim()) ?? 0,
      'address': addressCtrl.text.trim(),
      'city': cityCtrl.text.trim(),
      'bio': bioCtrl.text.trim(),
      'emergency_contact': emergencyCtrl.text.trim(),
    };

    try {
      final api = ref.read(apiClientProvider);
      if (editing) {
        final id = int.parse((widget.trainer!['id'] ?? widget.trainer!['user_id']).toString());
        await api.updateTrainer(id, data);
      } else {
        await api.createTrainer(data);
      }
      if (!mounted) return;
      Toast.success(context, editing ? 'Trainer updated' : 'Trainer added • Password 1234@paas');
      Navigator.pop(context, true);
    } catch (e) {
      if (mounted) Toast.error(context, _friendlyError(e));
    } finally {
      if (mounted) setState(() => submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(editing ? 'Edit Trainer' : 'Add Trainer')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 40),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          _hero(),
          const SizedBox(height: 14),
          _card('Basic Information', Icons.person_outline_rounded, AppTheme.brand, [
            TextField(controller: nameCtrl, decoration: const InputDecoration(labelText: 'Full Name*', prefixIcon: Icon(Icons.person_outline_rounded))),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: TextField(controller: phoneCtrl, decoration: const InputDecoration(labelText: 'Phone*', prefixIcon: Icon(Icons.phone_rounded)), keyboardType: TextInputType.phone, inputFormatters: [FilteringTextInputFormatter.digitsOnly, LengthLimitingTextInputFormatter(10)])),
              const SizedBox(width: 12),
              Expanded(child: DropdownButtonFormField<String>(value: gender, decoration: const InputDecoration(labelText: 'Gender'), items: const [DropdownMenuItem(value: 'male', child: Text('Male')), DropdownMenuItem(value: 'female', child: Text('Female')), DropdownMenuItem(value: 'other', child: Text('Other'))], onChanged: (v) => setState(() => gender = v ?? 'male'))),
            ]),
            const SizedBox(height: 12),
            TextField(controller: emailCtrl, decoration: const InputDecoration(labelText: 'Email (optional)', prefixIcon: Icon(Icons.email_outlined)), keyboardType: TextInputType.emailAddress),
            const SizedBox(height: 12),
            _dateBox('DOB', dob, () async {
              final d = await showDatePicker(context: context, initialDate: dob ?? DateTime(1995), firstDate: DateTime(1950), lastDate: DateTime.now());
              if (d != null) setState(() => dob = d);
            }),
          ]),
          const SizedBox(height: 14),
          _card('Professional Details', Icons.school_rounded, AppTheme.success, [
            TextField(controller: qualCtrl, decoration: const InputDecoration(labelText: 'Qualification', prefixIcon: Icon(Icons.school_outlined))),
            const SizedBox(height: 12),
            TextField(controller: specCtrl, decoration: const InputDecoration(labelText: 'Specialization', prefixIcon: Icon(Icons.fitness_center_rounded), hintText: 'Strength, Yoga, Cardio...')),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: TextField(controller: expCtrl, decoration: const InputDecoration(labelText: 'Experience (years)'), keyboardType: TextInputType.number, inputFormatters: [FilteringTextInputFormatter.digitsOnly])),
              const SizedBox(width: 12),
              Expanded(child: TextField(controller: salaryCtrl, decoration: const InputDecoration(labelText: 'Salary', prefixText: '₹ '), keyboardType: const TextInputType.numberWithOptions(decimal: true))),
            ]),
            const SizedBox(height: 12),
            _dateBox('Joining Date', joining, () async {
              final d = await showDatePicker(context: context, initialDate: joining ?? DateTime.now(), firstDate: DateTime(2020), lastDate: DateTime(2035));
              if (d != null) setState(() => joining = d);
            }),
          ]),
          const SizedBox(height: 14),
          _card('Contact & Notes', Icons.location_on_outlined, AppTheme.info, [
            TextField(controller: addressCtrl, decoration: const InputDecoration(labelText: 'Address', prefixIcon: Icon(Icons.location_on_outlined))),
            const SizedBox(height: 12),
            Row(children: [
              Expanded(child: TextField(controller: cityCtrl, decoration: const InputDecoration(labelText: 'City'))),
              const SizedBox(width: 12),
              Expanded(child: TextField(controller: emergencyCtrl, decoration: const InputDecoration(labelText: 'Emergency Contact'), keyboardType: TextInputType.phone)),
            ]),
            const SizedBox(height: 12),
            TextField(controller: bioCtrl, decoration: const InputDecoration(labelText: 'Bio / Notes'), minLines: 2, maxLines: 4),
          ]),
          const SizedBox(height: 18),
          if (!editing)
            SurfaceCard(
              color: AppTheme.info.withOpacity(0.08),
              border: Border.all(color: AppTheme.info.withOpacity(0.18)),
              child: Row(children: [
                const Icon(Icons.key_rounded, color: AppTheme.info),
                const SizedBox(width: 10),
                Expanded(child: Text('Trainer can login with phone/email and default password: 1234@paas', style: context.typo.bodySmall?.copyWith(color: AppTheme.info, fontWeight: FontWeight.w700))),
              ]),
            ),
          const SizedBox(height: 18),
          FireButton(label: editing ? 'Save Trainer' : 'Add Trainer', icon: editing ? Icons.save_rounded : Icons.person_add_rounded, loading: submitting, onPressed: submitting ? null : _submit),
        ]),
      ),
    );
  }

  Widget _hero() {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(gradient: AppTheme.darkHeroGradient, borderRadius: BorderRadius.circular(24)),
      child: Row(children: [
        IconBadge(editing ? Icons.edit_rounded : Icons.person_add_rounded, color: AppTheme.brandAmber, size: 54, iconSize: 28),
        const SizedBox(width: 14),
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(editing ? 'Update Trainer' : 'New Trainer Profile', style: GoogleFonts.spaceGrotesk(color: Colors.white, fontSize: 23, fontWeight: FontWeight.w800)),
          const SizedBox(height: 4),
          Text(editing ? 'Update professional and contact details.' : 'Create trainer profile and Flutter app login.', style: GoogleFonts.poppins(color: Colors.white.withOpacity(0.68), fontSize: 12.5)),
        ])),
      ]),
    );
  }

  Widget _card(String title, IconData icon, Color color, List<Widget> children) {
    return SurfaceCard(
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [IconBadge(icon, color: color, size: 38, iconSize: 18), const SizedBox(width: 10), Text(title, style: context.typo.titleMedium)]),
        const SizedBox(height: 14),
        ...children,
      ]),
    );
  }

  Widget _dateBox(String label, DateTime? value, VoidCallback onTap) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: InputDecorator(
        decoration: InputDecoration(labelText: label, prefixIcon: const Icon(Icons.calendar_today_rounded)),
        child: Row(children: [
          Expanded(child: Text(value == null ? 'Select' : DateFormatter.formatDate(_dateValue(value)))),
          Icon(Icons.edit_calendar_rounded, size: 18, color: context.tokens.textTertiary),
        ]),
      ),
    );
  }

  DateTime? _parseDate(dynamic v) {
    if (v == null || v.toString().isEmpty || v.toString() == 'null') return null;
    try { return DateTime.parse(v.toString().split('T').first); } catch (_) { return null; }
  }

  String? _dateValue(DateTime? d) {
    if (d == null) return null;
    return '${d.year}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
  }
}
