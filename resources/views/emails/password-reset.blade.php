<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密码重置 - Mosure</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container {
            background-color: #fff;
            border-radius: 8px;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #eee;
            padding-bottom: 20px;
        }
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
        }
        .logo img {
            width: 40px;
            height: 40px;
            margin-right: 8px;
        }
        .logo span {
            font-size: 18px;
            font-weight: bold;
            color: #000;
        }
        .content {
            margin-bottom: 30px;
        }
        .reset-button {
            display: inline-block;
            background-color: #1890ff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            margin: 20px 0;
        }
        .reset-button:hover {
            background-color: #40a9ff;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }
        .warning {
            background-color: #fff7e6;
            border: 1px solid #ffd591;
            border-radius: 4px;
            padding: 12px;
            margin: 20px 0;
            color: #d46b08;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <img src="{{ asset('logo.png') }}" alt="Mosure" />
                <span>Mosure</span>
            </div>
            <h1 style="margin: 0; color: #333;">密码重置</h1>
        </div>

        <div class="content">
            <p>您好，{{ $user->name }}！</p>
            
            <p>我们收到了您的密码重置请求。如果这不是您本人的操作，请忽略此邮件。</p>
            
            <p>要重置您的密码，请点击下面的按钮：</p>
            
            <div style="text-align: center;">
                <a href="{{ $resetLink }}" class="reset-button">重置密码</a>
            </div>
            
            <div class="warning">
                <strong>安全提示：</strong>此链接将在 {{ config('auth.passwords.users.expire', 60) }} 分钟后失效。请尽快完成密码重置。
            </div>
            
            <p>如果按钮无法点击，请复制以下链接到浏览器地址栏：</p>
            <p style="word-break: break-all; background-color: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace;">
                {{ $resetLink }}
            </p>
        </div>

        <div class="footer">
            <p>此邮件由 Mosure 系统自动发送，请勿回复。</p>
            <p>如有疑问，请联系系统管理员。</p>
        </div>
    </div>
</body>
</html>
