<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'مدیریت نینی‌استوری')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .admin{min-height:100vh;background:#faf7f7;color:#382b30}.admin-layout{display:grid;grid-template-columns:240px 1fr;min-height:100vh}.admin-side{background:#fff;border-left:1px solid #efded7;padding:22px;display:flex;flex-direction:column;gap:12px}.admin-side a{padding:10px 12px;border-radius:12px}.admin-side a:hover{background:#fdebf0}.admin-main{min-width:0;padding:28px clamp(16px,4vw,56px)}.admin-card{background:white;border:1px solid #efded7;border-radius:18px;padding:20px;box-shadow:0 4px 14px #6b4f4f0b}.admin-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.admin-table{width:100%;border-collapse:collapse}.admin-table th,.admin-table td{text-align:right;padding:12px;border-bottom:1px solid #f1e8e7}.admin-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.admin-form label{display:grid;gap:6px}.admin-form input,.admin-form select,.admin-form textarea{border:1px solid #dfd0d0;border-radius:10px;padding:10px;background:white}.admin-span{grid-column:1/-1}.admin-button{display:inline-flex;padding:10px 16px;border-radius:12px;background:#c92e70;color:white;font-weight:700}.admin-button--muted{background:#6b4f4f}.admin-alert{padding:12px;border-radius:12px;background:#d8efe3;margin-bottom:16px}.admin-errors{background:#fde5e8;color:#8c2343}.admin-actions{display:flex;gap:8px;align-items:center}.admin-actions form{display:inline}.admin-variants{width:100%;border-collapse:collapse}.admin-variants th,.admin-variants td{padding:8px;border-bottom:1px solid #eee}.admin-variants input{width:100%;border:1px solid #dfd0d0;border-radius:8px;padding:8px}.admin-table-wrap{overflow-x:auto}@media(max-width:850px){.admin-layout{grid-template-columns:1fr}.admin-side{flex-direction:row;overflow:auto;border:0;border-bottom:1px solid #efded7;padding:10px}.admin-side h2,.admin-side form{display:none}.admin-side a{white-space:nowrap}.admin-main{padding:18px 14px}.admin-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.admin-form{grid-template-columns:1fr}.admin-span{grid-column:auto}}
    </style>
</head>
<body class="admin font-sans">
<div class="admin-layout">
    <aside class="admin-side">
        <h2 class="text-xl font-extrabold text-[var(--color-wine)]">NiniStory Admin</h2>
        <a href="{{ route('admin.dashboard') }}">داشبورد</a>
        <a href="{{ route('admin.categories.index') }}">دسته‌بندی‌ها</a>
        <a href="{{ route('admin.products.index') }}">محصولات و موجودی</a>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="admin-button admin-button--muted">خروج</button></form>
    </aside>
    <main class="admin-main">
        @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif
        @if($errors->any())
            <div class="admin-alert admin-errors"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
