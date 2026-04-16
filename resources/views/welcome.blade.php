<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DAYANCO TRADING CO. LIMITED</title>
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
        <h1 class="title">DAYANCO TRADING CO. LIMITED</h1>
        <p class="subtitle">منصة مؤسسية متكاملة لإدارة سير العمل والاعتمادات والوثائق</p>
        
        <a href="/login" class="login-btn">🚀 تسجيل الدخول إلى النظام</a>
        
        <div class="features">
            <div class="feature">إدارة المستخدمين حسب الأدوار والصلاحيات</div>
            <div class="feature">معالجة الطلبات وتتبع مراحل الاعتماد</div>
            <div class="feature">توليد المستندات الاحترافية بصيغة PDF</div>
            <div class="feature">إشعارات لحظية ولوحة متابعة مركزية</div>
            <div class="feature">حفظ آمن للمرفقات والوثائق</div>
        </div>
        
        <div style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #eee;">
            <small style="color: #999;">
                DAYANCO TRADING CO. LIMITED<br>
                © 2024 نظام إدارة سير العمل المؤسسي
            </small>
        </div>
    </div>

    <script>
        // Immediately redirect to login
        window.location.replace('/login');
    </script>
</body>
</html>
