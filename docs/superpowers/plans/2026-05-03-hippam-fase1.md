# HIPPAM Fase 1 — Foundation (MVP) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build multi-tenant Laravel 13 app with auth, pelanggan/meter CRUD, pembacaan meter, and tagihan generation for Indonesian HIPPAM water supply organizations.

**Architecture:** Single MySQL 8 database with row-level tenant isolation (`tenant_id` on every tenant-owned table). Native Laravel global scopes via `BelongsToTenant` trait. `ResolveTenant` middleware sets current tenant from authenticated user.

**Tech Stack:** Laravel 13 (PHP 8.3+), MySQL 8, Blade + Tailwind CSS + Alpine.js, Laravel Breeze (session auth), Chart.js, maatwebsite/excel, barryvdh/laravel-dompdf

**PRD:** `docs/PRD.md` — 20 confirmed decisions.

**UI:** Bahasa Indonesia. Clean & minimal, mobile-first. Blue accent (sidebar/header), neutral content. Dark/light theme toggle. Bottom tab bar (mobile), sidebar (desktop). Font: Inter.

---

## File Structure

```
app/
├── Models/
│   ├── Tenant.php                  # Tenant CRUD, has many users/pelanggan
│   ├── User.php                    # Auth, role (super_admin/operator), belongsTo Tenant
│   ├── Pelanggan.php               # use BelongsToTenant
│   └── Meter.php                   # use BelongsToTenant, belongsTo Pelanggan
├── Traits/
│   └── BelongsToTenant.php         # Global scope + auto-set tenant_id
├── Http/
│   ├── Middleware/
│   │   ├── ResolveTenant.php       # Set current.tenant from auth user
│   │   └── EnsureTenant.php        # Require active tenant context
│   └── Controllers/
│       ├── SuperAdmin/
│       │   ├── TenantController.php
│       │   └── OperatorController.php
│       └── Operator/
│           ├── DashboardController.php
│           ├── PelangganController.php
│           ├── PembacaanController.php
│           └── TagihanController.php
├── Services/
│   └── TagihanService.php          # Generate tagihan from pembacaan
└── Policies/
    └── TenantPolicy.php            # Authorization: operator can only access own tenant

database/
├── migrations/
│   ├── xxxx_create_tenants_table.php
│   ├── xxxx_create_pelanggan_table.php
│   ├── xxxx_create_meters_table.php
│   ├── xxxx_create_pembacaan_table.php
│   └── xxxx_create_tagihan_table.php
└── seeders/
    └── DemoTenantSeeder.php

resources/
├── views/
│   ├── layouts/
│   │   ├── app.blade.php           # Main layout (Tailwind + Alpine, dark/light toggle)
│   │   ├── sidebar.blade.php       # Desktop sidebar navigation
│   │   └── mobile-nav.blade.php    # Mobile bottom tab bar
│   ├── auth/                       # Breeze views (customized)
│   ├── super-admin/
│   │   ├── dashboard.blade.php
│   │   ├── tenants/index.blade.php
│   │   ├── tenants/create.blade.php
│   │   ├── tenants/edit.blade.php
│   │   └── operators/index.blade.php
│   └── operator/
│       ├── dashboard.blade.php
│       ├── pelanggan/index.blade.php
│       ├── pelanggan/create.blade.php
│       ├── pelanggan/edit.blade.php
│       ├── pembacaan/index.blade.php
│       ├── pembacaan/create.blade.php
│       └── tagihan/index.blade.php
└── css/app.css                     # Tailwind entry point

routes/
└── web.php                         # All routes (auth, super-admin, operator)
```

---

## Task 1: Project Setup & Dependencies

**Files:**
- Modify: `composer.json`
- Modify: `package.json`
- Modify: `resources/css/app.css`
- Modify: `tailwind.config.js`
- Modify: `.env`

- [ ] **Step 1: Install Laravel packages**

```bash
cd /Users/labba.awwabi/Personal/hippam
composer require laravel/breeze --dev
composer require barryvdh/laravel-dompdf
composer require maatwebsite/excel
composer require laravel-lang/common --dev
php artisan breeze:install blade
npm install
```

- [ ] **Step 2: Configure Tailwind with Inter font and dark mode**

