import 'package:flutter/material.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';

class AppNotification {
  final int id;
  final String title;
  final String message;
  final String type;
  final String createdAt;
  final bool isRead;

  AppNotification({required this.id, required this.title, required this.message, required this.type, required this.createdAt, this.isRead = false});

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: int.tryParse(json['id'].toString()) ?? 0,
      title: json['title'] ?? '',
      message: json['message'] ?? '',
      type: json['type'] ?? 'info',
      createdAt: json['created_at'] ?? '',
      isRead: (json['is_read']?.toString() == '1') || json['is_read'] == true,
    );
  }

  String get formattedDate => DateFormatter.formatDate(createdAt);

  IconData get icon {
    switch (type) {
      case 'error':
        return Icons.error_outline;
      case 'warning':
        return Icons.warning_amber_rounded;
      case 'success':
        return Icons.check_circle_outline;
      default:
        return Icons.info_outline;
    }
  }

  Color get color {
    switch (type) {
      case 'error':
        return const Color(0xFFEF4444);
      case 'warning':
        return const Color(0xFFF59E0B);
      case 'success':
        return const Color(0xFF10B981);
      default:
        return const Color(0xFF3B82F6);
    }
  }
}
