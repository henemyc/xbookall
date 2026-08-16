import 'dart:async';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import 'package:gymxbook/features/notifications/models/notification.dart';

final notificationsProvider = StateNotifierProvider<NotificationsNotifier, NotificationsState>((ref) {
  final api = ref.watch(apiClientProvider);
  return NotificationsNotifier(api);
});

class NotificationsState {
  final bool isLoading;
  final bool hasLoaded;
  final List<AppNotification> notifications;
  final String? error;
  final int unreadCount;

  NotificationsState({
    this.isLoading = false,
    this.hasLoaded = false,
    this.notifications = const [],
    this.error,
    this.unreadCount = 0,
  });

  NotificationsState copyWith({
    bool? isLoading,
    bool? hasLoaded,
    List<AppNotification>? notifications,
    String? error,
    int? unreadCount,
  }) {
    return NotificationsState(
      isLoading: isLoading ?? this.isLoading,
      hasLoaded: hasLoaded ?? this.hasLoaded,
      notifications: notifications ?? this.notifications,
      error: error,
      unreadCount: unreadCount ?? this.unreadCount,
    );
  }
}

class NotificationsNotifier extends StateNotifier<NotificationsState> {
  final ApiClient _api;
  Timer? _livePoll;
  bool _pollingActive = false;
  int _failureCount = 0;

  NotificationsNotifier(this._api) : super(NotificationsState()) {
    // Initial load is intentionally one request. MainShell starts background
    // polling only while an authenticated app is visible.
    Future.microtask(() => load());
  }

  void startPolling() {
    if (_pollingActive) return;
    _pollingActive = true;
    _scheduleNextPoll(const Duration(seconds: 90));
  }

  void stopPolling() {
    _pollingActive = false;
    _livePoll?.cancel();
    _livePoll = null;
  }

  void _scheduleNextPoll(Duration delay) {
    _livePoll?.cancel();
    if (!_pollingActive) return;
    _livePoll = Timer(delay, () => load(force: true));
  }

  Duration _nextDelay() {
    if (_failureCount <= 0) return const Duration(seconds: 90);
    if (_failureCount == 1) return const Duration(seconds: 90);
    if (_failureCount == 2) return const Duration(seconds: 180);
    return const Duration(seconds: 300);
  }

  @override
  void dispose() {
    stopPolling();
    super.dispose();
  }

  Future<void> load({bool force = false}) async {
    if (state.isLoading) return;
    if (state.hasLoaded && !force) return;

    // Show skeleton only for the very first load. On refresh / empty state,
    // keep the current UI stable to avoid blinking/flickering.
    final showBlockingLoader = !state.hasLoaded && state.notifications.isEmpty;
    state = state.copyWith(isLoading: showBlockingLoader, error: null);

    try {
      final res = await _api.getNotifications();
      final list = (res['notifications'] as List? ?? [])
          .map((e) => AppNotification.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
      list.sort((a, b) => b.createdAt.compareTo(a.createdAt));

      _failureCount = 0;
      state = state.copyWith(
        isLoading: false,
        hasLoaded: true,
        notifications: list,
        unreadCount: int.tryParse((res['unread_count'] ?? list.where((n) => !n.isRead).length).toString()) ?? 0,
        error: null,
      );
    } catch (e) {
      _failureCount++;
      state = state.copyWith(
        isLoading: false,
        hasLoaded: true,
        error: e.toString(),
      );
    } finally {
      _scheduleNextPoll(_nextDelay());
    }
  }

  Future<bool> markAsRead(int id) async {
    try {
      await _api.markNotificationRead(id);
      final updated = state.notifications
          .map((n) => n.id == id
              ? AppNotification(
                  id: n.id,
                  title: n.title,
                  message: n.message,
                  type: n.type,
                  createdAt: n.createdAt,
                  isRead: true,
                )
              : n)
          .toList();
      state = state.copyWith(
        notifications: updated,
        unreadCount: updated.where((n) => !n.isRead).length,
        error: null,
      );
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> deleteOne(int id) async {
    try {
      await _api.deleteNotification(id);
      final updated = state.notifications.where((n) => n.id != id).toList();
      state = state.copyWith(
        notifications: updated,
        unreadCount: updated.where((n) => !n.isRead).length,
        error: null,
      );
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> clearAll() async {
    try {
      await _api.clearNotifications();
      state = state.copyWith(
        notifications: [],
        unreadCount: 0,
        hasLoaded: true,
        error: null,
      );
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }
}
