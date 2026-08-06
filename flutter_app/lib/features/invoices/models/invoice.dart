import 'package:gymxbook/core/utils/date_formatter.dart';

class InvoiceItem {
  final int id;
  final String title;
  final double amount;
  final String description;

  InvoiceItem({required this.id, required this.title, required this.amount, this.description = ''});

  factory InvoiceItem.fromJson(Map<String, dynamic> json) {
    return InvoiceItem(
      id: int.tryParse(json['id'].toString()) ?? 0,
      title: json['title'] ?? '',
      amount: double.tryParse(json['amount'].toString()) ?? 0,
      description: json['description'] ?? '',
    );
  }
}

class InvoicePayment {
  final int id;
  final double amount;
  final String paymentType;
  final String paymentDate;
  final String notes;

  InvoicePayment({required this.id, required this.amount, required this.paymentType, required this.paymentDate, this.notes = ''});

  factory InvoicePayment.fromJson(Map<String, dynamic> json) {
    return InvoicePayment(
      id: int.tryParse(json['id'].toString()) ?? 0,
      amount: double.tryParse(json['amount'].toString()) ?? 0,
      paymentType: json['payment_type'] ?? 'cash',
      paymentDate: json['payment_date'] ?? '',
      notes: json['notes'] ?? '',
    );
  }

  String get formattedDate => DateFormatter.formatDate(paymentDate);
}

class Invoice {
  final int id;
  final int invoiceId;
  final int userId;
  final String memberName;
  final String invoiceDate;
  final String? dueDate;
  final String status;
  final String notes;
  final List<InvoiceItem> items;
  final List<InvoicePayment> payments;
  final double totalAmount;
  final double paidAmount;

  Invoice({
    required this.id,
    required this.invoiceId,
    required this.userId,
    required this.memberName,
    required this.invoiceDate,
    this.dueDate,
    required this.status,
    this.notes = '',
    this.items = const [],
    this.payments = const [],
    this.totalAmount = 0,
    this.paidAmount = 0,
  });

  factory Invoice.fromJson(Map<String, dynamic> json) {
    // Parse total from multiple possible field names
    final total = _parseDouble(json['total_amount']) ??
        _parseDouble(json['amount']) ??
        _parseDouble(json['grand_total']) ??
        0;

    // Parse paid from multiple possible field names
    final paid = _parseDouble(json['paid_amount']) ??
        _parseDouble(json['paid']) ??
        0;

    final computedStatus = paid >= total && total > 0
        ? 'paid'
        : (paid > 0 ? 'partial' : (json['status'] ?? 'unpaid').toString());

    return Invoice(
      id: int.tryParse(json['id'].toString()) ?? 0,
      invoiceId: int.tryParse(json['invoice_id'].toString()) ?? 0,
      userId: int.tryParse(json['user_id'].toString()) ?? 0,
      memberName: json['member_name'] ?? json['name'] ?? '',
      invoiceDate: json['invoice_date'] ?? json['date'] ?? '',
      dueDate: json['invoice_due_date'],
      status: computedStatus,
      notes: json['notes'] ?? '',
      totalAmount: total,
      paidAmount: paid,
    );
  }

  factory Invoice.fromDetailJson(Map<String, dynamic> json) {
    final inv = json['invoice'] ?? json;
    final items = (inv['items'] as List? ?? []).map((e) => InvoiceItem.fromJson(e)).toList();
    final pays = (inv['payments'] as List? ?? []).map((e) => InvoicePayment.fromJson(e)).toList();

    final total = _parseDouble(inv['total_amount']) ??
        (items.isNotEmpty ? items.fold<double>(0.0, (s, i) => s + i.amount) : null) ??
        _parseDouble(inv['amount']) ??
        0;

    final paid = _parseDouble(inv['paid_amount']) ??
        (pays.isNotEmpty ? pays.fold<double>(0.0, (s, p) => s + p.amount) : null) ??
        0;

    final computedStatus = paid >= total && total > 0
        ? 'paid'
        : (paid > 0 ? 'partial' : (inv['status'] ?? 'unpaid').toString());

    return Invoice(
      id: int.tryParse(inv['id'].toString()) ?? 0,
      invoiceId: int.tryParse(inv['invoice_id'].toString()) ?? 0,
      userId: int.tryParse(inv['user_id'].toString()) ?? 0,
      memberName: inv['member_name'] ?? '',
      invoiceDate: inv['invoice_date'] ?? '',
      dueDate: inv['invoice_due_date'],
      status: computedStatus,
      notes: inv['notes'] ?? '',
      items: items,
      payments: pays,
      totalAmount: total,
      paidAmount: paid,
    );
  }

  /// Safe double parser — returns null if value is null, empty, or unparseable
  static double? _parseDouble(dynamic v) {
    if (v == null) return null;
    final d = double.tryParse(v.toString());
    return d;
  }

  double get dueAmount => (totalAmount - paidAmount).clamp(0, double.infinity).toDouble();
  String get formattedDate => DateFormatter.formatDate(invoiceDate);
  String get formattedDueDate => DateFormatter.formatDate(dueDate);

  // Status colors - same as PWA
  Map<String, dynamic> get statusColors {
    switch (status) {
      case 'paid':
        return {'bg': 0xFFECFDF5, 'text': 0xFF10B981, 'label': 'PAID'};
      case 'partial':
        return {'bg': 0xFFFEF3C7, 'text': 0xFFD97706, 'label': 'PARTIAL'};
      default:
        return {'bg': 0xFFFEF2F2, 'text': 0xFFEF4444, 'label': 'UNPAID'};
    }
  }
}
