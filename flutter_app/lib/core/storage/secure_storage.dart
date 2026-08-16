import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SecureStorage {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(
      encryptedSharedPreferences: true,
      // For devices without secure hardware, fallback
      resetOnError: true,
    ),
  );

  static const String _installGuardKey = 'install_guard_v2';
  static const String _authSchemaKey = 'auth_session_schema';
  static const String _authNoticeKey = 'auth_session_notice';
  static const String _currentAuthSchema = '2';

  /// Prevent Android Auto Backup / restored SharedPreferences from keeping an
  /// old login after the app is uninstalled and installed again.
  ///
  /// On a real fresh install, secure storage usually has no token. If Android
  /// restores SharedPreferences from backup, this guard clears that restored
  /// token before AuthProvider checks login state.
  static Future<void> initializeFreshInstallGuard() async {
    try {
      final marker = await _storage.read(key: _installGuardKey);
      final secureToken = await _storage.read(key: 'api_token');

      if (marker == null || marker.isEmpty) {
        if (secureToken == null || secureToken.length < 20) {
          try { await _storage.deleteAll(); } catch (_) {}
          try {
            final prefs = await SharedPreferences.getInstance();
            await prefs.clear();
          } catch (_) {}
        }

        await _storage.write(
          key: _installGuardKey,
          value: DateTime.now().millisecondsSinceEpoch.toString(),
        );
        print('SecureStorage: Fresh install guard initialized');
      }
    } catch (e) {
      print('SecureStorage: Fresh install guard warning: $e');
      try {
        final prefs = await SharedPreferences.getInstance();
        await prefs.remove('api_token');
        await prefs.remove('user_id');
        await prefs.remove('user_type');
        await prefs.remove('user_name');
        await prefs.remove('user_email');
      } catch (_) {}
    }
  }

  /// Clears only authentication/session keys. Theme, onboarding and the auth
  /// schema marker remain untouched so logout never causes a repeated migration.
  static Future<void> clearAuthSession({String? notice}) async {
    const authKeys = ['api_token', 'user_id', 'user_type', 'user_name', 'user_email'];
    try {
      final prefs = await SharedPreferences.getInstance();
      for (final key in authKeys) {
        try { await _storage.delete(key: key); } catch (_) {}
        await prefs.remove(key);
      }
      if (notice != null && notice.isNotEmpty) {
        await prefs.setString(_authNoticeKey, notice);
      }
    } catch (_) {}
  }

  /// One-time compatibility migration for releases that change session/tenant
  /// behavior. Bump [_currentAuthSchema] deliberately for a future forced re-login.
  static Future<void> enforceAuthSessionSchema() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      if (prefs.getString(_authSchemaKey) == _currentAuthSchema) return;
      final hadSession = await getToken() != null;
      await clearAuthSession(notice: hadSession ? 'GymXBook has been updated. Please login again to continue.' : null);
      await prefs.setString(_authSchemaKey, _currentAuthSchema);
    } catch (_) {
      await clearAuthSession();
    }
  }

  static Future<String?> consumeAuthNotice() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final notice = prefs.getString(_authNoticeKey);
      if (notice != null) await prefs.remove(_authNoticeKey);
      return notice;
    } catch (_) {
      return null;
    }
  }

  // Dual-save: SecureStorage + SharedPreferences fallback for Android issues
  static Future<void> saveToken(String token) async {
    try {
      await _storage.write(key: 'api_token', value: token);
      print('SecureStorage: Token saved to secure storage len=${token.length}');
    } catch (e) {
      print('SecureStorage: Failed to save to secure storage: $e');
    }
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('api_token', token);
      print('SecureStorage: Token also saved to SharedPrefs');
    } catch (e) {
      print('SecureStorage: Failed to save to SharedPrefs: $e');
    }
  }

  static Future<String?> getToken() async {
    String? token;
    try {
      token = await _storage.read(key: 'api_token');
      if (token != null && token.length > 20) {
        print('SecureStorage: Got token from secure storage len=${token.length}');
        return token;
      }
    } catch (e) {
      print('SecureStorage: Error reading secure storage: $e');
    }
    // Fallback to SharedPreferences
    try {
      final prefs = await SharedPreferences.getInstance();
      token = prefs.getString('api_token');
      if (token != null && token.length > 20) {
        print('SecureStorage: Got token from SharedPrefs fallback len=${token.length}');
        // Restore to secure storage
        try { await _storage.write(key: 'api_token', value: token); } catch (_) {}
        return token;
      }
    } catch (e) {
      print('SecureStorage: Error reading SharedPrefs: $e');
    }
    print('SecureStorage: No token found anywhere');
    return null;
  }

  static Future<void> saveUser({required String id, required String type, required String name, required String email}) async {
    try {
      await _storage.write(key: 'user_id', value: id);
      await _storage.write(key: 'user_type', value: type);
      await _storage.write(key: 'user_name', value: name);
      await _storage.write(key: 'user_email', value: email);
    } catch (_) {}
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('user_id', id);
      await prefs.setString('user_type', type);
      await prefs.setString('user_name', name);
      await prefs.setString('user_email', email);
    } catch (_) {}
  }

  static Future<Map<String, String?>> getUser() async {
    try {
      final values = await _storage.readAll();
      if (values.isNotEmpty) return values;
    } catch (_) {}
    try {
      final prefs = await SharedPreferences.getInstance();
      return {
        'user_id': prefs.getString('user_id'),
        'user_type': prefs.getString('user_type'),
        'user_name': prefs.getString('user_name'),
        'user_email': prefs.getString('user_email'),
        'api_token': prefs.getString('api_token'),
      };
    } catch (_) {}
    return {};
  }

  static Future<void> clear() async {
    await clearAuthSession();
    print('SecureStorage: Cleared auth session data');
  }

  static Future<bool> isLoggedIn() async {
    final token = await getToken();
    return token != null && token.isNotEmpty && token.length > 20;
  }
}
