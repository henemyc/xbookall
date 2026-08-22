import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

final permissionProvider = Provider<PermissionService>((ref) {
  final user = ref.watch(authProvider).user;
  return PermissionService(user);
});

class PermissionService {
  final Map<String, dynamic>? user;
  const PermissionService(this.user);

  String get userType => (user?['type'] ?? '').toString();
  bool get isStaff => userType == 'staff';
  bool get isTrainee => userType == 'trainee';

  List<String> get permissions {
    final raw = user?['permissions'];
    if (raw is List) return raw.map((e) => e.toString()).toList();
    return const [];
  }

  bool can(String permission) {
    // Members (trainees) never have gym-admin permissions — this is what
    // previously let them see edit/delete on notices and other admin UI.
    if (isTrainee) return false;
    if (!isStaff) return true;
    return permissions.contains(permission);
  }

  bool canAny(List<String> keys) {
    if (isTrainee) return false;
    if (!isStaff) return true;
    return keys.any(permissions.contains);
  }
}
