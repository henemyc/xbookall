import 'package:flutter/material.dart';
import 'package:gymxbook/core/utils/date_formatter.dart';
import 'package:gymxbook/core/theme/app_theme.dart';

class Member {
  final int id;
  final String name;
  final String email;
  final String phone;
  final String type;
  final bool isActive;
  final String? profile;
  final String? expiryDate;
  final String? planName;
  final String? fitnessGoal;
  final String? address;
  final String? gender;
  final int traineeStatus; // 1=active, 2=expired, 3=frozen

  Member({
    required this.id,
    required this.name,
    required this.email,
    required this.phone,
    required this.type,
    required this.isActive,
    this.profile,
    this.expiryDate,
    this.planName,
    this.fitnessGoal,
    this.address,
    this.gender,
    this.traineeStatus = 1,
  });

  factory Member.fromJson(Map<String, dynamic> json) {
    return Member(
      id: int.tryParse(json['id'].toString()) ?? 0,
      name: json['name'] ?? '',
      email: json['email'] ?? '',
      phone: json['phone_number'] ?? json['phone'] ?? '',
      type: json['type'] ?? 'trainee',
      isActive: (json['is_active']?.toString() == '1') || (json['is_active'] == true) || (json['is_active'] == 1),
      profile: json['profile'],
      expiryDate: json['membership_expiry_date'],
      planName: json['plan_name'],
      fitnessGoal: json['fitness_goal'],
      address: json['address'],
      gender: json['gender'],
      traineeStatus: int.tryParse(json['trainee_status']?.toString() ?? '1') ?? 1,
    );
  }

  int get daysLeft => DateFormatter.daysLeft(expiryDate);
  String get expiryLabel => DateFormatter.expiryLabel(daysLeft);
  String get formattedExpiry => DateFormatter.formatDate(expiryDate);

  bool get isExpiringSoon => daysLeft >= 0 && daysLeft <= 7;
  bool get isExpiredRecently => daysLeft < 0 && daysLeft >= -3;
  bool get isExpired => daysLeft < 0;
  bool get isFrozen => traineeStatus == 3;

  /// Returns status label + color for badges
  String get statusLabel {
    if (isFrozen) return 'FROZEN';
    if (!isActive) return 'INACTIVE';
    if (isExpired) return 'EXPIRED';
    if (isExpiringSoon) return 'EXPIRING';
    return 'ACTIVE';
  }

  Color get statusColor {
    if (isFrozen) return AppTheme.info;
    if (!isActive) return AppTheme.danger;
    if (isExpired) return AppTheme.danger;
    if (isExpiringSoon) return AppTheme.warning;
    return AppTheme.success;
  }
}
