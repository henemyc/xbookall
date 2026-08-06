import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/core/storage/secure_storage.dart';

final apiClientProvider = Provider<ApiClient>((ref) => ApiClient());

final authProvider = StateNotifierProvider<AuthNotifier, AuthState>((ref) {
  final api = ref.watch(apiClientProvider);
  return AuthNotifier(api);
});

class AuthState {
  final bool isLoading;
  final bool isLoggedIn;
  final String? error;
  final Map<String, dynamic>? user;
  final Map<String, dynamic>? subscription;
  final int? subscriptionDaysLeft;
  final bool subscriptionExpired;

  AuthState({this.isLoading = false, this.isLoggedIn = false, this.error, this.user, this.subscription, this.subscriptionDaysLeft, this.subscriptionExpired = false});

  AuthState copyWith({bool? isLoading, bool? isLoggedIn, String? error, Map<String, dynamic>? user, Map<String, dynamic>? subscription, int? subscriptionDaysLeft, bool? subscriptionExpired}) {
    return AuthState(
      isLoading: isLoading ?? this.isLoading,
      isLoggedIn: isLoggedIn ?? this.isLoggedIn,
      error: error,
      user: user ?? this.user,
      subscription: subscription ?? this.subscription,
      subscriptionDaysLeft: subscriptionDaysLeft ?? this.subscriptionDaysLeft,
      subscriptionExpired: subscriptionExpired ?? this.subscriptionExpired,
    );
  }
}

class AuthNotifier extends StateNotifier<AuthState> {
  final ApiClient _api;
  AuthNotifier(this._api) : super(AuthState(isLoading: true)) {
    checkAuth();
  }

  String _friendlyError(dynamic e) {
    try {
      final msg = e.toString().toLowerCase();

      if (msg.contains('no internet') || msg.contains('connection refused') || msg.contains('socketexception') || msg.contains('connection')) {
        return 'No internet connection. Please check your network.';
      }

      // Extract from Dio response (Laravel usually returns {message, error, errors})
      final response = (e as dynamic).response?.data;
      if (response is String && response.trim().isNotEmpty) {
        final text = response.toLowerCase();
        if (text.contains('personal_access_tokens')) return 'Server auth table missing. Please contact support.';
        if (text.contains('server error') || text.contains('exception') || text.contains('sqlstate')) return 'Server error. Please contact support.';
      }

      if (response is Map) {
        // Common Laravel error shapes
        if (response['message'] != null) {
          final m = response['message'].toString();
          if (m.toLowerCase().contains('credential') || m.toLowerCase().contains('invalid') || m.toLowerCase().contains('password')) {
return 'Invalid phone or password';
          }
          return m;
        }
        if (response['error'] != null) {
          return response['error'].toString();
        }
        if (response['errors'] is Map) {
          final errors = response['errors'] as Map;
          final firstError = errors.values.first;
          if (firstError is List && firstError.isNotEmpty) return firstError.first.toString();
          return firstError.toString();
        }
      }

      if (msg.contains('401') || msg.contains('unauthorized')) return 'Invalid phone or password';
      if (msg.contains('500')) return 'Server error. Please contact support.';
      if (msg.contains('404')) return 'Login API not found. Please update backend routes.';
      if (msg.contains('400')) return 'Please check your input';

      return 'Login failed: ${e.toString()}';
    } catch (_) {
      return 'Login failed. Please try again later.';
    }
  }

  void _trackAppOpenSilently() {
    Future.microtask(() async {
      try {
        await _api.trackAppOpen();
      } catch (_) {
        // Tracking must never block login/session.
      }
    });
  }

  // Debug helper - you can temporarily call this from login screen if needed
  String getLastError() => state.error ?? 'No error';

  bool _isAuthFailure(dynamic e) {
    try {
      final status = (e as dynamic).response?.statusCode;
      return status == 401 || status == 403;
    } catch (_) {
      return false;
    }
  }

  bool _isNetworkFailure(dynamic e) {
    try {
      if ((e as dynamic).response == null) return true;
    } catch (_) {}
    final text = e.toString().toLowerCase();
    return text.contains('socketexception') ||
        text.contains('connection') ||
        text.contains('network') ||
        text.contains('timeout') ||
        text.contains('no internet');
  }

  Future<Map<String, dynamic>> _cachedUserFromStorage() async {
    final stored = await SecureStorage.getUser();
    return {
      'id': stored['user_id'] ?? '0',
      'type': stored['user_type'] ?? 'admin',
      'name': stored['user_name'] ?? '',
      'email': stored['user_email'] ?? '',
    };
  }