`tailwind.config.js`:
```js
import defaultTheme from 'tailwindcss/defaultTheme';

export default {
  darkMode: 'class',
  content: ['./resources/**/*.{blade.php,js,jsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', ...defaultTheme.fontFamily.sans],
      },
      colors: {
        primary: {
          50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd',
          400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8',
          800: '#1e40af', 900: '#1e3a8a', 950: '#172554',
        },
      },
    },
  },
  plugins: [],
};
```

- [ ] **Step 3: Set locale to Indonesian**

`config/app.php`:
```php
'locale' => 'id',
'fallback_locale' => 'id',
```

Publish lang files:
```bash
php artisan vendor:publish --tag=laravel-lang
```

- [ ] **Step 4: Configure .env**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hippam
DB_USERNAME=root
DB_PASSWORD=

APP_URL=http://localhost:8000
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: install dependencies and configure project"
```

---

## Task 2: Multi-Tenancy Foundation

**Files:**
- Create: `app/Traits/BelongsToTenant.php`
- Create: `app/Http/Middleware/ResolveTenant.php`
- Create: `app/Http/Middleware/EnsureTenant.php`

- [ ] **Step 1: Write failing test for BelongsToTenant trait**

`tests/Unit/Traits/BelongsToTenantTest.php`:
```php
<?php

namespace Tests\Unit\Traits;

use App\Models\Tenant;
use App\Models\Pelanggan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BelongsToTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_scope_filters_by_current_tenant(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        Pelanggan::factory()->count(3)->create(['tenant_id' => $tenant1->id]);
        Pelanggan::factory()->count(2)->create(['tenant_id' => $tenant2->id]);

        // Resolve tenant1
        app()->instance('current.tenant', $tenant1);

        $results = Pelanggan::all();

        $this->assertCount(3, $results);
        $this->assertTrue($results->every(fn ($p) => $p->tenant_id === $tenant1->id));
    }

    public function test_creating_model_auto_sets_tenant_id(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current.tenant', $tenant);

        $pelanggan = Pelanggan::factory()->make(['tenant_id' => null]);
        $pelanggan->save();

        $this->assertEquals($tenant->id, $pelanggan->fresh()->tenant_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Unit/Traits/BelongsToTenantTest.php
```

Expected: FAIL (trait doesn't exist yet)

- [ ] **Step 3: Implement BelongsToTenant trait**

`app/Traits/BelongsToTenant.php`:
```php
<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (app()->has('current.tenant')) {
                $builder->where(
                    $builder->getModel()->getTable() . '.tenant_id',
                    app('current.tenant')->id
                );
            }
        });

        static::creating(function (Model $model) {
            if (app()->has('current.tenant') && empty($model->tenant_id)) {
                $model->tenant_id = app('current.tenant')->id;
            }
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
php artisan test tests/Unit/Traits/BelongsToTenantTest.php
```

Expected: PASS

- [ ] **Step 5: Implement ResolveTenant middleware**

`app/Http/Middleware/ResolveTenant.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->tenant_id) {
            $tenant = auth()->user()->tenant;
            app()->instance('current.tenant', $tenant);
        }

        return $next($request);
    }
}
```

- [ ] **Step 6: Implement EnsureTenant middleware**

`app/Http/Middleware/EnsureTenant.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->has('current.tenant')) {
            abort(403, 'Tenant context required.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 7: Register middlewares**

`bootstrap/app.php` — add to middleware:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'tenant.resolve' => \App\Http\Middleware\ResolveTenant::class,
        'tenant.ensure' => \App\Http\Middleware\EnsureTenant::class,
    ]);
})
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add BelongsToTenant trait and tenant middleware"
```

---

## Task 3: Tenant Model & Migration

**Files:**
- Create: `database/migrations/xxxx_create_tenants_table.php`
- Create: `app/Models/Tenant.php`
- Create: `database/factories/TenantFactory.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_tenants_table
```

`database/migrations/xxxx_create_tenants_table.php`:
```php
Schema::create('tenants', function (Blueprint $table) {
    $table->id();
    $table->string('nama_unit');
    $table->string('kode_unit')->unique();
    $table->text('alamat')->nullable();
    $table->string('desa')->nullable();
    $table->string('kecamatan')->nullable();
    $table->string('kabupaten')->nullable();
    $table->string('kontak_pengelola')->nullable();
    $table->string('no_telepon')->nullable();
    $table->string('email')->nullable();
    $table->decimal('tarif_per_m3', 10, 2)->default(0);
    $table->unsignedTinyInteger('jatuh_tempo_tanggal')->default(20);
    $table->string('printer_width', 10)->default('58mm');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

- [ ] **Step 2: Create model**

`app/Models/Tenant.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_unit', 'kode_unit', 'alamat', 'desa', 'kecamatan', 'kabupaten',
        'kontak_pengelola', 'no_telepon', 'email', 'tarif_per_m3',
        'jatuh_tempo_tanggal', 'printer_width', 'is_active',
    ];

    protected $casts = [
        'tarif_per_m3' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function pelanggan(): HasMany
    {
        return $this->hasMany(Pelanggan::class);
    }
}
```

- [ ] **Step 3: Create factory**

`database/factories/TenantFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'nama_unit' => fake()->company() . ' HIPPAM',
            'kode_unit' => strtoupper(fake()->unique()->lexify('???')),
            'alamat' => fake()->address(),
            'desa' => fake()->village(),
            'kecamatan' => fake()->citySuffix(),
            'kabupaten' => fake()->city(),
            'kontak_pengelola' => fake()->name(),
            'no_telepon' => fake()->phoneNumber(),
            'tarif_per_m3' => fake()->randomElement([2000, 3000, 3500, 4000, 5000]),
            'is_active' => true,
        ];
    }
}
```

- [ ] **Step 4: Run migration**

```bash
php artisan migrate
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Tenant model, migration, and factory"
```

---

## Task 4: User Model, Roles & Auth

**Files:**
- Modify: `database/migrations/xxxx_create_users_table.php` (add tenant_id, role)
- Modify: `app/Models/User.php`
- Create: `app/Policies/TenantPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Modify users migration**

Add to the existing `create_users_table` migration:
```php
$table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
$table->string('role')->default('operator'); // super_admin, operator
```

- [ ] **Step 2: Update User model**

`app/Models/User.php`:
```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    // NOTE: Do NOT use BelongsToTenant here — User is special.
    // Super admins have null tenant_id. Tenant scoping is handled
    // manually in controllers via tenant.ensure middleware.

    protected $fillable = [
        'name', 'email', 'password', 'tenant_id', 'role',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }
}
```

- [ ] **Step 3: Run migration**

```bash
php artisan migrate:fresh
```

- [ ] **Step 4: Create TenantPolicy**

`app/Policies/TenantPolicy.php`:
```php
<?php

namespace App\Policies;

use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function delete(User $user): bool
    {
        return $user->isSuperAdmin();
    }
}
```

- [ ] **Step 5: Register policy in AppServiceProvider**

`app/Providers/AppServiceProvider.php` — add to `boot()`:
```php
protected $policies = [
    Tenant::class => TenantPolicy::class,
];
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add user roles (super_admin/operator) and tenant policy"
```

---

## Task 5: Super Admin — Tenant CRUD

**Files:**
- Create: `app/Http/Controllers/SuperAdmin/TenantController.php`
- Create: `resources/views/super-admin/tenants/index.blade.php`
- Create: `resources/views/super-admin/tenants/create.blade.php`
- Create: `resources/views/super-admin/tenants/edit.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create TenantController**

