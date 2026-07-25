<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لوحة التحكم - متجرك المربوط</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6">
    <div class="max-w-4xl mx-auto space-y-6">
        <!-- Top Navbar -->
        <header class="flex items-center justify-between p-4 bg-slate-900 border border-slate-800 rounded-2xl">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-500 to-purple-600 flex items-center justify-center font-bold text-white">
                    {{ strtoupper(substr($platform, 0, 1)) }}
                </div>
                <div>
                    <h2 class="font-bold text-white text-lg">{{ $storeName }}</h2>
                    <span class="text-xs text-slate-400">المنصة: <strong class="uppercase text-emerald-400">{{ $platform }}</strong></span>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-slate-300 hidden sm:inline">مرحباً بك، <strong>{{ $user->name }}</strong></span>
                
                <!-- 🔴 Logout Form -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 rounded-xl text-xs font-bold transition-all">
                        تسجيل الخروج
                    </button>
                </form>
            </div>
        </header>

        <!-- Main Status Card -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 shadow-xl">
            <div class="flex items-center gap-4 mb-6">
                <div class="w-12 h-12 rounded-xl {{ $platform === 'salla' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-purple-500/20 text-purple-400' }} flex items-center justify-center text-2xl">
                    {{ $platform === 'salla' ? '🟢' : '🟣' }}
                </div>
                <div>
                    <h3 class="text-xl font-bold text-white">تم الربط والمصادقة بنجاح 🎉</h3>
                    <p class="text-slate-400 text-sm">بيانات التوكن الخاصة بمتجرك مشفرة ومحفوظة في قاعدة البيانات بأمان.</p>
                </div>
            </div>

            <!-- Grid Details -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-4 border-t border-slate-800">
                <div class="p-4 bg-slate-950/50 rounded-xl border border-slate-800">
                    <span class="text-xs text-slate-500 block mb-1">اسم المتجر / المستخدم</span>
                    <strong class="text-white text-base">{{ $storeName }}</strong>
                </div>
                <div class="p-4 bg-slate-950/50 rounded-xl border border-slate-800">
                    <span class="text-xs text-slate-500 block mb-1">البريد الإلكتروني للتاجر</span>
                    <strong class="text-white text-base">{{ $user->email }}</strong>
                </div>
                <div class="p-4 bg-slate-950/50 rounded-xl border border-slate-800">
                    <span class="text-xs text-slate-500 block mb-1">معرف المتجر (Merchant ID)</span>
                    <strong class="text-white text-base">{{ $merchantId }}</strong>
                </div>
                <div class="p-4 bg-slate-950/50 rounded-xl border border-slate-800">
                    <span class="text-xs text-slate-500 block mb-1">نوع المنصة النشطة (حالة التوكن: مشفر 🔒)</span>
                    <span class="px-3 py-1 text-xs font-bold rounded-full {{ $platform === 'salla' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-purple-500/20 text-purple-400' }} uppercase inline-block">
                        {{ $platform }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
