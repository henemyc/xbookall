import 'dart:async';
import 'dart:convert';
import 'dart:math';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

/// Must remain top-level so Android can invoke it when Flutter is terminated.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
  // Phase 4 will persist/process data payloads through Laravel-backed routes.
}

class PushNotificationService {
  PushNotificationService._();

  static final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();
  static const AndroidNotificationChannel _channel = AndroidNotificationChannel(
    'gymxbook_general',
    'GymXBook Notifications',
    description: 'Updates from GymXBook',
    importance: Importance.high,
  );

  static StreamSubscription<String>? _tokenSubscription;
  static StreamSubscription<RemoteMessage>? _foregroundSubscription;
  static Future<void> Function(String token)? _tokenSyncHandler;
  static Future<void> Function(Map<String, dynamic> data)? _tapHandler;
  static Future<void> Function(Map<String, dynamic> data)? _foregroundHandler;
  static Map<String, dynamic>? _pendingTapData;
  static StreamSubscription<RemoteMessage>? _tapSubscription;
  static String? _token;
  static String? _installationId;

  static String? get token => _token;

  static Future<String> installationId() async {
    if (_installationId != null) return _installationId!;
    final prefs = await SharedPreferences.getInstance();
    final existing = prefs.getString('fcm_installation_id');
    if (existing != null && existing.isNotEmpty) {
      _installationId = existing;
      return existing;
    }
    final random = Random.secure();
    final created = '${DateTime.now().microsecondsSinceEpoch}-${List.generate(16, (_) => random.nextInt(36).toRadixString(36)).join()}';
    await prefs.setString('fcm_installation_id', created);
    _installationId = created;
    return created;
  }

  static void setTokenSyncHandler(Future<void> Function(String token)? handler) {
    _tokenSyncHandler = handler;
    final current = _token;
    if (current != null && current.isNotEmpty) _syncToken(current);
  }

  static Future<void> _syncToken(String token) async {
    try {
      await _tokenSyncHandler?.call(token);
    } catch (_) {
      // Device token sync is retryable and must never block app startup.
    }
  }

  static void setNotificationTapHandler(Future<void> Function(Map<String, dynamic> data)? handler) {
    _tapHandler = handler;
    final pending = _pendingTapData;
    if (pending != null) {
      _pendingTapData = null;
      _dispatchTap(pending);
    }
  }

  static void setForegroundMessageHandler(Future<void> Function(Map<String, dynamic> data)? handler) {
    _foregroundHandler = handler;
  }

  static Future<void> _dispatchTap(Map<String, dynamic> data) async {
    final handler = _tapHandler;
    if (handler == null) {
      _pendingTapData = data;
      return;
    }
    try {
      await handler(data);
    } catch (_) {
      // Navigation failures must never crash notification delivery.
    }
  }

  static Future<void> initialize() async {
    await Firebase.initializeApp();
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

    const androidInit = AndroidInitializationSettings('@mipmap/ic_launcher');
    await _local.initialize(
      const InitializationSettings(android: androidInit),
      onDidReceiveNotificationResponse: (response) {
        if (response.payload == null || response.payload!.isEmpty) return;
        try {
          final decoded = jsonDecode(response.payload!);
          if (decoded is Map) _dispatchTap(Map<String, dynamic>.from(decoded));
        } catch (_) {}
      },
    );
    final androidPlugin = _local.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
    await androidPlugin?.createNotificationChannel(_channel);

    final settings = await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );

    if (settings.authorizationStatus == AuthorizationStatus.denied) return;

    _token = await FirebaseMessaging.instance.getToken();
    final initialToken = _token;
    if (initialToken != null && initialToken.isNotEmpty) await _syncToken(initialToken);

    _tokenSubscription?.cancel();
    _tokenSubscription = FirebaseMessaging.instance.onTokenRefresh.listen((newToken) {
      _token = newToken;
      _syncToken(newToken);
    });

    _foregroundSubscription?.cancel();
    _foregroundSubscription = FirebaseMessaging.onMessage.listen((message) async {
      await _showForegroundNotification(message);
      try { await _foregroundHandler?.call(Map<String, dynamic>.from(message.data)); } catch (_) {}
    });

    _tapSubscription?.cancel();
    _tapSubscription = FirebaseMessaging.onMessageOpenedApp.listen((message) {
      _dispatchTap(Map<String, dynamic>.from(message.data));
    });

    final initialMessage = await FirebaseMessaging.instance.getInitialMessage();
    if (initialMessage != null) {
      await _dispatchTap(Map<String, dynamic>.from(initialMessage.data));
    }
  }

  static Future<void> _showForegroundNotification(RemoteMessage message) async {
    final notification = message.notification;
    if (notification == null) return;
    await _local.show(
      notification.hashCode,
      notification.title ?? 'GymXBook',
      notification.body ?? '',
      const NotificationDetails(
        android: AndroidNotificationDetails(
          'gymxbook_general',
          'GymXBook Notifications',
          channelDescription: 'Updates from GymXBook',
          importance: Importance.high,
          priority: Priority.high,
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  static Future<void> dispose() async {
    await _tokenSubscription?.cancel();
    await _foregroundSubscription?.cancel();
    await _tapSubscription?.cancel();
    _tokenSubscription = null;
    _foregroundSubscription = null;
    _tapSubscription = null;
  }
}