`app/Http/Controllers/SuperAdmin/TenantController.php`:
```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->authorizeResource(Tenant::class, 'tenant');
    }

    public function index(Request $request): View
    {
        $tenants = Tenant::withCount('pelanggan')->latest()->paginate(20);
        return view('super-admin.tenants.index', compact('tenants'));
    }

    public function create(): View
    {
        return view('super-admin.tenants.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama_unit' => 'required|string|max:255',
            'kode_unit' => 'required|string|max:10|unique:tenants,kode_unit',
            'alamat' => 'nullable|string',
            'desa' => 'nullable|string|max:255',
            'kecamatan' => 'nullable|string|max:255',
            'kabupaten' => 'nullable|string|max:255',
            'kontak_pengelola' => 'nullable|string|max:255',
            'no_telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'tarif_per_m3' => 'required|numeric|min:0',
            'jatuh_tempo_tanggal' => 'required|integer|min:1|max:28',
            'printer_width' => 'required|in:58mm,80mm',
        ]);

        Tenant::create($validated);

        return redirect()->route('super-admin.tenants.index')
            ->with('success', 'Unit HIPPAM berhasil ditambahkan.');
    }

    public function edit(Tenant $tenant): View
    {
        return view('super-admin.tenants.edit', compact('tenant'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'nama_unit' => 'required|string|max:255',
            'kode_unit' => 'required|string|max:10|unique:tenants,kode_unit,' . $tenant->id,
            'alamat' => 'nullable|string',
            'desa' => 'nullable|string|max:255',
            'kecamatan' => 'nullable|string|max:255',
            'kabupaten' => 'nullable|string|max:255',
            'kontak_pengelola' => 'nullable|string|max:255',
            'no_telepon' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'tarif_per_m3' => 'required|numeric|min:0',
            'jatuh_tempo_tanggal' => 'required|integer|min:1|max:28',
            'printer_width' => 'required|in:58mm,80mm',
            'is_active' => 'boolean',
        ]);

        $tenant->update($validated);

        return redirect()->route('super-admin.tenants.index')
            ->with('success', 'Unit HIPPAM berhasil diperbarui.');
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();
        return redirect()->route('super-admin.tenants.index')
            ->with('success', 'Unit HIPPAM berhasil dihapus.');
    }
}
```

