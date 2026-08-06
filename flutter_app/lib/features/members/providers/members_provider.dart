import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:gymxbook/core/api/api_client.dart';
import '../models/member.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

final membersProvider = StateNotifierProvider<MembersNotifier, MembersState>((ref) {
  final api = ref.watch(apiClientProvider);
  return MembersNotifier(api);
});

class MembersState {
  final bool isLoading;
  final bool isLoadingMore;
  final List<Member> members;
  final String? error;
  final String searchQuery;
  final String statusFilter; // all, active, expired, expiring_7, expiring_14
  final int total;
  final int page;
  final int pages;

  MembersState({
    this.isLoading = false,
    this.isLoadingMore = false,
    this.members = const [],
    this.error,
    this.searchQuery = '',
    this.statusFilter = 'all',
    this.total = 0,
    this.page = 1,
    this.pages = 1,
  });

  bool get hasMore => page < pages || members.length < total;

  MembersState copyWith({
    bool? isLoading,
    bool? isLoadingMore,
    List<Member>? members,
    String? error,
    String? searchQuery,
    String? statusFilter,
    int? total,
    int? page,
    int? pages,
  }) {
    return MembersState(
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      members: members ?? this.members,
      error: error,
      searchQuery: searchQuery ?? this.searchQuery,
      statusFilter: statusFilter ?? this.statusFilter,
      total: total ?? this.total,
      page: page ?? this.page,
      pages: pages ?? this.pages,
    );
  }
}

class MembersNotifier extends StateNotifier<MembersState> {
  final ApiClient _api;
  MembersNotifier(this._api) : super(MembersState());

  Future<void> load({String search = '', String status = 'all', bool refresh = false}) async {
    state = state.copyWith(
      isLoading: true,
      isLoadingMore: false,
      error: null,
      searchQuery: search,
      statusFilter: status,
      page: 1,
      pages: 1,
      members: refresh ? state.members : const [],
    );

    try {
      final res = await _api.getMembers(search: search, status: status == 'all' ? '' : status, page: 1);
      final membersRaw = res['members'] ?? res['data'] ?? [];
      final list = (membersRaw as List? ?? [])
          .map((e) => Member.fromJson(e is Map ? Map<String, dynamic>.from(e) : {}))
          .toList();
      final total = int.tryParse((res['total'] ?? list.length).toString()) ?? list.length;
      final page = int.tryParse((res['page'] ?? 1).toString()) ?? 1;
      final pages = int.tryParse((res['pages'] ?? 1).toString()) ?? 1;

      state = state.copyWith(
        isLoading: false,
        isLoadingMore: false,
        members: list,
        total: total,
        page: page,
        pages: pages,
        error: null,
      );
    } catch (e) {
      state = state.copyWith(isLoading: false, isLoadingMore: false, error: e.toString());
    }
  }

  Future<void> loadMore() async {
    if (state.isLoading || state.isLoadingMore || !state.hasMore) return;

    final nextPage = state.page + 1;
    state = state.copyWith(isLoadingMore: true, error: null);

    try {
      final res = await _api.getMembers(
        search: state.searchQuery,
        status: state.statusFilter == 'all' ? '' : state.statusFilter,
        page: nextPage,
      );
      final membersRaw = res['members'] ?? res['data'] ?? [];
      final nextList = (membersRaw as List? ?? [])
          .map((e) => Member.fromJson(e is Map ? Map<String, dynamic>.from(e) : {}))
          .toList();

      final merged = <int, Member>{for (final m in state.members) m.id: m};
      for (final m in nextList) {
        merged[m.id] = m;
      }

      final total = int.tryParse((res['total'] ?? state.total).toString()) ?? state.total;
      final page = int.tryParse((res['page'] ?? nextPage).toString()) ?? nextPage;
      final pages = int.tryParse((res['pages'] ?? state.pages).toString()) ?? state.pages;

      state = state.copyWith(
        isLoadingMore: false,
        members: merged.values.toList(),
        total: total,
        page: page,
        pages: pages,
        error: null,
      );
    } catch (e) {
      state = state.copyWith(isLoadingMore: false, error: e.toString());
    }
  }

  Future<Map<String, dynamic>> getMemberDetail(int id) async {
    final res = await _api.getMember(id);
    return res;
  }
}