  Map<String, dynamic> _userFromPayload(Map<String, dynamic> res, {Map<String, dynamic>? fallback}) {
    final rawUser = res['user'] ?? fallback ?? <String, dynamic>{};
    final user = Map<String, dynamic>.from(rawUser as Map);

    if (res['gym_info'] is Map) {
      final gymInfo = Map<String, dynamic>.from(res['gym_info'] as Map);
      final gymName = (gymInfo['name'] ?? '').toString().trim();
      user['gym_info'] = gymInfo;
      if (gymName.isNotEmpty) {
        user['company_name'] = gymName;
        if ((user['type'] ?? '').toString() == 'admin' || (user['type'] ?? '').toString() == 'owner' || (user['type'] ?? '').toString() == 'staff') {
          user['name'] = gymName;
        }
      }
      if (gymInfo['owner_id'] != null) user['gym_owner_id'] = gymInfo['owner_id'];
    }

    // Member fields
    if (res['plan_name'] != null) user['plan_name'] = res['plan_name'];
    if (res['membership_expiry_date'] != null) user['membership_expiry_date'] = res['membership_expiry_date'];
    if (res['membership_start_date'] != null) user['membership_start_date'] = res['membership_start_date'];
    if (res['fitness_goal'] != null) user['fitness_goal'] = res['fitness_goal'];
    if (res['trainee_detail'] != null) user['trainee_detail'] = res['trainee_detail'];
    if (res['trainee_status'] != null) user['trainee_status'] = res['trainee_status'];

    // Trainer fields
    if (res['trainer_detail'] != null) user['trainer_detail'] = res['trainer_detail'];
    if (res['qualification'] != null) user['qualification'] = res['qualification'];
    if (res['specialization'] != null) user['specialization'] = res['specialization'];
    if (res['experience_years'] != null) user['experience_years'] = res['experience_years'];

    // Subscription tier + plan feature access
    if (res['current_tier'] != null) user['current_tier'] = res['current_tier'];
    if (res['plan_features'] != null) user['plan_features'] = res['plan_features'];

    // Staff role/permissions
    if (res['permissions'] != null) user['permissions'] = res['permissions'];
    if (res['staff_role'] != null) user['staff_role'] = res['staff_role'];
    if (res['gym_owner_id'] != null) user['gym_owner_id'] = res['gym_owner_id'];

    return user;
  }

  /// Silent refresh — updates user data without showing loading spinner.
  /// Used when app resumes from background so the UI stays intact.
  Future<void> silentRefresh() async {
    final logged = await SecureStorage.isLoggedIn();
    if (!logged) {
      state = state.copyWith(isLoggedIn: false);
      return;
    }
    try {
      final res = await _api.me();
      state = state.copyWith(
        isLoggedIn: true, user: _userFromPayload(res, fallback: state.user), subscription: res['subscription'], error: null,
        subscriptionDaysLeft: res['subscription_days_left'] == null ? null : int.tryParse(res['subscription_days_left'].toString()),
        subscriptionExpired: res['subscription_expired'] == true,
      );
    } catch (e) {
      // Never log out on offline/timeout app resume. Only a real 401/403
      // should remove the local session.
      if (_isAuthFailure(e)) {
        await SecureStorage.clear();
        state = AuthState(isLoggedIn: false, isLoading: false);
      }
    }
  }

  Future<void> checkAuth() async {
    final logged = await SecureStorage.isLoggedIn();
    if (!logged) {
      state = state.copyWith(isLoggedIn: false, isLoading: false);
      return;
    }
    try {
      state = state.copyWith(isLoading: true);
      final res = await _api.me();
      state = state.copyWith(
        isLoading: false, isLoggedIn: true, user: _userFromPayload(res, fallback: state.user), subscription: res['subscription'], error: null,
        subscriptionDaysLeft: res['subscription_days_left'] == null ? null : int.tryParse(res['subscription_days_left'].toString()),
        subscriptionExpired: res['subscription_expired'] == true,
      );
      _trackAppOpenSilently();
    } catch (e) {
      if (_isAuthFailure(e)) {
        await SecureStorage.clear();
        state = AuthState(isLoggedIn: false, isLoading: false);
        return;
      }

      // If app opens while offline/timeout, keep the local session and show
      // the main shell with cached user data. Do not clear token.
      if (_isNetworkFailure(e)) {
        final cachedUser = await _cachedUserFromStorage();
        state = state.copyWith(
          isLoading: false,
          isLoggedIn: true,
          user: state.user ?? cachedUser,
          error: null,
        );
        return;
      }

      // Unknown server error: keep token/session to avoid forced logout.
      final cachedUser = await _cachedUserFromStorage();
      state = state.copyWith(isLoading: false, isLoggedIn: true, user: state.user ?? cachedUser, error: null);
    }
  }