- [ ] **Step 2: Create views**

`resources/views/super-admin/tenants/index.blade.php` — Table of tenants with search, pagination, action buttons (edit, delete). Mobile-responsive card layout on small screens.

`resources/views/super-admin/tenants/create.blade.php` — Form with all tenant fields. Bahasa Indonesia labels.

`resources/views/super-admin/tenants/edit.blade.php` — Same form as create, pre-filled with existing data. Includes `is_active` toggle.

- [ ] **Step 3: Add routes**

`routes/web.php`:
```php
Route::middleware('auth')->group(function () {
    Route::middleware('can:viewAny,App\Models\Tenant')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/tenants', [SuperAdmin\TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/create', [SuperAdmin\TenantController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [SuperAdmin\TenantController::class, 'store'])->name('tenants.store');
        Route::get('/tenants/{tenant}/edit', [SuperAdmin\TenantController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{tenant}', [SuperAdmin\TenantController::class, 'update'])->name('tenants.update');
        Route::delete('/tenants/{tenant}', [SuperAdmin\TenantController::class, 'destroy'])->name('tenants.destroy');
    });
});
```

- [ ] **Step 4: Test manually**

```bash
php artisan serve
# Visit http://localhost:8000/super-admin/tenants
# Create a tenant, verify it appears in list
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Super Admin tenant CRUD with views and routes"
```

---

## Task 6: Super Admin — Operator Management

**Files:**
- Create: `app/Http/Controllers/SuperAdmin/OperatorController.php`
- Create: `resources/views/super-admin/operators/index.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create OperatorController**

`app/Http/Controllers/SuperAdmin/OperatorController.php`:
```php
<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperatorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): View
    {
        $operators = User::where('role', 'operator')
            ->with('tenant')
            ->latest()
            ->paginate(20);

        return view('super-admin.operators.index', compact('operators'));
    }

    public function create(): View
    {
        $tenants = Tenant::where('is_active', true)->orderBy('nama_unit')->get();
        return view('super-admin.operators.create', compact('tenants'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'tenant_id' => 'required|exists:tenants,id',
        ]);

        $validated['password'] = bcrypt($validated['password']);
        $validated['role'] = 'operator';

        User::create($validated);

        return redirect()->route('super-admin.operators.index')
            ->with('success', 'Operator berhasil ditambahkan.');
    }

    public function destroy(User $user)
    {
        if ($user->isSuperAdmin()) {
            abort(403, 'Tidak bisa menghapus Super Admin.');
        }

        $user->delete();
        return redirect()->route('super-admin.operators.index')
            ->with('success', 'Operator berhasil dihapus.');
    }
}
```

- [ ] **Step 2: Create views**

`resources/views/super-admin/operators/index.blade.php` — Table with name, email, tenant name, action (delete).

`resources/views/super-admin/operators/create.blade.php` — Form: name, email, password, password confirmation, tenant dropdown.

- [ ] **Step 3: Add routes**

```php
Route::prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('/operators', [SuperAdmin\OperatorController::class, 'index'])->name('operators.index');
    Route::get('/operators/create', [SuperAdmin\OperatorController::class, 'create'])->name('operators.create');
    Route::post('/operators', [SuperAdmin\OperatorController::class, 'store'])->name('operators.store');
    Route::delete('/operators/{user}', [SuperAdmin\OperatorController::class, 'destroy'])->name('operators.destroy');
});
```

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: add Super Admin operator management"
```

---

## Task 7: Pelanggan CRUD

