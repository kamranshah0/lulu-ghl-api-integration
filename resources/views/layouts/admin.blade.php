<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name') }} | Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Outfit:wght@100..900&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --bg: #f8fafc;
            --sidebar-bg: #0f172a;
            --sidebar-text: #94a3b8;
            --sidebar-active: #ffffff;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --sidebar-width: 280px;
            --header-height: 72px;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        aside {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            z-index: 50;
            transition: all 0.3s ease;
        }

        .sidebar-brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 2rem;
            gap: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .logo-square {
            width: 32px;
            height: 32px;
            background: var(--primary);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 800;
            font-size: 1.125rem;
        }

        .brand-name {
            font-family: 'Outfit', sans-serif;
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            letter-spacing: -0.02em;
        }

        .sidebar-content {
            flex: 1;
            padding: 2rem 1rem;
            overflow-y: auto;
        }

        .nav-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 1rem;
            margin-left: 1rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.875rem;
            padding: 0.875rem 1.25rem;
            border-radius: 0.75rem;
            color: var(--sidebar-text);
            text-decoration: none;
            margin-bottom: 0.5rem;
            transition: all 0.2s ease;
            font-weight: 500;
            font-size: 0.9375rem;
        }

        .nav-item i {
            width: 18px;
            height: 18px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            color: #fff;
        }

        .nav-item.active {
            background: var(--primary);
            color: #fff;
            box-shadow: 0 10px 15px -3px rgba(99, 102, 241, 0.3);
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border-radius: 0.75rem;
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 600;
            border: 1px solid rgba(239, 68, 68, 0.1);
            transition: all 0.2s;
            cursor: pointer;
        }

        .logout-btn:hover {
            background: #ef4444;
            color: #fff;
            border-color: #ef4444;
        }

        .logout-btn i {
            width: 18px;
            height: 18px;
        }

        /* Main Content wrapper */
        .wrapper {
            flex: 1;
            margin-left: var(--sidebar-width);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* Header */
        header.top-bar {
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 40;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2.5rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-muted);
            font-weight: 500;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info { text-align: right; }
        .user-name { font-weight: 600; font-size: 0.875rem; }
        .user-role { font-size: 0.75rem; color: var(--text-muted); }

        .avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-light);
            border-radius: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 700;
        }

        main {
            padding: 2.5rem;
            flex: 1;
        }

        /* Cards & UI Polish */
        .card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            padding: 2rem;
            box-shadow: var(--shadow);
            transition: box-shadow 0.3s ease;
        }

        .card:hover { box-shadow: var(--shadow-md); }

        h1, h2, h3 { font-family: 'Outfit', sans-serif; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            gap: 0.625rem;
            font-size: 0.875rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .btn-outline {
            background-color: transparent;
            border: 1.5px solid var(--border);
            color: var(--text-main);
        }

        .btn-outline:hover { background-color: #f1f5f9; border-color: #94a3b8; }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.875rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }

        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-warning { background: #fef9c3; color: #a16207; }
        .badge-danger { background: #fee2e2; color: #b91c1c; }
        .badge-info { background: #e0e7ff; color: #4338ca; }

        /* Tables Refinement */
        table { width: 100%; border-collapse: separate; border-spacing: 0; }
        th {
            text-align: left;
            padding: 1.25rem 1rem;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 2px solid var(--bg);
        }
        td { padding: 1.25rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.9375rem; vertical-align: middle; }
        tr:hover td { background-color: #f8fafc; }

        @media (max-width: 1024px) {
            aside { width: 80px; }
            .brand-name, .nav-text, .nav-label, .user-info { display: none; }
            .sidebar-brand, .nav-item { justify-content: center; padding: 1rem; }
            .wrapper { margin-left: 80px; }
        }
    </style>
</head>
<body>
    <aside>
        <div class="sidebar-brand">
            <div class="logo-square">F</div>
            <span class="brand-name">Forever Wellness</span>
        </div>
        
        <div class="sidebar-content">
            <div class="nav-label">Main Menu</div>
            <nav>
                <a href="{{ route('admin.dashboard') }}" class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i data-lucide="layout-dashboard"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
                <a href="{{ route('admin.orders.index') }}" class="nav-item {{ request()->routeIs('admin.orders.index*') ? 'active' : '' }}">
                    <i data-lucide="package"></i>
                    <span class="nav-text">Orders</span>
                </a>
                <a href="{{ route('admin.orders.failed') }}" class="nav-item {{ request()->routeIs('admin.orders.failed') ? 'active' : '' }}">
                    <i data-lucide="alert-circle"></i>
                    <span class="nav-text">Failed Queue</span>
                </a>
            </nav>

            <div class="nav-label" style="margin-top: 2rem;">System</div>
            <nav>
                <a href="{{ route('admin.settings') }}" class="nav-item {{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                    <i data-lucide="settings"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </nav>
        </div>

        <div class="sidebar-footer">
            <form action="{{ route('admin.logout') }}" method="POST" id="logout-form">
                @csrf
                <button type="submit" class="logout-btn">
                    <i data-lucide="log-out"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="wrapper">
        <header class="top-bar">
            <div class="breadcrumb">
                <span>Admin</span>
                <span>/</span>
                <span style="color: var(--text-main); font-weight: 700;">{{ ucfirst(request()->segment(2) ?? 'Dashboard') }}</span>
            </div>

            <div class="user-profile">
                <div class="user-info">
                    <div class="user-name">Administrator</div>
                    <div class="user-role">Super Admin</div>
                </div>
                <div class="avatar">AD</div>
            </div>
        </header>

        <main>
            @if(session('success'))
                <div style="background: #dcfce7; color: #15803d; padding: 1rem 1.5rem; border-radius: 1rem; margin-bottom: 2rem; border: 1px solid #10b981; font-weight: 600; display: flex; align-items: center; gap: 0.75rem;">
                    <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i> {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div style="background: #fee2e2; color: #b91c1c; padding: 1rem 1.5rem; border-radius: 1rem; margin-bottom: 2rem; border: 1px solid #ef4444; font-weight: 600; display: flex; align-items: center; gap: 0.75rem;">
                    <i data-lucide="x-circle" style="width: 20px; height: 20px;"></i> {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
