import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';
import '../models/notice.dart';

final noticesProvider = StateNotifierProvider<NoticesNotifier, NoticesState>((ref) {
  final api = ref.watch(apiClientProvider);
  return NoticesNotifier(api);
});

class NoticesState {
  final bool isLoading;
  final List<Notice> notices;
  final String? error;

  NoticesState({this.isLoading = false, this.notices = const [], this.error});

  NoticesState copyWith({bool? isLoading, List<Notice>? notices, String? error}) {
    return NoticesState(isLoading: isLoading ?? this.isLoading, notices: notices ?? this.notices, error: error);
  }
}

class NoticesNotifier extends StateNotifier<NoticesState> {
  final ApiClient _api;
  NoticesNotifier(this._api) : super(NoticesState());

  Future<void> load() async {
    state = state.copyWith(isLoading: true, error: null);
    try {
      final res = await _api.getNotices();
      final list = (res['notices'] as List? ?? []).map((e) => Notice.fromJson(e)).toList();
      state = state.copyWith(isLoading: false, notices: list);
    } catch (e) {
      state = state.copyWith(isLoading: false, error: e.toString());
    }
  }

  Future<bool> add({required String title, required String description}) async {
    try {
      await _api.createNotice({'title': title, 'description': description});
      await load();
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> update({required int id, required String title, required String description}) async {
    try {
      await _api.updateNotice(id, {'title': title, 'description': description});
      await load();
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }

  Future<bool> delete(int id) async {
    try {
      await _api.deleteNotice(id);
      state = state.copyWith(notices: state.notices.where((n) => n.id != id).toList());
      return true;
    } catch (e) {
      state = state.copyWith(error: e.toString());
      return false;
    }
  }
}