  Future<bool> login({required String email, required String password}) async {
    try {
      state = state.copyWith(isLoading: true, error: null);
      final res = await _api.login(email: email, password: password);

      // Support both normalized {success: true} and raw Laravel response shapes
      final bool isSuccess = (res['success'] == true) ||
          (res['token'] != null || res['access_token'] != null);

      if (isSuccess) {
        final user = _userFromPayload(res);

        await SecureStorage.saveUser(
          id: (user['id'] ?? 0).toString(),
          type: user['type'] ?? 'admin',
          name: (user['company_name'] ?? user['name'] ?? '').toString(),
          email: user['email'] ?? '',
        );
        state = state.copyWith(
          isLoading: false,
          isLoggedIn: true,
          user: user,
          subscription: res['subscription'],
          subscriptionDaysLeft: res['subscription_days_left'] == null ? null : int.tryParse(res['subscription_days_left'].toString()),
          subscriptionExpired: res['subscription_expired'] == true,
          error: null,
        );
        _trackAppOpenSilently();
        return true;
      } else {
        final err = res['error'] ?? res['message'] ?? 'Login failed';
        state = state.copyWith(isLoading: false, error: err.toString());
        return false;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: _friendlyError(e));
      return false;
    }
  }

  Future<bool> register({
    required String businessName,
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    try {
      state = state.copyWith(isLoading: true, error: null);
      final res = await _api.register(businessName: businessName, name: name, email: email, phone: phone, password: password);

      // Backend returns token on success (same as login)
      final bool isSuccess = (res['success'] == true) ||
          (res['token'] != null || res['api_token'] != null || res['access_token'] != null);

      if (isSuccess) {
        // Extract token (same logic as login)
        final token = (res['token'] ?? res['api_token'] ?? res['access_token'])?.toString();
        if (token != null && token.isNotEmpty) {
          await SecureStorage.saveToken(token);
        }

        final user = _userFromPayload(res);

        await SecureStorage.saveUser(
          id: (user['id'] ?? 0).toString(),
          type: user['type'] ?? 'admin',
          name: (user['company_name'] ?? user['name'] ?? name ?? businessName).toString(),
          email: user['email'] ?? email,
        );

        state = state.copyWith(
          isLoading: false,
          isLoggedIn: true,
          user: user,
          subscription: res['subscription'],
          subscriptionDaysLeft: res['subscription_days_left'] == null ? null : int.tryParse(res['subscription_days_left'].toString()),
          subscriptionExpired: res['subscription_expired'] == true,
          error: null,
        );
        _trackAppOpenSilently();
        return true;
      } else {
        state = state.copyWith(isLoading: false, error: res['error'] ?? res['message'] ?? 'Register failed');
        return false;
      }
    } catch (e) {
      state = state.copyWith(isLoading: false, error: _friendlyError(e));
      return false;
    }
  }

  Future<void> logout() async {
    await _api.logout();
    state = AuthState(isLoggedIn: false);
  }

  // OTP helpers - now return exact server errors
  Future<Map<String, dynamic>> sendOtp(String phone) async {
    try {
      return await _api.sendOtp(phone: phone);
    } catch (e) {
      rethrow; // Let UI show exact Laravel message
    }
  }

  Future<Map<String, dynamic>> verifyOtp(String phone, String otp) async {
    return await _api.verifyOtp(phone: phone, otp: otp);
  }

  Future<Map<String, dynamic>> forgotSendOtp(String phone) async {
    try {
      return await _api.forgotSendOtp(phone: phone);
    } catch (e) {
      rethrow;
    }
  }

  Future<Map<String, dynamic>> forgotVerifyOtp(String phone, String otp) async {
    return await _api.forgotVerifyOtp(phone: phone, otp: otp);
  }

  Future<Map<String, dynamic>> forgotReset(String phone, String otp, String newPass, String confirmPass) async {
    return await _api.forgotReset(phone: phone, otp: otp, newPassword: newPass, confirmPassword: confirmPass);
  }
}
