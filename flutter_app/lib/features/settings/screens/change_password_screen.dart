import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final currentCtrl = TextEditingController();
  final newCtrl = TextEditingController();
  final confirmCtrl = TextEditingController();
  bool loading = false;
  bool obscure = true;

  Future<void> _change() async {
    if (newCtrl.text.length < 6) { Toast.error(context, 'New password must be at least 6 chars'); return; }
    if (newCtrl.text != confirmCtrl.text) { Toast.error(context, 'Passwords do not match'); return; }
    setState(() => loading = true);
    try {
      await ref.read(apiClientProvider).changePassword(
        currentPassword: currentCtrl.text.trim(),
        newPassword: newCtrl.text.trim(),
      );
      if (mounted) { Toast.success(context, 'Password changed successfully'); Navigator.pop(context); }
    } catch (e) {
      if (mounted) {
        String msg = 'Failed';
        try { msg = (e as dynamic).response?.data['error'] ?? e.toString(); } catch (_) {}
        Toast.error(context, msg);
      }
    } finally { if (mounted) setState(() => loading = false); }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Change Password')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Center(child: IconBadge(Icons.lock_reset_rounded, color: AppTheme.brand, size: 68, iconSize: 34)),
          const SizedBox(height: 20),
          _label('Current Password'),
          const SizedBox(height: 7),
          TextField(controller: currentCtrl, obscureText: obscure, decoration: InputDecoration(prefixIcon: const Icon(Icons.lock_outline_rounded), suffixIcon: IconButton(icon: Icon(obscure ? Icons.visibility_off_rounded : Icons.visibility_rounded, size: 20), onPressed: () => setState(() => obscure = !obscure)))),
          const SizedBox(height: 16),
          _label('New Password'),
          const SizedBox(height: 7),
          TextField(controller: newCtrl, obscureText: obscure, decoration: const InputDecoration(prefixIcon: Icon(Icons.lock_rounded))),
          const SizedBox(height: 16),
          _label('Confirm New Password'),
          const SizedBox(height: 7),
          TextField(controller: confirmCtrl, obscureText: obscure, decoration: const InputDecoration(prefixIcon: Icon(Icons.lock_reset_rounded))),
          const SizedBox(height: 26),
          FireButton(label: 'Update Password', loading: loading, onPressed: loading ? null : _change),
        ]),
      ),
    );
  }

  Widget _label(String s) => Text(s, style: context.typo.labelMedium?.copyWith(color: context.tokens.textSecondary, fontWeight: FontWeight.w600));
}
