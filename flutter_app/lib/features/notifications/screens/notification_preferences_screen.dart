import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/core/widgets/ui.dart';

class NotificationPreferencesScreen extends ConsumerStatefulWidget {
  const NotificationPreferencesScreen({super.key});

  @override
  ConsumerState<NotificationPreferencesScreen> createState() => _NotificationPreferencesScreenState();
}

class _NotificationPreferencesScreenState extends ConsumerState<NotificationPreferencesScreen> {
  bool loading = true;
  bool saving = false;
  Map<String, dynamic> prefs = {
    'notices_enabled': true,
    'super_admin_enabled': true,
    'payments_enabled': true,
    'membership_enabled': true,
    'workouts_enabled': true,
  };

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final result = await ref.read(apiClientProvider).getNotificationPreferences();
      if (mounted) setState(() {
        prefs = Map<String, dynamic>.from(result['preferences'] ?? prefs);
        loading = false;
      });
    } catch (_) {
      if (mounted) setState(() => loading = false);
    }
  }

  // All notification preferences default to ON (missing/NULL means enabled).
  bool _value(String key) => prefs[key] == null ? true : (prefs[key] == true || prefs[key]?.toString() == '1');

  Future<void> _toggle(String key, bool value) async {
    final previous = Map<String, dynamic>.from(prefs);
    setState(() { prefs[key] = value; saving = true; });
    try {
      await ref.read(apiClientProvider).updateNotificationPreferences({key: value});
    } catch (_) {
      if (mounted) {
        setState(() => prefs = previous);
        Toast.error(context, 'Could not update notification preference');
      }
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Notification Preferences')),
      body: loading
          ? const SkeletonList(count: 5)
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text('Choose which push notifications you receive.', style: context.typo.bodyMedium?.copyWith(color: context.tokens.textTertiary)),
                const SizedBox(height: 16),
                SurfaceCard(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Column(children: [
                    _item('notices_enabled', Icons.campaign_rounded, 'Gym notices', 'New notices from your gym'),
                    _item('super_admin_enabled', Icons.admin_panel_settings_rounded, 'Platform announcements', 'Updates sent by GymXBook'),
                    _item('payments_enabled', Icons.payments_rounded, 'Payments', 'Payment receipts and payment updates'),
                    _item('membership_enabled', Icons.card_membership_rounded, 'Membership', 'Expiry and renewal reminders'),
                    _item('workouts_enabled', Icons.fitness_center_rounded, 'Workouts', 'New workout plan updates'),
                  ]),
                ),
                if (saving) const Padding(padding: EdgeInsets.only(top: 14), child: LinearProgressIndicator()),
              ],
            ),
    );
  }

  Widget _item(String key, IconData icon, String title, String subtitle) {
    return ListTile(
      leading: IconBadge(icon, color: AppTheme.brand, size: 40, iconSize: 19),
      title: Text(title, style: context.typo.titleSmall),
      subtitle: Text(subtitle, style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary, fontSize: 11.5)),
      trailing: Switch.adaptive(value: _value(key), onChanged: saving ? null : (value) => _toggle(key, value)),
    );
  }
}
