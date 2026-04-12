<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>WorkFlow Management System</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            background: white;
            padding: 3rem;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 90%;
        }
        .logo {
            font-size: 3rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 1rem;
        }
        .title {
            font-size: 2rem;
            color: #333;
            margin-bottom: 1rem;
        }
        .subtitle {
            font-size: 1.2rem;
            color: #666;
            margin-bottom: 2rem;
        }
        .login-btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
        }
        .features {
            margin-top: 2rem;
            text-align: left;
        }
        .feature {
            padding: 0.5rem 0;
            color: #555;
        }
        .feature::before {
            content: "✓";
            color: #667eea;
            font-weight: bold;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🏢</div>
        <h1 class="title">WorkFlow System</h1>
        <p class="subtitle">Complete Business Management Solution</p>
        
        <a href="/login" class="login-btn">🚀 Login to System</a>
        
        <div class="features">
            <div class="feature">Multi-Role User Management</div>
            <div class="feature">Order Processing & Tracking</div>
            <div class="feature">Document Generation (Excel/PDF)</div>
            <div class="feature">Real-time Analytics Dashboard</div>
            <div class="feature">Secure File Storage</div>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
            <small style="color: #999;">
                Powered by Laravel & React<br>
                © 2024 WorkFlow Management System
            </small>
        </div>
    </div>

    <script>
        // Immediately redirect to login
        window.location.replace('/login');
    </script>
</body>
</html>
