import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Currently selected bottom-nav tab index.
final navIndexProvider = StateProvider<int>((ref) => 0);
