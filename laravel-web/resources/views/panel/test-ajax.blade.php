<!DOCTYPE html>
<html>
<head>
    <title>AJAX Test</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        body { font-family: Arial; max-width: 600px; margin: 50px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 10px; }
        button { padding: 12px 24px; background: #ff6b2c; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin: 5px; }
        button:disabled { background: #ccc; }
        .result { margin-top: 20px; padding: 15px; border-radius: 8px; white-space: pre-wrap; font-family: monospace; font-size: 13px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .loading { background: #e2e3e5; color: #383d41; }
        .section { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h2 { color: #ff6b2c; margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>🧪 GymXBook AJAX Test</h1>
    
    <!-- Test 1: Basic JSON -->
    <div class="section">
        <h2>Test 1: Basic JSON POST</h2>
        <button onclick="testJson()">Test JSON POST</button>
        <div id="result1" class="result" style="display: none;"></div>
    </div>
    
    <!-- Test 2: FormData POST (like actual forms) -->
    <div class="section">
        <h2>Test 2: FormData POST</h2>
        <form id="testForm" onsubmit="testFormData(event)">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="Test User" required>
            </div>
            <div class="form-group">
                <label>Phone *</label>
                <input type="text" name="phone_number" value="9876543210" required>
            </div>
            <button type="submit">Test FormData POST</button>
        </form>
        <div id="result2" class="result" style="display: none;"></div>
    </div>
    
    <!-- Test 3: Member Store (Actual API) -->
    <div class="section">
        <h2>Test 3: Member Store API</h2>
        <form id="memberForm" onsubmit="testMemberStore(event)">
            <div class="form-group">
                <label>Name *</label>
                <input type="text" name="name" value="Test Member" required>
            </div>
            <div class="form-group">
                <label>Phone *</label>
                <input type="text" name="phone_number" value="9876543210" required>
            </div>
            <div class="form-group">
                <label>Plan *</label>
                <select name="membership_plan" required>
                    <option value="1">Plan 1</option>
                    <option value="2">Plan 2</option>
                </select>
            </div>
            <button type="submit">Test Member Store</button>
        </form>
        <div id="result3" class="result" style="display: none;"></div>
    </div>

    <script>
        async function testJson() {
            const result = document.getElementById('result1');
            result.style.display = 'block';
            result.className = 'result loading';
            result.textContent = 'Sending JSON...';
            
            try {
                const response = await fetch('/panel/test-submit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name: 'Test', email: 'test@test.com' })
                });
                
                const data = await response.json();
                result.className = data.success ? 'result success' : 'result error';
                result.textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                result.className = 'result error';
                result.textContent = 'Error: ' + error.message;
            }
        }
        
        async function testFormData(e) {
            e.preventDefault();
            const result = document.getElementById('result2');
            result.style.display = 'block';
            result.className = 'result loading';
            result.textContent = 'Sending FormData...';
            
            const form = document.getElementById('testForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('/panel/test-submit', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const data = await response.json();
                result.className = data.success ? 'result success' : 'result error';
                result.textContent = JSON.stringify(data, null, 2);
            } catch (error) {
                result.className = 'result error';
                result.textContent = 'Error: ' + error.message;
            }
        }
        
        async function testMemberStore(e) {
            e.preventDefault();
            const result = document.getElementById('result3');
            result.style.display = 'block';
            result.className = 'result loading';
            result.textContent = 'Sending to member store...';
            
            const form = document.getElementById('memberForm');
            const formData = new FormData(form);
            
            try {
                const response = await fetch('/panel/members', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData
                });
                
                const text = await response.text();
                let data;
                try {
                    data = JSON.parse(text);
                    result.className = data.success ? 'result success' : 'result error';
                    result.textContent = JSON.stringify(data, null, 2);
                } catch (e) {
                    result.className = 'result error';
                    result.textContent = 'Response (not JSON):\n' + text.substring(0, 500);
                }
            } catch (error) {
                result.className = 'result error';
                result.textContent = 'Error: ' + error.message;
            }
        }
    </script>
</body>
</html>

