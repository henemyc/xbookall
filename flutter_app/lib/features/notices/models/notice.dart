import 'package:gymxbook/core/utils/date_formatter.dart';

class Notice {
  final int id;
  final String title;
  final String description;
  final String createdAt;

  Notice({required this.id, required this.title, required this.description, required this.createdAt});

  factory Notice.fromJson(Map<String, dynamic> json) {
    return Notice(
      id: int.tryParse(json['id'].toString()) ?? 0,
      title: json['title'] ?? '',
      description: json['description'] ?? '',
      createdAt: json['created_at'] ?? '',
    );
  }

  String get formattedDate => DateFormatter.formatDate(createdAt);
}
