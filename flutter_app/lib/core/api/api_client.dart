import 'dart:convert';
import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../storage/secure_storage.dart';

class ApiClient {
  // ========================================================
  // GymXBook Flutter API Client
  // Laravel REST API v1 + Sanctum
  //
  // CRITICAL: In Laravel, api routes are automatically prefixed with /api
  // So the real base is: https://web.gymxbook.com/api
  //
  // Final URLs become:
  //   https://web.gymxbook.com/api/v1/login
  //   https://web.gymxbook.com/api/v1/members
  //   https://web.gymxbook.com/api/v1/dashboard
  //
  // Base URL: https://web.gymxbook.com/api
  // Token: Authorization: Bearer
  // ========================================================
  static const String baseUrl = 'https://web.gymxbook.com/api';
  static const String appVersion = '1.1.1';
  static String? _cachedToken;

  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  ApiClient() {
    _dio = Dio(BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 15),
      receiveTimeout: const Duration(seconds: 15),
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-App-Platform': 'flutter',
        'X-App-Version': appVersion,
      },
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        try {
          String? token = _cachedToken;
          if (token == null || token.length < 20) {
            token = await _storage.read(key: 'api_token');
          }
          if (token == null || token.length < 20) {
            token = await SecureStorage.getToken();
          }
          if (token != null && token.isNotEmpty && token.length > 20) {
            _cachedToken = token;
            // Clean token handling for Laravel Sanctum
            options.headers['Authorization'] = 'Bearer $token';
          }
        } catch (_) {}
        return handler.next(options);
      },
      onResponse: (response, handler) {
        return handler.next(response);
      },
      onError: (DioException e, handler) {
        // User-friendly connection errors
        if (e.type == DioExceptionType.connectionTimeout ||
            e.type == DioExceptionType.receiveTimeout ||
            e.type == DioExceptionType.sendTimeout) {
          e = DioException(
            requestOptions: e.requestOptions,
            type: e.type,
            error: 'Connection timeout. Please check internet.',
          );
        } else if (e.type == DioExceptionType.connectionError) {
          e = DioException(
            requestOptions: e.requestOptions,
            type: e.type,
            error: 'No internet. Check connection.',
          );
        }
        return handler.next(e);
      },
    ));
  }

  Dio get dio => _dio;

  // ─────────────────────────────────────────────────────────────
  // Clean V1 REST Helpers (Laravel Sanctum)
  // ─────────────────────────────────────────────────────────────

  Future<Response> getV1(String path, {Map<String, dynamic>? query}) async {
    return _dio.get('/v1$path', queryParameters: query);
  }

  Future<Response> postV1(String path, {dynamic data, Map<String, dynamic>? query}) async {
    return _dio.post('/v1$path', data: data, queryParameters: query);
  }

  Future<Response> putV1(String path, {dynamic data, Map<String, dynamic>? query}) async {
    return _dio.put('/v1$path', data: data, queryParameters: query);
  }

  Future<Response> deleteV1(String path, {dynamic data, Map<String, dynamic>? query}) async {
    return _dio.delete('/v1$path', data: data, queryParameters: query);
  }

  // ─────────────────────────────────────────────────────────────
  // PHASE 1: Authentication (Laravel REST)
  // ─────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> login({required String email, required String password}) async {
    try {
      // Use postV1 so it automatically prefixes /api + /v1
      final res = await postV1('/login', data: {
        'email': email,
        'password': password,
      });

      // Safely handle different response shapes
      final rawData = res.data;
      final data = (rawData is Map) 
          ? Map<String, dynamic>.from(rawData) 
          : <String, dynamic>{};

      // Laravel Sanctum login usually returns { token, user }
      // Some setups wrap it as { data: {...} } or { success: true, ... }
      Map<String, dynamic> payload = data;

      // Handle possible nested data
      if (data['data'] is Map) {
        payload = Map<String, dynamic>.from(data['data']);
      }

      final token = (payload['token'] ?? payload['access_token'] ?? data['token'] ?? data['access_token'] ?? payload['api_token'] ?? data['api_token'])?.toString();

      if (token != null && token.isNotEmpty) {
        _cachedToken = token;
        await SecureStorage.saveToken(token);
        await _storage.write(key: 'api_token', value: token);

        final user = payload['user'] ?? data['user'] ?? payload['user'];

        if (user != null) {
          await _storage.write(key: 'user_type', value: user['type'] ?? 'admin');
          await _storage.write(key: 'user_id', value: user['id'].toString());
        }

        // Return rich payload for both admin and trainee (member) logins
        return {
          'success': true,
          'token': token,
          'user': user,
          'user_type': user?['type'] ?? payload['user_type'] ?? 'admin',
          'subscription': payload['subscription'] ?? data['subscription'],
          'subscription_expired': payload['subscription_expired'] ?? data['subscription_expired'],
          'subscription_days_left': payload['subscription_days_left'] ?? data['subscription_days_left'],
          'message': data['message'] ?? payload['message'] ?? 'Login successful',
          ...data,
          ...payload,
        };
      }

      // Failed login - try to extract message
      final errorMsg = data['message'] ?? 
                       data['error'] ?? 
                       payload['message'] ?? 
                       'Invalid email or password';

      return {
        'success': false,
        'error': errorMsg,
        ...data,
      };
    } catch (e) {
      rethrow;
    }
  }

  Future<Map<String, dynamic>> register({
    required String businessName,
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    try {
      final res = await postV1('/register', data: {
        'business_name': businessName,
        'name': name,
        'email': email,
        'phone_number': phone,
        'password': password,
      });

      final rawData = res.data;
      final data = (rawData is Map) ? Map<String, dynamic>.from(rawData) : <String, dynamic>{};
      Map<String, dynamic> payload = data;
      if (data['data'] is Map) {
        payload = Map<String, dynamic>.from(data['data']);
      }

      // Laravel registration can return api_token only. The app accepts
      // token/access_token/api_token so registration auto-login works
      // exactly like the login flow.
      final token = (payload['token'] ??
              payload['access_token'] ??
              payload['api_token'] ??
              data['token'] ??
              data['access_token'] ??
              data['api_token'])
          ?.toString();

      if (token != null && token.isNotEmpty) {
        _cachedToken = token;
        await SecureStorage.saveToken(token);
        await _storage.write(key: 'api_token', value: token);

        final user = payload['user'] ?? data['user'];
        if (user is Map) {
          await _storage.write(key: 'user_type', value: (user['type'] ?? 'admin').toString());
          await _storage.write(key: 'user_id', value: (user['id'] ?? '').toString());
        }

        return {
          'success': true,
          'token': token,
          'api_token': token,
          'access_token': token,
          'user': user,
          'subscription': payload['subscription'] ?? data['subscription'],
          'message': payload['message'] ?? data['message'] ?? 'Registration successful',
          ...data,
          ...payload,
        };
      }

      return {
        'success': false,
        'error': data['error'] ?? data['message'] ?? payload['error'] ?? payload['message'] ?? 'Register failed',
        ...data,
      };
    } catch (e) {
      rethrow;
    }
  }

  Future<Map<String, dynamic>> getAppUpdateInfo({required String currentVersion}) async {
    final res = await getV1('/app-update', query: {'current_version': currentVersion});
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getSystemStatus() async {
    final res = await getV1('/system-status');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getWebLoginSessions() async {
    final res = await getV1('/web-login/sessions');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> logoutWebLoginSession({int? sessionId}) async {
    final res = await postV1('/web-login/logout', data: {
      if (sessionId != null) 'session_id': sessionId,
    });
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> approveWebLogin({required String token}) async {
    final res = await postV1('/web-login/approve', data: {'token': token});
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> sendOtp({required String phone}) async {
    final res = await postV1('/send-otp', data: {'phone': phone});
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> verifyOtp({required String phone, required String otp}) async {
    final res = await postV1('/verify-otp', data: {'phone': phone, 'otp': otp});
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> forgotSendOtp({required String phone}) async {
    final res = await postV1('/forgot-password/send-otp', data: {'phone': phone});
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> forgotVerifyOtp({required String phone, required String otp}) async {
    final res = await postV1('/forgot-password/verify-otp', data: {'phone': phone, 'otp': otp});
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> forgotReset({
    required String phone,
    required String otp,
    required String newPassword,
    required String confirmPassword,
  }) async {
    final res = await postV1('/forgot-password/reset', data: {
      'phone': phone,
      'otp': otp,
      'new_password': newPassword,
      'confirm_password': confirmPassword,
    });
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> trackAppOpen() async {
    final res = await postV1('/track-app-open');
    return _unwrap(res.data);
  }

  Future<void> logout() async {
    try {
      await postV1('/logout');
    } catch (_) {}
    _cachedToken = null;
    await _storage.deleteAll();
    await SecureStorage.clear();
  }

  Future<Map<String, dynamic>> me() async {
    final res = await getV1('/me');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    final res = await postV1('/change-password', data: {
      'current_password': currentPassword,
      'new_password': newPassword,
    });
    return _unwrap(res.data);
  }

  // ─────────────────────────────────────────────────────────────
  // PHASE 2: Dashboard + Members + Attendance (Migrated to Laravel REST)
  // ─────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> dashboard() async {
    final res = await getV1('/dashboard');
    final out = _unwrap(res.data);
    final stats = out['stats'] ?? _findApiKey(res.data, 'stats');
    if (stats is Map) out['stats'] = Map<String, dynamic>.from(stats);
    final recentMembers = _normaliseApiListValue(out['recent_members'] ?? _findApiKey(res.data, 'recent_members'));
    if (recentMembers is List) out['recent_members'] = recentMembers;
    final todayCheckins = _normaliseApiListValue(out['today_checkins'] ?? _findApiKey(res.data, 'today_checkins'));
    if (todayCheckins is List) out['today_checkins'] = todayCheckins;
    final gymInfo = out['gym_info'] ?? _findApiKey(res.data, 'gym_info');
    if (gymInfo is Map) out['gym_info'] = Map<String, dynamic>.from(gymInfo);
    return out;
  }

  // ─────────────────────────────────────────────────────────────
  // Helper: Unwrap Laravel BaseController::success responses
  // Most endpoints return: { "success": true, "message": "...", "key": value, ... }
  // This extracts the payload keys at the root level.
  // NOTE: We now preserve 'message' so toasts in OTP / reportBug etc. can show it.
  dynamic _decodeApiRaw(dynamic raw) {
    if (raw is String) {
      final text = raw.trim();
      if (text.isEmpty) return null;
      try {
        return jsonDecode(text);
      } catch (_) {
        return raw;
      }
    }
    return raw;
  }

  Map<String, dynamic> _unwrap(dynamic raw) {
    raw = _decodeApiRaw(raw);
    if (raw == null) return <String, dynamic>{};
    if (raw is! Map) return <String, dynamic>{};

    final map = Map<String, dynamic>.from(raw);
    final message = map['message'];
    final nested = _decodeApiRaw(map['data']);

    // Support all backend response shapes:
    // 1) {success:true, members:[...]}
    // 2) {success:true, data:{members:[...]}}
    // 3) {success:true, data:[...]}
    // 4) JSON returned as a string because of server content-type.
    if (nested is Map) {
      final out = Map<String, dynamic>.from(nested);
      for (final entry in map.entries) {
        if (entry.key == 'success' || entry.key == 'data') continue;
        out.putIfAbsent(entry.key, () => entry.value);
      }
      if (message != null && out['message'] == null) out['message'] = message;
      return out;
    }

    if (map['success'] == true) {
      final payload = Map<String, dynamic>.from(map);
      payload.remove('success');
      return payload;
    }

    return map;
  }

  dynamic _findApiKey(dynamic raw, String key, [int depth = 0]) {
    raw = _decodeApiRaw(raw);
    if (depth > 8 || raw == null) return null;
    if (raw is Map) {
      final map = Map<String, dynamic>.from(raw);
      if (map.containsKey(key)) return _decodeApiRaw(map[key]);
      for (final value in map.values) {
        final found = _findApiKey(value, key, depth + 1);
        if (found != null) return found;
      }
    }
    return null;
  }

  dynamic _normaliseApiListValue(dynamic value) {
    value = _decodeApiRaw(value);
    if (value is List) return value;
    if (value is Map) {
      final map = Map<String, dynamic>.from(value);
      final nestedData = _decodeApiRaw(map['data']);
      if (nestedData is List) return nestedData;
      final items = _decodeApiRaw(map['items']);
      if (items is List) return items;
      final rows = _decodeApiRaw(map['rows']);
      if (rows is List) return rows;
    }
    return value;
  }

  Map<String, dynamic> _unwrapList(dynamic raw, String key) {
    final out = _unwrap(raw);
    out[key] = _normaliseApiListValue(out[key]);

    if (out[key] == null) {
      out[key] = _normaliseApiListValue(_findApiKey(raw, key));
    }
    if (out[key] == null && out['data'] != null) {
      out[key] = _normaliseApiListValue(out['data']);
    }
    if (out[key] == null) {
      out[key] = const [];
    }
    return out;
  }

  // Members
  Future<Map<String, dynamic>> getMembers({String search = '', String status = '', int page = 1}) async {
    final res = await getV1('/members', query: {
      'search': search,
      'status': status,
      'page': page,
    });
    return _unwrapList(res.data, 'members');
  }

  Future<Map<String, dynamic>> getMember(int id) async {
    final res = await getV1('/members/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> createMember(Map<String, dynamic> data) async {
    final res = await postV1('/members', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateMember(int id, Map<String, dynamic> data) async {
    final res = await putV1('/members/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteMember(int id) async {
    final res = await deleteV1('/members/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> hardDeleteMember(int id) async {
    final res = await deleteV1('/members/$id/hard');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> renewMember(int id, Map<String, dynamic> data) async {
    final res = await postV1('/members/$id/renew', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> freezeMember(int id, Map<String, dynamic> data) async {
    final res = await postV1('/members/$id/freeze', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> unfreezeMember(int id) async {
    final res = await postV1('/members/$id/unfreeze');
    return _unwrap(res.data);
  }

  // Attendance
  Future<Map<String, dynamic>> getAttendance({String date = '', int? id}) async {
    final query = <String, dynamic>{
      'date': date.isEmpty ? DateTime.now().toIso8601String().split('T')[0] : date,
    };
    if (id != null) query['id'] = id.toString();

    final res = await getV1('/attendance', query: query);
    return _unwrapList(res.data, 'attendance');
  }

  Future<Map<String, dynamic>> getAttendanceCalendar({required int month, required int year}) async {
    final res = await getV1('/attendance/calendar', query: {
      'month': month,
      'year': year,
    });
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> postAttendance({
    required int userId,
    String type = 'checkin',
    String notes = 'Flutter QR',
    String? qrToken,
  }) async {
    final res = await postV1('/attendance', data: {
      'user_id': userId,
      'type': type,
      'notes': notes,
      'qr_token': qrToken,
    });
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> searchAttendance(String query) async {
    final res = await getV1('/attendance/search', query: {'q': query});
    return _unwrap(res.data);
  }

  // Attendance update & delete (for edit/checkout/delete flows)
  Future<Map<String, dynamic>> updateAttendance(int id, Map<String, dynamic> data) async {
    final res = await putV1('/attendance/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteAttendance(int id) async {
    final res = await deleteV1('/attendance/$id');
    return _unwrap(res.data);
  }

  // Workouts (used in member detail)
  Future<Map<String, dynamic>> createWorkout(Map<String, dynamic> data) async {
    final res = await postV1('/workouts', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getWorkouts() async {
    final res = await getV1('/workouts');
    return _unwrap(res.data);
  }

  // Lockers assign (used in member detail)
  // Note: assignLocker already exists


  // ─────────────────────────────────────────────────────────────
  // PHASE 3: Finance Modules (Invoices, Transactions, Reports, Notifications)
  // Migrated to Laravel REST
  // ─────────────────────────────────────────────────────────────

  // Invoices
  Future<Map<String, dynamic>> getInvoices({String status = ''}) async {
    final res = await getV1('/invoices', query: {'status': status});
    return _unwrapList(res.data, 'invoices');
  }

  Future<Map<String, dynamic>> getInvoice(int id) async {
    final res = await getV1('/invoices/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> createInvoice(Map<String, dynamic> data) async {
    final res = await postV1('/invoices', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> addInvoicePayment(int id, Map<String, dynamic> data) async {
    final res = await postV1('/invoices/$id/payments', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteInvoice(int id) async {
    final res = await deleteV1('/invoices/$id');
    return _unwrap(res.data);
  }

  // Transactions
  Future<Map<String, dynamic>> getTransactions({int? month, int? year}) async {
    final res = await getV1('/transactions', query: {
      'month': month,
      'year': year,
    });
    return _unwrapList(res.data, 'transactions');
  }

  Future<Map<String, dynamic>> getMemberTransactions({required int userId}) async {
    final res = await getV1('/member-transactions', query: {'user_id': userId});
    return _unwrap(res.data);
  }

  // Reports
  Future<Map<String, dynamic>> getReports() async {
    final res = await getV1('/reports');
    return _unwrap(res.data);
  }

  // Notifications
  Future<Map<String, dynamic>> getNotifications() async {
    final res = await getV1('/notifications');
    return _unwrapList(res.data, 'notifications');
  }

  Future<Map<String, dynamic>> markNotificationRead(int id) async {
    final res = await putV1('/notifications/$id/read');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteNotification(int id) async {
    final res = await deleteV1('/notifications/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> clearNotifications() async {
    final res = await deleteV1('/notifications');
    return _unwrap(res.data);
  }

  // ─────────────────────────────────────────────────────────────
  // PHASE 4: Remaining Features (Trainers, Plans, Classes, Expenses, Products,
  // Lockers, Events, Notices, Settings, etc.)
  // Migrated to Laravel REST API
  // ─────────────────────────────────────────────────────────────

  // Trainers
  Future<Map<String, dynamic>> getTrainers() async {
    final res = await getV1('/trainers');
    return _unwrapList(res.data, 'trainers');
  }

  Future<Map<String, dynamic>> getTrainer(int id) async {
    final res = await getV1('/trainers/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> createTrainer(Map<String, dynamic> data) async {
    final res = await postV1('/trainers', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateTrainer(int id, Map<String, dynamic> data) async {
    final res = await putV1('/trainers/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteTrainer(int id) async {
    final res = await deleteV1('/trainers/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> toggleTrainer(int id) async {
    final res = await putV1('/trainers/$id/toggle');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getTrainerDashboard() async {
    final res = await getV1('/trainer/dashboard');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getTrainerAssignedMembers() async {
    final res = await getV1('/trainer/members');
    return _unwrapList(res.data, 'members');
  }

  Future<Map<String, dynamic>> getTrainerClasses() async {
    final res = await getV1('/trainer/classes');
    return _unwrapList(res.data, 'classes');
  }

  Future<Map<String, dynamic>> getTrainerAssignedMember(int id) async {
    final res = await getV1('/trainer/members/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getTrainerWorkoutPlans() async {
    final res = await getV1('/trainer/workouts');
    return _unwrapList(res.data, 'workouts');
  }

  Future<Map<String, dynamic>> createTrainerWorkoutPlan(Map<String, dynamic> data) async {
    final res = await postV1('/trainer/workouts', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateTrainerWorkoutPlan(int id, Map<String, dynamic> data) async {
    final res = await putV1('/trainer/workouts/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteTrainerWorkoutPlan(int id) async {
    final res = await deleteV1('/trainer/workouts/$id');
    return _unwrap(res.data);
  }

  // Memberships (Plans)
  Future<Map<String, dynamic>> getMemberships() async {
    final res = await getV1('/memberships');
    final out = _unwrapList(res.data, 'memberships');
    if ((out['memberships'] as List?)?.isEmpty == true) {
      final plans = _normaliseApiListValue(out['plans'] ?? _findApiKey(res.data, 'plans'));
      if (plans is List) out['memberships'] = plans;
    }
    return out;
  }

  Future<Map<String, dynamic>> createMembership(Map<String, dynamic> data) async {
    final res = await postV1('/memberships', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateMembership(int id, Map<String, dynamic> data) async {
    final res = await putV1('/memberships/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteMembership(int id) async {
    final res = await deleteV1('/memberships/$id');
    return _unwrap(res.data);
  }

  // Classes
  Future<Map<String, dynamic>> getClasses() async {
    final res = await getV1('/classes');
    return _unwrapList(res.data, 'classes');
  }

  // Categories (used in Add Member)
  Future<Map<String, dynamic>> getCategories() async {
    final res = await getV1('/categories');
    return _unwrapList(res.data, 'categories');
  }

  Future<Map<String, dynamic>> createClass(Map<String, dynamic> data) async {
    final res = await postV1('/classes', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateClass(int id, Map<String, dynamic> data) async {
    final res = await putV1('/classes/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteClass(int id) async {
    final res = await deleteV1('/classes/$id');
    return _unwrap(res.data);
  }

  // Expenses
  Future<Map<String, dynamic>> getExpenses({int? month, int? year}) async {
    final res = await getV1('/expenses', query: {
      'month': month,
      'year': year,
    });
    return _unwrapList(res.data, 'expenses');
  }

  Future<Map<String, dynamic>> createExpense(Map<String, dynamic> data) async {
    final res = await postV1('/expenses', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateExpense(int id, Map<String, dynamic> data) async {
    final res = await putV1('/expenses/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteExpense(int id) async {
    final res = await deleteV1('/expenses/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getExpenseTypes() async {
    final res = await getV1('/expenses/types');
    return _unwrap(res.data);
  }

  // Products
  Future<Map<String, dynamic>> getProducts() async {
    final res = await getV1('/products');
    return _unwrapList(res.data, 'products');
  }

  Future<Map<String, dynamic>> createProduct(Map<String, dynamic> data) async {
    final res = await postV1('/products', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateProduct(int id, Map<String, dynamic> data) async {
    final res = await putV1('/products/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteProduct(int id) async {
    final res = await deleteV1('/products/$id');
    return _unwrap(res.data);
  }

  // Lockers
  Future<Map<String, dynamic>> getLockers() async {
    final res = await getV1('/lockers');
    return _unwrapList(res.data, 'lockers');
  }

  Future<Map<String, dynamic>> createLocker(Map<String, dynamic> data) async {
    final res = await postV1('/lockers', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> assignLocker(Map<String, dynamic> data) async {
    final res = await postV1('/lockers/assign', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> unassignLocker(Map<String, dynamic> data) async {
    final res = await putV1('/lockers/unassign', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteLocker(int id) async {
    final res = await deleteV1('/lockers/$id');
    return _unwrap(res.data);
  }

  // Events
  Future<Map<String, dynamic>> getEvents() async {
    final res = await getV1('/events');
    return _unwrapList(res.data, 'events');
  }

  Future<Map<String, dynamic>> createEvent(Map<String, dynamic> data) async {
    final res = await postV1('/events', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateEvent(int id, Map<String, dynamic> data) async {
    final res = await putV1('/events/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteEvent(int id) async {
    final res = await deleteV1('/events/$id');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getEventTypes() async {
    final res = await getV1('/events/types');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> createEventType(Map<String, dynamic> data) async {
    final res = await postV1('/events/types', data: data);
    return _unwrap(res.data);
  }

  // Notices
  Future<Map<String, dynamic>> getNotices() async {
    final res = await getV1('/notices');
    return _unwrapList(res.data, 'notices');
  }

  Future<Map<String, dynamic>> createNotice(Map<String, dynamic> data) async {
    final res = await postV1('/notices', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateNotice(int id, Map<String, dynamic> data) async {
    final res = await putV1('/notices/$id', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> deleteNotice(int id) async {
    final res = await deleteV1('/notices/$id');
    return _unwrap(res.data);
  }

  // Settings
  Future<Map<String, dynamic>> getSettings() async {
    final res = await getV1('/settings');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateSettings(Map<String, dynamic> data) async {
    final res = await postV1('/settings', data: data);
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> getGymProfile() async {
    final res = await getV1('/settings/gym-profile');
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> updateGymProfile(Map<String, dynamic> data) async {
    final res = await putV1('/settings/gym-profile', data: data);
    return _unwrap(res.data);
  }

  // ─────────────────────────────────────────────────────────────
  // PHASE 5: Subscription (SaaS Top-up) - Migrated to Laravel REST
  // ─────────────────────────────────────────────────────────────
  Future<Map<String, dynamic>> getSubscriptionPlans() async {
    final res = await getV1('/subscription/plans');
    final out = _unwrapList(res.data, 'tiers');
    if (out['plans'] == null && out['data'] is List) out['plans'] = out['data'];
    return out;
  }

  Future<Map<String, dynamic>> createSubscriptionPaymentLink({int? planId, int? tierId, int? tierPriceId, required String type}) async {
    final res = await postV1('/subscription/payment-link', data: {
      if (planId != null) 'plan_id': planId,
      if (tierId != null) 'subscription_tier_id': tierId,
      if (tierPriceId != null) 'subscription_tier_price_id': tierPriceId,
      'type': type,
    });
    return _unwrap(res.data);
  }

  Future<Map<String, dynamic>> verifySubscriptionPayment({required String orderId}) async {
    final res = await getV1('/subscription/verify', query: {'order_id': orderId});
    return _unwrap(res.data);
  }

  // Bonus: Cancel subscription (available on backend)
  Future<Map<String, dynamic>> cancelSubscription() async {
    final res = await postV1('/subscription/cancel');
    return _unwrap(res.data);
  }

  // QR Attendance Scan (member side) - uses dedicated /scan endpoint
  // IMPORTANT: This must hit /v1/attendance/scan exactly
  Future<Map<String, dynamic>> scanAttendance({
    required String qrToken,
    String type = 'checkin',
    String notes = 'QR Scan',
  }) async {
    // Use explicit path to avoid any prefix issues
    final res = await _dio.post('/v1/attendance/scan', data: {
      'qr_token': qrToken,
      'type': type,
      'notes': notes,
    });
    return _unwrap(res.data);
  }

  // Convenience: get current logged-in user's full profile (for member dashboard)
  Future<Map<String, dynamic>> getMyProfile() async {
    final res = await getV1('/me');
    return _unwrap(res.data);
  }

  // ==================== INTEGRATIONS TEST ====================

  /// Test SMTP by sending a test email
  Future<Map<String, dynamic>> testSmtp({required String testEmail}) async {
    final res = await postV1('/settings/smtp/test', data: {
      'test_email': testEmail,
    });
    return _unwrap(res.data);
  }

  /// Get current SMTP configuration
  Future<Map<String, dynamic>> getSmtpSettings() async {
    final res = await getV1('/settings/smtp');
    return _unwrap(res.data);
  }

  // ==================== BUG REPORT ====================

  Future<Map<String, dynamic>> reportBug({
    required String title,
    required String description,
    String? gymName,
    String? userId,
    String? email,
    String? screenshotPath,
  }) async {
    final cleanGymName = gymName?.toString();
    final cleanUserId = userId?.toString();
    final cleanEmail = email?.toString();

    if (screenshotPath != null && screenshotPath.trim().isNotEmpty) {
      final file = File(screenshotPath);
      if (await file.exists()) {
        final filename = screenshotPath.split(RegExp(r'[\/]')).last;
        try {
          final formData = FormData.fromMap({
            'title': title,
            'description': description,
            if (cleanGymName != null && cleanGymName.isNotEmpty) 'gym_name': cleanGymName,
            if (cleanUserId != null && cleanUserId.isNotEmpty) 'user_id': cleanUserId,
            if (cleanEmail != null && cleanEmail.isNotEmpty) 'email': cleanEmail,
            'has_screenshot': '1',
            'screenshot_name': filename,
            'screenshot': await MultipartFile.fromFile(
              screenshotPath,
              filename: filename,
            ),
          });

          // Do not leave the default JSON content-type on this request.
          final res = await _dio.post(
            '/v1/bugs/report',
            data: formData,
            options: Options(contentType: Headers.multipartFormDataContentType, headers: {'Accept': 'application/json'}),
          );
          return _unwrap(res.data);
        } catch (_) {
          // Some servers/proxies strip multipart files. Fallback to base64 JSON
          // so screenshot upload still works for support reports.
          final bytes = await file.readAsBytes();
          final res = await postV1('/bugs/report', data: {
            'title': title,
            'description': description,
            if (cleanGymName != null && cleanGymName.isNotEmpty) 'gym_name': cleanGymName,
            if (cleanUserId != null && cleanUserId.isNotEmpty) 'user_id': cleanUserId,
            if (cleanEmail != null && cleanEmail.isNotEmpty) 'email': cleanEmail,
            'has_screenshot': '1',
            'screenshot_name': filename,
            'screenshot_base64': base64Encode(bytes),
          });
          return _unwrap(res.data);
        }
      }
    }

    // No screenshot - simple JSON
    final Map<String, dynamic> data = <String, dynamic>{
      'title': title,
      'description': description,
      if (cleanGymName != null && cleanGymName.isNotEmpty) 'gym_name': cleanGymName,
      if (cleanUserId != null && cleanUserId.isNotEmpty) 'user_id': cleanUserId,
      if (cleanEmail != null && cleanEmail.isNotEmpty) 'email': cleanEmail,
    };

    final res = await postV1('/bugs/report', data: data);
    return _unwrap(res.data);
  }

  // Admin: Get all bug reports
  Future<Map<String, dynamic>> getBugReports({String status = 'all'}) async {
    final res = await getV1('/bug-reports', query: {'status': status});
    return _unwrap(res.data);
  }

  // Admin: Update bug report + notify user
  Future<Map<String, dynamic>> updateBugReport(int id, {required String status, String? adminNotes}) async {
    final res = await putV1('/bug-reports/$id', data: {
      'status': status,
      'admin_notes': adminNotes,
    });
    return _unwrap(res.data);
  }

}
