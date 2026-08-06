import 'package:intl/intl.dart';

/// Same formatting as PWA app.js - DD-MM-YYYY and lowercase am/pm
class DateFormatter {
  static String formatDate(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return '-';
    try {
      DateTime dt;
      if (dateStr.contains('T')) {
        // Laravel may serialize DATE columns as UTC ISO timestamps.
        // Convert to local time before formatting to avoid previous-day display.
        dt = DateTime.parse(dateStr).toLocal();
      } else {
        dt = DateFormat('yyyy-MM-dd').parse(dateStr);
      }
      return DateFormat('dd-MM-yyyy').format(dt);
    } catch (_) {
      // Try already DD-MM-YYYY
      return dateStr;
    }
  }

  static String formatDateTime(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return '-';
    try {
      final dt = DateTime.parse(dateStr);
      return DateFormat('dd-MM-yyyy hh:mm a').format(dt).toLowerCase();
    } catch (_) {
      return dateStr;
    }
  }

  static String formatTime(String? timeStr) {
    if (timeStr == null || timeStr.isEmpty) return '-';
    try {
      // Handle HH:mm:ss or HH:mm
      DateTime dt;
      if (timeStr.contains(' ')) {
        // Already formatted like 02:30 PM
        final lower = timeStr.toLowerCase();
        return lower.contains('am') || lower.contains('pm') ? lower : timeStr;
      }
      // Parse as time only today
      if (timeStr.length >= 5) {
        final parts = timeStr.split(':');
        final hour = int.parse(parts[0]);
        final minute = int.parse(parts[1].split(' ')[0]);
        final now = DateTime.now();
        dt = DateTime(now.year, now.month, now.day, hour, minute);
        return DateFormat('hh:mm a').format(dt).toLowerCase();
      }
      return timeStr;
    } catch (_) {
      return timeStr.toLowerCase();
    }
  }

  static int daysLeft(String? expiryDate) {
    if (expiryDate == null || expiryDate.isEmpty) return 999;
    try {
      DateTime expiry;
      if (expiryDate.contains('-') && expiryDate.split('-')[0].length == 4) {
        expiry = DateFormat('yyyy-MM-dd').parse(expiryDate.split('T')[0].split(' ')[0]);
      } else {
        expiry = DateFormat('dd-MM-yyyy').parse(expiryDate);
      }
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      final expDay = DateTime(expiry.year, expiry.month, expiry.day);
      return expDay.difference(today).inDays;
    } catch (_) {
      return 999;
    }
  }

  static String expiryLabel(int days) {
    if (days < 0) return '${days.abs()} days ago';
    if (days == 0) return 'Expires today';
    return '$days days left';
  }
}