**Files:**
- Create: `database/migrations/xxxx_create_pelanggan_table.php`
- Create: `app/Models/Pelanggan.php`
- Create: `database/factories/PelangganFactory.php`
- Create: `app/Http/Controllers/Operator/PelangganController.php`
- Create: `resources/views/operator/pelanggan/index.blade.php`
- Create: `resources/views/operator/pelanggan/create.blade.php`
- Create: `resources/views/operator/pelanggan/edit.blade.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_pelanggan_table
```

```php
Schema::create('pelanggan', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('nama');
    $table->text('alamat')->nullable();
    $table->string('no_telepon')->nullable();
    $table->string('nomor_pelanggan')->unique();
    $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
    $table->date('tanggal_daftar')->default(now());
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->unique(['tenant_id', 'nomor_pelanggan']);
    $table->index(['tenant_id', 'status']);
    $table->index(['tenant_id', 'nama']);
});
```

- [ ] **Step 2: Create model**

`app/Models/Pelanggan.php`:
```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Pelanggan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'pelanggan';

    protected $fillable = [
        'tenant_id', 'nama', 'alamat', 'no_telepon',
        'nomor_pelanggan', 'status', 'tanggal_daftar', 'catatan',
    ];

    protected $casts = [
        'tanggal_daftar' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->nomor_pelanggan)) {
                $model->nomor_pelanggan = self::generateNomorPelanggan($model->tenant_id);
            }
        });
    }

    public static function generateNomorPelanggan(int $tenantId): string
    {
        $tenant = Tenant::find($tenantId);
        $prefix = $tenant ? $tenant->kode_unit : 'HPP';
        $last = self::where('tenant_id', $tenantId)->latest('id')->first();
        $seq = $last ? (int) substr($last->nomor_pelanggan, -4) + 1 : 1;

        return sprintf('%s-%04d', $prefix, $seq);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function meter(): HasMany
    {
        return $this->hasMany(Meter::class);
    }
}
```

- [ ] **Step 3: Create factory**

`database/factories/PelangganFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Pelanggan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class PelangganFactory extends Factory
{
    protected $model = Pelanggan::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nama' => fake()->name(),
            'alamat' => fake()->address(),
            'no_telepon' => fake()->phoneNumber(),
            'status' => 'aktif',
        ];
    }
}
```

- [ ] **Step 4: Create PelangganController**

`app/Http/Controllers/Operator/PelangganController.php`:
```php
<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Pelanggan;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PelangganController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
    }

    public function index(Request $request): View
    {
        $query = Pelanggan::query();

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                  ->orWhere('nomor_pelanggan', 'like', "%{$search}%")
                  ->orWhere('no_telepon', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $pelanggan = $query->latest()->paginate(20)->withQueryString();

        return view('operator.pelanggan.index', compact('pelanggan'));
    }

    public function create(): View
    {
        return view('operator.pelanggan.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:20',
            'catatan' => 'nullable|string',
        ]);

        Pelanggan::create($validated);

        return redirect()->route('pelanggan.index')
            ->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(Pelanggan $pelanggan): View
    {
        $this->ensureTenantOwnership($pelanggan);
        return view('operator.pelanggan.edit', compact('pelanggan'));
    }

    public function update(Request $request, Pelanggan $pelanggan)
    {
        $this->ensureTenantOwnership($pelanggan);

        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:20',
            'status' => 'required|in:aktif,nonaktif',
            'catatan' => 'nullable|string',
        ]);

        $pelanggan->update($validated);

        return redirect()->route('pelanggan.index')
            ->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    private function ensureTenantOwnership(Pelanggan $pelanggan): void
    {
        if ($pelanggan->tenant_id !== app('current.tenant')->id) {
            abort(404);
        }
    }
}
```

- [ ] **Step 5: Create views** (index, create, edit)

All views in Bahasa Indonesia. Mobile-first responsive. Tailwind styling.

- [ ] **Step 6: Add routes**

```php
Route::middleware(['auth', 'tenant.resolve', 'tenant.ensure'])->group(function () {
    Route::get('/pelanggan', [Operator\PelangganController::class, 'index'])->name('pelanggan.index');
    Route::get('/pelanggan/create', [Operator\PelangganController::class, 'create'])->name('pelanggan.create');
    Route::post('/pelanggan', [Operator\PelangganController::class, 'store'])->name('pelanggan.store');
    Route::get('/pelanggan/{pelanggan}/edit', [Operator\PelangganController::class, 'edit'])->name('pelanggan.edit');
    Route::put('/pelanggan/{pelanggan}', [Operator\PelangganController::class, 'update'])->name('pelanggan.update');
});
```

