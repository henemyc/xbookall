import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class GymProfileScreen extends ConsumerStatefulWidget {
  const GymProfileScreen({super.key});
  @override
  ConsumerState<GymProfileScreen> createState() => _GymProfileScreenState();
}

class _GymProfileScreenState extends ConsumerState<GymProfileScreen> {
  final nameCtrl = TextEditingController();
  final phoneCtrl = TextEditingController();
  final emailCtrl = TextEditingController();
  final addressCtrl = TextEditingController();
  bool loading = true;
  bool saving = false;

  @override
  void initState() { super.initState(); _load(); }

  Future<void> _load() async {
    setState(() => loading = true);
    try {
      final res = await ref.read(apiClientProvider).getGymProfile();
      final data = (res is Map) ? Map<String, dynamic>.from(res) : <String, dynamic>{};
      
      // Handle both legacy 'settings' and new 'profile' shape
      Map<String, dynamic> profile = {};
      if (data['profile'] is Map) {
        profile = Map<String, dynamic>.from(data['profile']);
      } else if (data['settings'] is Map) {
        profile = Map<String, dynamic>.from(data['settings']);
      } else {
        profile = data;
      }
      
      setState(() {
        nameCtrl.text = profile['company_name'] ?? profile['gym_name'] ?? profile['name'] ?? '';
        phoneCtrl.text = profile['company_phone'] ?? profile['phone'] ?? '';
        emailCtrl.text = profile['company_email'] ?? profile['email'] ?? '';
        addressCtrl.text = profile['company_address'] ?? profile['address'] ?? '';
        loading = false;
      });
    } catch (e) { setState(() => loading = false); }
  }

  Future<void> _save() async {
    setState(() => saving = true);
    try {
      await ref.read(apiClientProvider).updateSettings({
        'company_name': nameCtrl.text.trim(),
        'company_phone': phoneCtrl.text.trim(),
        'company_email': emailCtrl.text.trim(),
        'company_address': addressCtrl.text.trim(),
      });
      if (mounted) Toast.success(context, 'Gym settings saved');
    } catch (e) { if (mounted) Toast.error(context, 'Failed to save'); }
    finally { if (mounted) setState(() => saving = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Gym Profile')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Center(child: IconBadge(Icons.fitness_center_rounded, color: AppTheme.brand, size: 68, iconSize: 34)),
                const SizedBox(height: 22),
                _field('Gym Name', nameCtrl, Icons.fitness_center_rounded),
                const SizedBox(height: 16),
                _field('Gym Contact', phoneCtrl, Icons.call_rounded),
                const SizedBox(height: 16),
                _field('Gym Email', emailCtrl, Icons.email_outlined),
                const SizedBox(height: 16),
                _field('Gym Address', addressCtrl, Icons.location_on_outlined, maxLines: 2),
                const SizedBox(height: 26),
                FireButton(label: 'Save Gym Settings', loading: saving, onPressed: saving ? null : _save),
              ]),
            ),
    );
  }

  Widget _field(String label, TextEditingController ctrl, IconData icon, {int maxLines = 1}) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(label, style: context.typo.labelMedium?.copyWith(color: context.tokens.textSecondary, fontWeight: FontWeight.w600)),
      const SizedBox(height: 7),
      TextField(controller: ctrl, maxLines: maxLines, decoration: InputDecoration(prefixIcon: Icon(icon))),
    ]);
  }
}
