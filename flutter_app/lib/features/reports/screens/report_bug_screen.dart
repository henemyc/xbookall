import 'dart:io';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';
import 'package:gymxbook/core/widgets/ui.dart';
import 'package:gymxbook/core/api/api_client.dart';
import 'package:gymxbook/features/auth/providers/auth_provider.dart';

class ReportBugScreen extends ConsumerStatefulWidget {
  const ReportBugScreen({super.key});

  @override
  ConsumerState<ReportBugScreen> createState() => _ReportBugScreenState();
}

class _ReportBugScreenState extends ConsumerState<ReportBugScreen> {
  final _titleCtrl = TextEditingController();
  final _descCtrl = TextEditingController();
  File? _screenshot;
  bool _submitting = false;
  final ImagePicker _picker = ImagePicker();

  @override
  void dispose() {
    _titleCtrl.dispose();
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 75,
        maxWidth: 1200,
      );
      if (image != null) {
        setState(() => _screenshot = File(image.path));
      }
    } catch (e) {
      Toast.error(context, 'Could not pick image');
    }
  }

  Future<void> _takePhoto() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 75,
        maxWidth: 1200,
      );
      if (image != null) {
        setState(() => _screenshot = File(image.path));
      }
    } catch (e) {
      Toast.error(context, 'Could not take photo');
    }
  }

  Future<void> _submit() async {
    if (_titleCtrl.text.trim().isEmpty) {
      Toast.error(context, 'Please enter a title');
      return;
    }
    if (_descCtrl.text.trim().length < 10) {
      Toast.error(context, 'Please provide more details');
      return;
    }

    setState(() => _submitting = true);

    try {
      final auth = ref.read(authProvider);
      // Best effort gym name — try multiple common keys
      final gymName = (auth.user?['business_name'] ?? 
                       auth.user?['gym_name'] ?? 
                       auth.user?['company_name'] ?? 
                       auth.user?['name'] ?? 
                       'Gym Owner') as String;
      final userId = auth.user?['id'];
      final email = auth.user?['email'];

      final api = ref.read(apiClientProvider);

      final res = await api.reportBug(
        title: _titleCtrl.text.trim(),
        description: _descCtrl.text.trim(),
        gymName: gymName,
        userId: userId?.toString(),
        email: email,
        screenshotPath: _screenshot?.path,
      );

      final success = res['success'] == true || res['report'] != null || (res['message'] ?? '').toString().isNotEmpty;
      if (mounted) {
        if (success) {
          Toast.success(context, res['message'] ?? 'Bug report submitted. Thank you!');
          Navigator.pop(context);
        } else {
          Toast.error(context, res['error'] ?? res['message'] ?? 'Failed to submit bug report.');
        }
      }
    } catch (e) {
      if (mounted) {
        String msg = 'Failed to submit. Please try again.';
        try {
          final dioErr = e as dynamic;
          final data = dioErr.response?.data;
          if (data is Map) {
            msg = data['error'] ?? data['message'] ?? msg;
          } else {
            msg = e.toString();
          }
        } catch (_) {}
        Toast.error(context, msg);
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Report a Bug'),
      ),
      body: GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        child: SingleChildScrollView(
          padding: EdgeInsets.fromLTRB(20, 20, 20, 20 + MediaQuery.of(context).padding.bottom),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Info card
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: AppTheme.brand.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Row(
                  children: [
                    Icon(Icons.info_outline_rounded, color: AppTheme.brand),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Your report will be sent to the GymXBook team. Please include as much detail as possible.',
                        style: context.typo.bodySmall,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 24),

              _label('Bug Title *'),
              TextField(
                controller: _titleCtrl,
                decoration: const InputDecoration(
                  hintText: 'e.g. Member list not loading',
                ),
              ),
              const SizedBox(height: 20),

              _label('Description *'),
              TextField(
                controller: _descCtrl,
                maxLines: 6,
                decoration: const InputDecoration(
                  hintText: 'Describe what happened, steps to reproduce, and expected behavior...',
                ),
              ),
              const SizedBox(height: 24),

              _label('Screenshot (optional)'),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.photo_library_rounded),
                      label: const Text('Gallery'),
                      onPressed: _pickImage,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      icon: const Icon(Icons.camera_alt_rounded),
                      label: const Text('Camera'),
                      onPressed: _takePhoto,
                    ),
                  ),
                ],
              ),
              if (_screenshot != null) ...[
                const SizedBox(height: 12),
                Stack(
                  children: [
                    ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Image.file(
                        _screenshot!,
                        height: 160,
                        width: double.infinity,
                        fit: BoxFit.cover,
                      ),
                    ),
                    Positioned(
                      top: 8,
                      right: 8,
                      child: IconButton(
                        icon: const Icon(Icons.close_rounded, color: Colors.white),
                        style: IconButton.styleFrom(backgroundColor: Colors.black54),
                        onPressed: () => setState(() => _screenshot = null),
                      ),
                    ),
                  ],
                ),
              ],
              const SizedBox(height: 32),

              FireButton(
                label: _submitting ? 'Submitting...' : 'Submit Bug Report',
                loading: _submitting,
                onPressed: _submitting ? null : _submit,
              ),
              const SizedBox(height: 12),
              Center(
                child: Text(
                  'Our team will review and get back to you.',
                  style: context.typo.bodySmall?.copyWith(color: context.tokens.textTertiary),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 8),
        child: Text(text, style: context.typo.labelMedium?.copyWith(fontWeight: FontWeight.w600)),
      );
}