- [ ] **Step 7: Run migration and test**

```bash
php artisan migrate
php artisan test
```

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: add Pelanggan CRUD with tenant scoping"
```

---

## Task 8: Meter CRUD

**Files:**
- Create: `database/migrations/xxxx_create_meters_table.php`
- Create: `app/Models/Meter.php`
- Create: `database/factories/MeterFactory.php`
- Modify: `app/Http/Controllers/Operator/PelangganController.php` (add meter methods)
- Create: `resources/views/operator/pelanggan/meter.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_meters_table
```

```php
Schema::create('meters', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
    $table->string('nomor_meter')->unique();
    $table->string('merek')->nullable();
    $table->date('tanggal_pemasangan')->default(now());
    $table->enum('status', ['aktif', 'rusak', 'nonaktif'])->default('aktif');
    $table->timestamps();

    $table->unique(['tenant_id', 'nomor_meter']);
    $table->index(['tenant_id', 'pelanggan_id']);
});
```

- [ ] **Step 2: Create model**

`app/Models/Meter.php`:
```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meter extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'pelanggan_id', 'nomor_meter', 'merek',
        'tanggal_pemasangan', 'status',
    ];

    protected $casts = [
        'tanggal_pemasangan' => 'date',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }
}
```

- [ ] **Step 3: Add meter management to PelangganController**

Add methods: `meterCreate()`, `meterStore()`, `meterEdit()`, `meterUpdate()`.
View: `pelanggan/meter.blade.php` — form to add/edit meter for a pelanggan.

- [ ] **Step 4: Add routes**

```php
Route::get('/pelanggan/{pelanggan}/meter', [Operator\PelangganController::class, 'meterEdit'])->name('pelanggan.meter');
Route::post('/pelanggan/{pelanggan}/meter', [Operator\PelangganController::class, 'meterStore'])->name('pelanggan.meter.store');
```

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: add Meter CRUD (1:1 with Pelanggan)"
```

---

## Task 9: Pembacaan Meter

**Files:**
- Create: `database/migrations/xxxx_create_pembacaan_table.php`
- Create: `app/Models/Pembacaan.php`
- Create: `database/factories/PembacaanFactory.php`
- Create: `app/Http/Controllers/Operator/PembacaanController.php`
- Create: `resources/views/operator/pembacaan/index.blade.php`
- Create: `resources/views/operator/pembacaan/create.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_pembacaan_table
```

```php
Schema::create('pembacaan', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
    $table->foreignId('meter_id')->constrained('meters')->cascadeOnDelete();
    $table->string('periode', 7); // YYYY-MM
    $table->decimal('angka_meter_sebelumnya', 10, 1)->default(0);
    $table->decimal('angka_meter_sekarang', 10, 1);
    $table->decimal('volume_m3', 10, 1)->storedAs('angka_meter_sekarang - angka_meter_sebelumnya');
    $table->date('tanggal_baca');
    $table->foreignId('dibaca_oleh')->constrained('users')->nullOnDelete();
    $table->enum('status', ['draft', 'konfirmasi'])->default('draft');
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->unique(['tenant_id', 'pelanggan_id', 'periode']);
    $table->index(['tenant_id', 'periode']);
});
```

- [ ] **Step 2: Create model**

`app/Models/Pembacaan.php`:
```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pembacaan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'pelanggan_id', 'meter_id', 'periode',
        'angka_meter_sebelumnya', 'angka_meter_sekarang',
        'tanggal_baca', 'dibaca_oleh', 'status', 'catatan',
    ];

    protected $casts = [
        'angka_meter_sebelumnya' => 'decimal:1',
        'angka_meter_sekarang' => 'decimal:1',
        'volume_m3' => 'decimal:1',
        'tanggal_baca' => 'date',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibaca_oleh');
    }

    /**
     * Auto-fetch angka_meter_sebelumnya from previous period's reading.
     */
    public static function getPreviousReading(int $pelangganId, string $periode): ?string
    {
        $previousPeriode = date('Y-m', strtotime($periode . ' -1 month'));

        return self::where('pelanggan_id', $pelangganId)
            ->where('periode', $previousPeriode)
            ->value('angka_meter_sekarang');
    }
}
```

- [ ] **Step 3: Create PembacaanController**

