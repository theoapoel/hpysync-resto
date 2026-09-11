<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route as RouteFacade;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ] : null,
            ],
            'nav' => fn () => $user ? $this->buildNav($user, $request) : [],
            'pendingSync' => fn () => $user ? $this->pendingSyncCount() : 0,
            'appName' => config('app.name', 'HPYSync Resto'),
            'logoUrl' => asset('images/happypos.png'),
            'dashboardUrl' => route('dashboard'),
            'syncUrl' => fn () => RouteFacade::has('sync.index') ? route('sync.index') : null,
            'logoutUrl' => fn () => $user ? route('logout') : null,
            'erpPingUrl' => fn () => $user && RouteFacade::has('sync.ping') ? route('sync.ping') : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }

    /**
     * Mirrors the section/menu structure of resources/views/layouts/app.blade.php,
     * gated by the same RolePermission modules.
     */
    /**
     * Route names that have been migrated to Inertia pages so far.
     * Everything else still returns classic Blade HTML — those must be
     * navigated to with a real full-page link, never Inertia's <Link>,
     * or Inertia's client shows the raw HTML in its error dialog.
     */
    protected const INERTIA_ROUTES = [
        'dashboard', 'transactions.index', 'products.index', 'products.create', 'products.edit',
        'customers.index', 'stock.index', 'stock-opname.index', 'stock-opname.create', 'stock-opname.show',
        'slices.index', 'slices.create', 'slices.show',
    ];

    protected function buildNav($user, Request $request): array
    {
        $map = [
            'dashboard' => ['route' => 'dashboard',             'active' => ['dashboard']],
            'pos' => ['route' => 'pos.kasir',             'active' => ['pos.index', 'pos.quick', 'pos.express']],
            'transactions' => ['route' => 'transactions.index',    'active' => ['transactions.*']],

            'products' => ['route' => 'products.index',        'active' => ['products.*']],
            'customers' => ['route' => 'customers.index',       'active' => ['customers.*']],
            'stock' => ['route' => 'stock.index',           'active' => ['stock.index', 'stock.debug*', 'stock.sync*']],
            'stock_opname' => ['route' => 'stock-opname.index',    'active' => ['stock-opname.*']],
            'slice' => ['route' => 'slices.index',          'active' => ['slices.*']],
            'stock_transfer' => ['route' => 'stock-transfer.index',  'active' => ['stock-transfer.*']],

            'delivery' => ['route' => 'delivery-orders.index', 'active' => ['delivery-orders.*']],
            'delivery_notes' => ['route' => 'delivery-notes.index',  'active' => ['delivery-notes.*']],
            'stock_request' => ['route' => 'stock-requests.index',  'active' => ['stock-requests.*']],
            'pulling_order' => ['route' => 'pulling-order.index',   'active' => ['pulling-order.*']],
            'rekap_order' => ['route' => 'rekap-order.index',     'active' => ['rekap-order.*']],
            'kitchen' => ['route' => 'kitchen.index',         'active' => ['kitchen.*']],

            'sync' => ['route' => 'sync.index',            'active' => ['sync.*'], 'badge' => true],
            'online_report' => ['route' => 'online-report.index',   'active' => ['online-report.*']],
            'mop_report' => ['route' => 'mop-report.index',      'active' => ['mop-report.*']],
            'do_report' => ['route' => 'do-report.index',       'active' => ['do-report.*']],

            'coupons' => ['route' => 'coupons.index',         'active' => ['coupons.*']],
            'users' => ['route' => 'users.index',           'active' => ['users.*']],
            'roles' => ['route' => 'roles.index',           'active' => ['roles.*']],
            'permissions' => ['route' => 'permissions.index',     'active' => ['permissions.*']],
            'warehouses' => ['route' => 'warehouses.index',      'active' => ['warehouses.*']],
            'settings' => ['route' => 'settings.index',        'active' => ['settings.*']],
            'backup' => ['route' => 'backup.restore',        'active' => ['backup.*']],
            'update' => ['route' => 'update.index',          'active' => ['update.*']],
            'factory_reset' => ['route' => 'factory-reset.index',   'active' => ['factory-reset.*'], 'danger' => true],
        ];

        $sections = [
            'Menu' => ['dashboard', 'pos', 'transactions'],
            'Manajemen' => ['products', 'customers', 'stock', 'stock_opname', 'slice', 'stock_transfer'],
            'Delivery & Dapur' => ['delivery', 'delivery_notes', 'stock_request', 'pulling_order', 'rekap_order', 'kitchen'],
            'Integrasi' => ['sync', 'online_report', 'mop_report', 'do_report'],
            'Sistem' => ['coupons', 'users', 'roles', 'permissions', 'warehouses', 'settings', 'backup', 'update', 'factory_reset'],
        ];

        $modules = RolePermission::modules();
        $pendingSync = $this->pendingSyncCount();
        $nav = [];

        foreach ($sections as $section => $keys) {
            $items = [];
            foreach ($keys as $key) {
                if (! isset($map[$key]) || ! $user->hasPermission($key) || ! RouteFacade::has($map[$key]['route'])) {
                    continue;
                }
                $items[] = [
                    'key' => $key,
                    'label' => $modules[$key]['label'] ?? $key,
                    'icon' => $modules[$key]['icon'] ?? 'fa-circle',
                    'href' => route($map[$key]['route']),
                    'active' => $request->routeIs(...$map[$key]['active']),
                    'badge' => ($map[$key]['badge'] ?? false) && $pendingSync > 0 ? $pendingSync : null,
                    'danger' => $map[$key]['danger'] ?? false,
                    'inertia' => in_array($map[$key]['route'], self::INERTIA_ROUTES, true),
                ];
            }
            if ($items) {
                $nav[] = ['section' => $section, 'items' => $items];
            }
        }

        return $nav;
    }

    protected function pendingSyncCount(): int
    {
        return Transaction::where('erp_sync_status', 'pending')->where('status', 'completed')->count();
    }
}
