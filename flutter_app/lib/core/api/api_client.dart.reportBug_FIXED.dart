  // ==================== BUG REPORT ====================

  Future<Map<String, dynamic>> reportBug({
    required String title,
    required String description,
    String? gymName,
    String? userId,
    String? email,
    String? screenshotPath,
  }) async {
    // Explicitly typed + collection-if to avoid ANY String? inference issues
    final Map<String, dynamic> data = <String, dynamic>{
      'title': title,
      'description': description,
      'gym_name': gymName,
      'user_id': userId,
      'email': email,
      if (screenshotPath != null) 'has_screenshot': true,
      if (screenshotPath != null) 'screenshot_name': screenshotPath.split('/').last,
    };

    final res = await postV1('/bugs/report', data: data);
    return _unwrap(res.data);
  }
