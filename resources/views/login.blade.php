<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OmniChat - Login</title>
    <style>
        :root {
            --bg-dark: #0f172a;
            --surface-dark: #1e293b;
            --primary: #3b82f6;
            --text-light: #f8fafc;
            --text-muted: #94a3b8;
        }
        body {
            background-color: var(--bg-dark);
            color: var(--text-light);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .login-card {
            background: var(--surface-dark);
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            width: 100%;
            max-width: 400px;
        }
        .login-card h1 {
            margin-top: 0;
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 2rem;
            color: white;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            background: rgba(0,0,0,0.2);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 0.5rem;
            color: white;
            box-sizing: border-box;
            outline: none;
        }
        .form-group input:focus {
            border-color: var(--primary);
        }
        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 0.5rem;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        .btn-submit:hover {
            opacity: 0.9;
        }
        .error {
            color: #ef4444;
            font-size: 0.8rem;
            margin-top: 0.5rem;
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h1>OmniChat Login</h1>
        
        <form action="{{ route('login') }}" method="POST">
            @csrf
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="{{ old('email', 'admin@omnichat.com') }}" required autofocus>
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" value="password" required>
            </div>

            <button type="submit" class="btn-submit">Sign In</button>
        </form>
    </div>

</body>
</html>