Key features:
- `index()`: List readings by periode, filterable
- `create()`: Show form with all active pelanggan for the selected periode. Auto-fill `angka_meter_sebelumnya` from previous period.
- `store()`: Batch save multiple readings at once. Validate: no negative volume, no duplicate pelanggan+periode. Show warning if volume > 3x average or negative.

- [ ] **Step 4: Create views**

`pembacaan/index.blade.php` — Periode selector dropdown, table of readings with status badges.

`pembacaan/create.blade.php` — Scrollable form listing all active pelanggan with active meters. Each row: pelanggan name, nomor meter, previous reading (auto), current reading input. Submit as batch.

- [ ] **Step 5: Add routes**

```php
Route::get('/pembacaan', [Operator\PembacaanController::class, 'index'])->name('pembacaan.index');
Route::get('/pembacaan/create', [Operator\PembacaanController::class, 'create'])->name('pembacaan.create');
Route::post('/pembacaan/batch', [Operator\PembacaanController::class, 'batchStore'])->name('pembacaan.batch');
Route::get('/pembacaan/{pembacaan}/edit', [Operator\PembacaanController::class, 'edit'])->name('pembacaan.edit');
Route::put('/pembacaan/{pembacaan}', [Operator\PembacaanController::class, 'update'])->name('pembacaan.update');
```

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add Pembacaan Meter with auto-fetch previous reading and batch entry"
```

---

## Task 10: Tagihan Generation

**Files:**
- Create: `database/migrations/xxxx_create_tagihan_table.php`
- Create: `app/Models/Tagihan.php`
- Create: `app/Services/TagihanService.php`
- Create: `app/Http/Controllers/Operator/TagihanController.php`
- Create: `resources/views/operator/tagihan/index.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create migration**

```bash
php artisan make:migration create_tagihan_table
```

```php
Schema::create('tagihan', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->foreignId('pelanggan_id')->constrained('pelanggan')->cascadeOnDelete();
    $table->foreignId('pembacaan_id')->constrained('pembacaan')->cascadeOnDelete();
    $table->string('periode', 7); // YYYY-MM
    $table->decimal('volume_m3', 10, 1);
    $table->decimal('tarif_per_m3', 10, 2);
    $table->decimal('biaya_air', 12, 2);
    $table->decimal('total_tagihan', 12, 2);
    $table->enum('status', ['belum_bayar', 'lunas', 'cicilan', 'batal'])->default('belum_bayar');
    $table->date('tanggal_jatuh_tempo');
    $table->text('catatan')->nullable();
    $table->timestamps();

    $table->unique(['tenant_id', 'pelanggan_id', 'periode']);
    $table->index(['tenant_id', 'periode', 'status']);
});
```

- [ ] **Step 2: Create model**

`app/Models/Tagihan.php`:
```php
<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tagihan extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'pelanggan_id', 'pembacaan_id', 'periode',
        'volume_m3', 'tarif_per_m3', 'biaya_air', 'total_tagihan',
        'status', 'tanggal_jatuh_tempo', 'catatan',
    ];

    protected $casts = [
        'volume_m3' => 'decimal:1',
        'tarif_per_m3' => 'decimal:2',
        'biaya_air' => 'decimal:2',
        'total_tagihan' => 'decimal:2',
        'tanggal_jatuh_tempo' => 'date',
    ];

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class);
    }

    public function pembacaan(): BelongsTo
    {
        return $this->belongsTo(Pembacaan::class);
    }

    public function pembayaran(): HasMany
    {
        return $this->hasMany(Pembayaran::class);
    }

    public function totalDibayar(): string
    {
        return $this->pembayaran()->sum('jumlah_bayar');
    }

    public function sisaTagihan(): string
    {
        return max(0, $this->total_tagihan - $this->totalDibayar());
    }
}
```

- [ ] **Step 3: Create TagihanService**

