<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'POS Ferreteria')</title>
    <style>
        :root { --bg:#f4f6f8; --ink:#1f2937; --muted:#6b7280; --brand:#e50909; --brand2:#111827; --line:#d8dee6; --card:#fff; --warn:#b45309; --danger:#b91c1c; --sidebar:#050505; --sidebar-soft:#141414; --sidebar-line:#2a2a2a; }
        * { box-sizing:border-box; } body { margin:0; font-family:Arial, Helvetica, sans-serif; background:var(--bg); color:var(--ink); }
        a { color:inherit; text-decoration:none; } .shell { display:flex; min-height:100vh; }
        .sidebar { width:286px; background:linear-gradient(180deg, #020202 0%, #111 52%, #050505 100%); color:#f8fafc; padding:14px 18px; position:fixed; inset:0 auto 0 0; height:100vh; overflow-y:auto; border-right:4px solid var(--brand); box-shadow:10px 0 30px rgba(0,0,0,.14); z-index:20; }
        .brand-block { display:grid; gap:9px; padding:4px 6px 14px; margin-bottom:10px; border-bottom:1px solid var(--sidebar-line); }
        .brand-logo { display:block; width:100%; max-width:150px; max-height:82px; object-fit:contain; object-position:left center; }
        .brand { font-size:16px; font-weight:900; letter-spacing:.3px; line-height:1.1; text-transform:uppercase; }
        .brand span { display:block; color:var(--brand); font-size:11px; margin-top:4px; }
        .role { display:inline-flex; align-items:center; width:max-content; color:#fff; background:#b80707; border:1px solid #ff2b2b; font-size:12px; font-weight:800; padding:5px 9px; border-radius:999px; text-transform:uppercase; }
        .nav { display:grid; gap:5px; }
        .nav a, .logout { width:100%; min-height:36px; display:flex; align-items:center; gap:9px; padding:8px 10px; border-radius:7px; color:#e5e7eb; border:1px solid transparent; font-size:14px; font-weight:700; line-height:1.2; transition:background .18s ease, color .18s ease, border-color .18s ease, transform .18s ease; }
        .nav a::before, .logout::before { content:""; width:6px; height:6px; border-radius:999px; background:#5b5b5b; flex:0 0 auto; }
        .nav a:hover, .logout:hover { background:var(--sidebar-soft); border-color:#343434; color:#fff; transform:translateX(2px); }
        .nav a:hover::before, .logout:hover::before, .nav .active::before { background:var(--brand); box-shadow:0 0 0 4px rgba(229,9,9,.18); }
        .nav .active { background:#fff; color:#050505; border-color:#fff; box-shadow:inset 4px 0 0 var(--brand); }
        .menu-toggle { display:none; position:fixed; top:12px; left:12px; width:42px; height:42px; border:0; border-radius:8px; background:#111; color:#fff; cursor:pointer; z-index:40; box-shadow:0 8px 22px rgba(0,0,0,.22); transition:transform .18s ease, background .18s ease; }
        .menu-toggle:hover { transform:translateY(-2px); background:#252525; }
        .menu-toggle span, .menu-toggle::before, .menu-toggle::after { content:""; display:block; width:20px; height:2px; margin:5px auto; border-radius:999px; background:currentColor; transition:transform .18s ease, opacity .18s ease; }
        .menu-backdrop { display:none; position:fixed; inset:0; background:rgba(0,0,0,.52); z-index:25; }
        .menu-open { overflow:hidden; }
        .menu-open .menu-toggle span { opacity:0; }
        .menu-open .menu-toggle::before { transform:translateY(7px) rotate(45deg); }
        .menu-open .menu-toggle::after { transform:translateY(-7px) rotate(-45deg); }
        .menu-open .menu-backdrop { display:block; }
        .content { flex:1; min-width:0; margin-left:286px; } .topbar { display:flex; justify-content:space-between; align-items:center; padding:18px 28px; background:#fff; border-bottom:1px solid var(--line); box-shadow:0 1px 0 rgba(0,0,0,.03); }
        .topbar strong { color:#111827; font-size:20px; } .topbar .muted { font-weight:700; }
        .page { padding:28px; } .panel { background:var(--card); border:1px solid var(--line); border-radius:8px; padding:18px; }
        .grid { display:grid; gap:16px; } .grid-4 { grid-template-columns:repeat(4, minmax(0, 1fr)); } .grid-2 { grid-template-columns:repeat(2, minmax(0, 1fr)); }
        h1 { margin:0 0 18px; font-size:28px; } h2 { margin:0 0 12px; font-size:19px; } .metric { font-size:28px; font-weight:800; }
        .muted { color:var(--muted); } .actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
        .btn { border:0; background:var(--brand); color:white; border-radius:6px; padding:10px 13px; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; min-height:38px; transition:transform .18s ease, box-shadow .18s ease, filter .18s ease; }
        .btn:hover { transform:translateY(-2px); filter:brightness(.92); box-shadow:0 7px 15px rgba(15,23,42,.2); }
        .btn:active { transform:translateY(0); box-shadow:none; }
        .btn:focus-visible { outline:3px solid rgba(229,9,9,.3); outline-offset:2px; }
        .btn.secondary { background:var(--brand2); } .btn.light { background:#e5e7eb; color:#111827; } .btn.danger { background:var(--danger); }
        .icon { width:19px; height:19px; display:block; flex:0 0 auto; } .icon-btn { width:38px; min-width:38px; padding:8px; } .actions .icon-btn { width:38px; }
        .table-responsive { width:100%; overflow-x:auto; border:1px solid var(--line); border-radius:8px; background:#fff; }
        .table-responsive table { border:0; border-radius:0; }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
        th, td { padding:12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:middle; } th { background:#eef2f7; font-size:13px; color:#374151; }
        input, select, textarea { width:100%; border:1px solid var(--line); border-radius:6px; padding:10px 11px; font:inherit; background:white; }
        label { display:grid; gap:6px; font-size:14px; font-weight:700; } textarea { min-height:86px; resize:vertical; }
        .form-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:14px; } .span-2 { grid-column:span 2; }
        .municipality-field { grid-column:span 2; } .municipality-combobox { position:relative; } .municipality-results { position:absolute; z-index:10; top:calc(100% + 4px); width:100%; max-height:260px; overflow-y:auto; border:1px solid var(--line); border-radius:6px; background:#fff; box-shadow:0 8px 18px rgba(15,23,42,.15); } .municipality-option { display:block; width:100%; padding:10px 11px; border:0; border-bottom:1px solid var(--line); background:#fff; color:var(--ink); text-align:left; cursor:pointer; } .municipality-option:last-child { border-bottom:0; } .municipality-option:hover, .municipality-option:focus { background:#fef2f2; outline:0; }
        .alert { border-radius:6px; padding:11px 13px; margin-bottom:14px; } .success { background:#dcfce7; color:#166534; } .error { background:#fee2e2; color:#991b1b; }
        .badge { display:inline-block; padding:4px 8px; border-radius:999px; background:#e5e7eb; font-size:12px; } .badge.warn { background:#fef3c7; color:var(--warn); }
        .pagination { margin-top:14px; } .pagination nav > .sm\:hidden { display:none; } .pagination nav > .hidden { display:flex; align-items:center; justify-content:space-between; gap:14px; } .pagination nav p { margin:0; color:var(--muted); font-size:14px; } .pagination nav > .hidden > div:last-child > span { display:inline-flex; overflow:hidden; border:1px solid var(--line); border-radius:7px; background:#fff; } .pagination nav a, .pagination nav [aria-current="page"] > span, .pagination nav [aria-disabled="true"] > span { display:inline-flex; align-items:center; justify-content:center; min-width:38px; min-height:38px; padding:7px 11px; color:#374151; border-left:1px solid var(--line); background:#fff; font-size:14px; font-weight:700; } .pagination nav > .hidden > div:last-child > span > :first-child > * { border-left:0; } .pagination nav a:hover { background:#fef2f2; color:var(--brand); } .pagination nav [aria-current="page"] > span { background:var(--brand); color:#fff; } .pagination nav [aria-disabled="true"] > span { color:#9ca3af; background:#f9fafb; cursor:not-allowed; } .pagination nav svg { width:17px; height:17px; }
        .logout { background:transparent; text-align:left; cursor:pointer; font:inherit; }
        @media (max-width: 900px) { .shell { display:block; } .menu-toggle { display:block; } .sidebar { width:min(82vw, 320px); height:100vh; position:fixed; inset:0 auto 0 0; overflow-y:auto; border-right:4px solid var(--brand); border-bottom:0; padding-top:68px; transform:translateX(-105%); transition:transform .22s ease; z-index:30; } .menu-open .sidebar { transform:translateX(0); } .content { margin-left:0; } .topbar { padding-left:68px; } .brand-block { grid-template-columns:1fr; align-items:center; } .brand-logo { max-width:150px; max-height:78px; } .nav { grid-template-columns:1fr; } .nav a, .logout { min-height:42px; font-size:16px; } .grid-4, .grid-2, .form-grid { grid-template-columns:1fr; } .span-2, .municipality-field { grid-column:auto; } .page { padding:18px; } .panel { padding:14px; } table { display:block; max-width:100%; overflow-x:auto; white-space:nowrap; } .table-responsive table { display:table; min-width:720px; } }
        @media (max-width: 560px) { .topbar { align-items:flex-start; gap:6px; flex-direction:column; padding:16px 18px 16px 68px; } .page { padding:14px; } .actions { align-items:stretch; } .actions .btn, .actions input, .actions form { width:100%; max-width:none !important; } .actions .icon-btn { width:42px; min-width:42px; } .pagination nav > .hidden { display:none; } .pagination nav > .sm\:hidden { display:flex; justify-content:space-between; gap:8px; } .pagination nav > .sm\:hidden > * { width:50%; border:1px solid var(--line); border-radius:7px; padding:10px; text-align:center; font-size:14px; font-weight:700; } h1 { font-size:24px; } }
    </style>
</head>
<body>
@auth
    <div class="shell">
        <button class="menu-toggle" type="button" aria-label="Abrir menu" aria-expanded="false"><span></span></button>
        <div class="menu-backdrop" data-menu-close></div>
        <aside class="sidebar">
            <div class="brand-block">
                <img class="brand-logo" src="{{ asset('images/titan-automotriz-logo.jpeg') }}" alt="Titan Automotriz">
                <div>
                    
                    <div class="role">{{ auth()->user()->role }}</div>
                </div>
            </div>
            <nav class="nav">
                <a href="{{ route('dashboard') }}" @class(['active' => request()->routeIs('dashboard')])>Panel</a>
                <a href="{{ route('cash.index') }}" @class(['active' => request()->routeIs('cash.*')])>Cierre de caja</a>
                <a href="{{ route('sales.create') }}" @class(['active' => request()->routeIs('sales.create')])>Nueva venta</a>
                <a href="{{ route('sales.index') }}" @class(['active' => request()->routeIs('sales.index', 'sales.show')])>Ventas</a>
                <a href="{{ route('products.index') }}" @class(['active' => request()->routeIs('products.*')])>Productos</a>
                <a href="{{ route('services.index') }}" @class(['active' => request()->routeIs('services.*')])>Servicios</a>
                <a href="{{ route('customers.index') }}" @class(['active' => request()->routeIs('customers.*')])>Clientes</a>
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('credits.index') }}" @class(['active' => request()->routeIs('credits.*')])>Creditos</a>
                    <a href="{{ route('categories.index') }}" @class(['active' => request()->routeIs('categories.*')])>Categorias</a>
                    <a href="{{ route('suppliers.index') }}" @class(['active' => request()->routeIs('suppliers.*')])>Proveedores</a>
                    <a href="{{ route('expenses.index') }}" @class(['active' => request()->routeIs('expenses.*')])>Gastos</a>
                    <a href="{{ route('users.index') }}" @class(['active' => request()->routeIs('users.*')])>Usuarios</a>
                @endif
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="logout" type="submit">Cerrar sesion</button>
                </form>
            </nav>
        </aside>
        <main class="content">
            <div class="topbar">
                <strong>@yield('title', 'Panel')</strong>
                <span class="muted">{{ auth()->user()->name }}</span>
            </div>
            <div class="page">
                @if(session('success')) <div class="alert success">{{ session('success') }}</div> @endif
                @if($errors->any()) <div class="alert error">{{ $errors->first() }}</div> @endif
                @yield('content')
            </div>
        </main>
    </div>
@else
    @yield('content')
@endauth
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Listo',
                text: @json(session('success')),
                confirmButtonColor: '#e50909',
                timer: 2400,
                timerProgressBar: true
            });
        @endif

        @if($errors->any())
            Swal.fire({
                icon: 'error',
                title: 'Revisa la informacion',
                text: @json($errors->first()),
                confirmButtonColor: '#e50909'
            });
        @endif

        document.querySelectorAll('.swal-confirm').forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();

                Swal.fire({
                    icon: 'warning',
                    title: button.dataset.title || 'Confirmar accion',
                    text: button.dataset.text || 'Esta accion no se puede deshacer facilmente.',
                    showCancelButton: true,
                    confirmButtonText: button.dataset.confirm || 'Si, continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#e50909',
                    cancelButtonColor: '#6b7280',
                    reverseButtons: true
                }).then((result) => {
                    if (result.isConfirmed) {
                        button.closest('form').submit();
                    }
                });
            });
        });

        const menuToggle = document.querySelector('.menu-toggle');
        const menuCloseTargets = document.querySelectorAll('[data-menu-close], .nav a, .logout');
        const setMenuOpen = (open) => {
            document.body.classList.toggle('menu-open', open);
            menuToggle?.setAttribute('aria-expanded', open ? 'true' : 'false');
            menuToggle?.setAttribute('aria-label', open ? 'Cerrar menu' : 'Abrir menu');
        };

        menuToggle?.addEventListener('click', () => {
            setMenuOpen(! document.body.classList.contains('menu-open'));
        });

        menuCloseTargets.forEach((target) => {
            target.addEventListener('click', () => setMenuOpen(false));
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth > 900) {
                setMenuOpen(false);
            }
        });
    });
</script>
</body>
</html>
