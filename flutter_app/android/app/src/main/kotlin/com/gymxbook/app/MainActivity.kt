package com.gymxbook.app

import android.widget.Toast
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private val toastChannel = "com.gymxbook.app/native_toast"

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, toastChannel)
            .setMethodCallHandler { call, result ->
                if (call.method == "show") {
                    val message = call.argument<String>("message").orEmpty()
                    runOnUiThread {
                        Toast.makeText(this, message, Toast.LENGTH_SHORT).show()
                    }
                    result.success(null)
                } else {
                    result.notImplemented()
                }
            }
    }
}