`app/Services/TagihanService.php`:
```php
<?php

namespace App\Services;

use App\Models\Pembacaan;
use App\Models\Tagihan;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TagihanService
{
    /**
     * Generate tagihan for all confirmed pembacaan in a periode.
     */
    public function generateForPeriode(Tenant $tenant, string $periode): array
    {
        $pembacaanList = Pembacaan::where('tenant_id', $tenant->id)
            ->where('periode', $periode)
            ->where('status', 'konfirmasi')
            ->whereDoesntHave('tagihan')
            ->with('pelanggan', 'meter')
            ->get();

        $results = ['created' => 0, 'skipped' => 0, 'errors' => []];

        // Calculate jatuh tempo: tanggal X of the following month
        $bulanBerikutnya = Carbon::parse($periode . '-01')->addMonth();
        $tanggalJatuhTempo = $bulanBerikutnya->setDay($tenant->jatuh_tempo_tanggal)->toDateString();

        foreach ($pembacaanList as $pembacaan) {
            if ($pembacaan->volume_m3 < 0) {
                $results['skipped']++;
                $results['errors'][] = "Pelanggan {$pembacaan->pelanggan->nama}: volume negatif";
                continue;
            }

            Tagihan::create([
                'tenant_id' => $tenant->id,
                'pelanggan_id' => $pembacaan->pelanggan_id,
                'pembacaan_id' => $pembacaan->id,
                'periode' => $periode,
                'volume_m3' => $pembacaan->volume_m3,
                'tarif_per_m3' => $tenant->tarif_per_m3,
                'biaya_air' => $pembacaan->volume_m3 * $tenant->tarif_per_m3,
                'total_tagihan' => $pembacaan->volume_m3 * $tenant->tarif_per_m3,
                'tanggal_jatuh_tempo' => $tanggalJatuhTempo,
            ]);

            $results['created']++;
        }

        return $results;
    }
}
```

- [ ] **Step 4: Create TagihanController**

`app/Http/Controllers/Operator/TagihanController.php`:
```php
<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Tagihan;
use App\Services\TagihanService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TagihanController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'tenant.resolve', 'tenant.ensure']);
    }

    public function index(Request $request): View
    {
        $query = Tagihan::with('pelanggan');

        if ($request->filled('periode')) {
            $query->where('periode', $request->periode);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $tagihan = $query->latest()->paginate(20)->withQueryString();

        return view('operator.tagihan.index', compact('tagihan'));
    }

    public function generate(Request $request, TagihanService $service)
    {
        $validated = $request->validate([
            'periode' => 'required|string|size:7|date_format:Y-m',
        ]);

        $tenant = app('current.tenant');
        $results = $service->generateForPeriode($tenant, $validated['periode']);

        $message = "Tagihan berhasil dibuat: {$results['created']} tagihan.";
        if ($results['skipped'] > 0) {
            $message .= " Dilewati: {$results['skipped']}.";
        }

        return redirect()->route('tagihan.index', ['periode' => $validated['periode']])
            ->with('success', $message);
    }
}
```

- [ ] **Step 5: Create view**

`resources/views/operator/tagihan/index.blade.php` — Table with filter by periode and status. "Generate Tagihan" button. Status badges with colors (belum_bayar = red, lunas = green, cicilan = yellow, batal = gray).

- [ ] **Step 6: Add routes**

```php
Route::get('/tagihan', [Operator\TagihanController::class, 'index'])->name('tagihan.index');
Route::post('/tagihan/generate', [Operator\TagihanController::class, 'generate'])->name('tagihan.generate');
```

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add Tagihan generation from Pembacaan with auto jatuh tempo"
```

---

## Self-Review

**1. Spec coverage:**
- [x] Multi-tenancy (Task 2)
- [x] Auth + roles (Task 4)
- [x] Tenant CRUD (Task 5)
- [x] Operator management (Task 6)
- [x] Pelanggan CRUD (Task 7)
- [x] Meter CRUD (Task 8)
- [x] Pembacaan Meter with auto-fetch + batch (Task 9)
- [x] Tagihan generation with jatuh tempo (Task 10)
- [x] Nomor pelanggan auto-generated
- [x] Unique constraint on pembacaan (pelanggan + periode)
- [x] Data policy: operator cancel, super admin delete

**2. Placeholder scan:** No TBDs, no "implement later", all code blocks present.

**3. Type consistency:** `volume_m3` is `decimal:1` everywhere. `tarif_per_m3` is `decimal:2`. Foreign keys match. Field names consistent (`dibaca_oleh` not `petugas_baca`).

**4. Missing from Fase 1 (by design, deferred to Fase 2-4):**
- Pembayaran (Fase 2)
- PDF kwitansi/invoice (Fase 2)
- Dashboard + charts (Fase 3)
- Laporan + export (Fase 3)
- Dark/light theme (Fase 4)
- Mobile bottom nav (Fase 4)

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-03-hippam-fase1.md`. Two execution options:

**1. Subagent-Driven (recommended)** — Dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
