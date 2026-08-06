import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

final permissionProvider = Provider<PermissionService>((ref) {
  final user = ref.watch(authProvider).user;
  return PermissionService(user);
});

class PermissionService {
  final Map<String, dynamic>? user;
  const PermissionService(this.user);

  bool get isStaff => (user?['type'] ?? '').toString() == 'staff';

  List<String> get permissions {
    final raw = user?['permissions'];
    if (raw is List) return raw.map((e) => e.toString()).toList();
    return const [];
  }

  bool can(String permission) {
    if (!isStaff) return true;
    return permissions.contains(permission);
  }

  bool canAny(List<String> keys) {
    if (!isStaff) return true;
    return keys.any(can);
  }
}
