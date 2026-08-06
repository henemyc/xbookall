<!DOCTYPE html>
<html>
<head>
    <title>Toast Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        button { padding: 12px 24px; background: #ff6b2c; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin: 5px; }
        .toast-container { position: fixed; top: 24px; right: 24px; z-index: 99999; display: flex; flex-direction: column; gap: 10px; }
        .toast { min-width: 320px; border: none; border-radius: 14px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); overflow: hidden; animation: slideIn 0.3s ease-out; }
        .toast-success { background: linear-gradient(135deg, #16c784, #0d9c5f); color: white; }
        .toast-error { background: linear-gradient(135deg, #ff4d4f, #d4380d); color: white; }
        .toast-body { padding: 16px 20px; font-weight: 500; font-size: 14px; display: flex; align-items: center; }
        @keyframes slideIn { from { opacity: 0; transform: translateX(100%); } to { opacity: 1; transform: translateX(0); } }
    </style>
</head>
<body>
    <h1>🧪 Toast Test</h1>
    <button onclick="showToast('Member added successfully!', 'success')">Show Success Toast</button>
    <button onclick="showToast('Email already exists', 'error')">Show Error Toast</button>
    <button onclick="testAjax()">Test AJAX Submit</button>
    
    <div id="result" style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 8px; display: none;"></div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <script>
        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) {
                alert('Toast container not found!');
                return;
            }
            
            const icons = {
                success: '✓',
                error: '✗'
            };
            
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <div class="toast-body">
                    <span style="margin-right: 10px; font-size: 18px;">${icons[type] || '✓'}</span>
                    <span>${message}</span>
                    <span style="margin-left: auto; cursor: pointer;" onclick="this.closest('.toast').remove()">✕</span>
                </div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.style.transition = 'all 0.4s';
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateX(100%)';
                    setTimeout(() => toast.remove(), 400);
                }
            }, 5000);
        }
        
        async function testAjax() {
            const result = document.getElementById('result');
            result.style.display = 'block';
            result.textContent = 'Sending...';
            
            try {
                const formData = new FormData();
                formData.append('name', 'Test User');
                formData.append('phone_number', '9876543210');
                formData.append('membership_plan', '1');
                
                const response = await fetch('/panel/members', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showToast(data.message, 'success');
                    result.style.background = '#d4edda';
                    result.style.color = '#155724';
                    result.textContent = JSON.stringify(data, null, 2);
                } else {
                    showToast(data.error || 'Error', 'error');
                    result.style.background = '#f8d7da';
                    result.style.color = '#721c24';
                    result.textContent = JSON.stringify(data, null, 2);
                }
            } catch (error) {
                showToast('Network error', 'error');
                result.style.background = '#f8d7da';
                result.style.color = '#721c24';
                result.textContent = 'Error: ' + error.message;
            }
        }
    </script>
</body>
</html>
