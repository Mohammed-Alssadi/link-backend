<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول - تطبيق سلة وزد الموحد</title>

    <!-- Google Fonts: Cairo -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Cairo', sans-serif;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-8 text-center">
        <!-- Logo / Header -->
        <div class="mb-8">
            <div class="w-16 h-16 mx-auto bg-gradient-to-tr from-emerald-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg mb-4">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold text-white mb-2">تطبيق المتاجر الموحد</h1>
            <p class="text-slate-400 text-sm">اختر منصة متجرك للربط والتسجيل الفوري بخطوة واحدة</p>
        </div>

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 text-sm">
                {{ session('error') }}
            </div>
        @endif

        <!-- Single-Click SSO Buttons -->
        <div class="space-y-4">
            <!-- 🟢 Salla SSO Button -->
            <a href="{{ route('auth.salla.redirect') }}" 
               class="w-full flex items-center justify-between px-6 py-4 bg-emerald-600 hover:bg-emerald-500 text-white rounded-xl font-bold transition-all shadow-lg hover:shadow-emerald-600/30 group">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-xl">🟢</span>
                    <span>تسجيل الدخول عبر سلة (Salla)</span>
                </span>
                <svg class="w-5 h-5 opacity-70 group-hover:translate-x-[-4px] transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>

            <!-- 🟣 Zid SSO Button -->
            <a href="{{ route('auth.zid.redirect') }}" 
               class="w-full flex items-center justify-between px-6 py-4 bg-purple-700 hover:bg-purple-600 text-white rounded-xl font-bold transition-all shadow-lg hover:shadow-purple-700/30 group">
                <span class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-xl">🟣</span>
                    <span>تسجيل الدخول عبر زد (Zid)</span>
                </span>
                <svg class="w-5 h-5 opacity-70 group-hover:translate-x-[-4px] transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        </div>

        <p class="mt-8 text-xs text-slate-500">
            تتم عملية الربط والمصادقة بأمان عالي وفق معايير OAuth 2.0 للمنصات
        </p>
    </div>
</body>
</html>
