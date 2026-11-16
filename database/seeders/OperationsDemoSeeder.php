<?php

namespace Database\Seeders;

use App\Enums\CompanyRole;
use App\Enums\VisitStatus;
use App\Models\Area;
use App\Models\AuditLog;
use App\Models\Collection;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LocationPoint;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Visit;
use App\Models\Warehouse;
use App\Services\LotLedger;
use App\Support\CompanyContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationsDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! config('fieldflow.demo')) {
            throw new \RuntimeException('Demo seeding is local only.');
        }
        $context = app(CompanyContext::class);
        $credentials = [];
        try {
            DB::transaction(function () use ($context, &$credentials) {
                $password = '123456';
                $makeUser = function ($name, $username) use ($password, &$credentials) {
                    $user = User::firstOrCreate(['username' => $username], ['name' => $name, 'password' => $password]);
                    if ($user->wasRecentlyCreated) {
                        $credentials[] = "$username | $password";
                    }

                    return $user;
                };
                $manager = $makeUser('عمر • مدير العمليات', 'amr');
                foreach ([['naba', 'شركة رواد الورقيات', 'water'], ['madar', 'مدار للمواد الغذائية', 'food']] as $companyData) {
                    $company = Company::firstOrCreate(['slug' => $companyData[0]], ['name' => $companyData[1], 'industry' => $companyData[2]]);
                    $company->users()->syncWithoutDetaching([$manager->id => ['role' => 'manager', 'is_active' => true]]);
                    $context->set($company, CompanyRole::Manager);
                    if (Trip::exists()) {
                        continue;
                    }
                    $suffix = $company->slug === 'madar' ? '2' : '';
                    $accountant = $makeUser('سارة • الحسابات', 'sara'.$suffix);
                    $company->users()->syncWithoutDetaching([$accountant->id => ['role' => 'accountant', 'is_active' => true]]);
                    $areas = [];
                    foreach (['الياسمين', 'النرجس', 'العقيق', 'الملقا'] as $name) {
                        $areas[] = Area::create(['name' => $name, 'city' => 'الرياض']);
                    }
                    $customers = [];
                    $names = ['أسواق السحاب', 'مركز الواحة', 'تموينات الروضة', 'متجر الريف', 'أسواق الدانة', 'ركن الحي', 'متجر النخيل', 'أسواق المروج', 'تموينات الشمال', 'مطعم اللقمة', 'مركز الصفاء', 'متجر الأفق', 'أسواق التلال', 'مطعم المذاق', 'فندق الساحة', 'متجر المدى', 'تموينات النور', 'مقهى الظل', 'مركز الروابي', 'متجر السهول', 'أسواق الحديقة', 'متجر الندى', 'تموينات وادي', 'مطعم الدار'];
                    foreach ($names as $j => $name) {
                        $customers[] = Customer::create(['code' => 'C-'.str_pad((string) ($j + 1), 4, '0', STR_PAD_LEFT), 'name' => $name.' (تجريبي)', 'area_id' => $areas[$j % 4]->id, 'address' => 'الرياض، '.$areas[$j % 4]->name.' • عنوان للعرض', 'latitude' => 24.77 + ($j % 6) * .008, 'longitude' => 46.61 + intdiv($j, 6) * .014, 'geofence_radius_m' => 150]);
                    }
                    $main = Warehouse::create(['name' => 'المستودع الرئيسي • الرياض', 'type' => 'warehouse']);
                    $products = [];
                    $catalog = $company->industry === 'water' ? [['W-12', 'مياه 330 مل • كرتون', 'كرتون', 1800], ['W-24', 'مياه 600 مل • كرتون', 'كرتون', 2400], ['W-05', 'عبوة مياه 5 لتر', 'عبوة', 650], ['W-19', 'عبوة مياه 19 لتر', 'عبوة', 1400]] : [['F-01', 'أرز بسمتي 5 كجم', 'كيس', 4200], ['F-02', 'زيت نباتي 1.5 لتر', 'عبوة', 1600], ['F-03', 'سكر 1 كجم', 'كيس', 500], ['F-04', 'دقيق 2 كجم', 'كيس', 850]];
                    foreach ($catalog as $p) {
                        $products[] = Product::create(['sku' => $p[0], 'name' => $p[1], 'unit' => $p[2], 'price_minor' => $p[3], 'reorder_level' => 30]);
                    }
                    foreach (['خالد العتيبي', 'عبدالله الحربي', 'محمد القحطاني', 'أحمد العمري', 'ناصر الدوسري', 'فهد الزهراني'] as $i => $repName) {
                        $rep = $makeUser($repName, ['khalid', 'abdullah', 'mohammed', 'ahmed', 'nasser', 'fahad'][$i].$suffix);
                        $company->users()->syncWithoutDetaching([$rep->id => ['role' => 'representative', 'is_active' => true]]);
                        $vehicle = Vehicle::create(['name' => 'فان التوزيع '.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), 'plate' => 'DEMO-'.($i + 110), 'capacity_units' => 600]);
                        $mobile = Warehouse::create(['name' => 'عهدة '.$repName, 'type' => 'vehicle', 'vehicle_id' => $vehicle->id]);
                        $trip = Trip::create(['code' => 'TR-DEMO-'.($i + 1), 'representative_id' => $rep->id, 'vehicle_id' => $vehicle->id, 'warehouse_id' => $mobile->id, 'scheduled_for' => today('Asia/Riyadh')->toDateString()]);
                        $trip->status = $i < 3 ? 'active' : ($i === 3 ? 'planned' : 'closed');
                        if ($trip->status !== 'planned') {
                            $trip->started_at = now()->subHours(2);
                        }
                        if ($trip->status === 'active') {
                            $trip->active_representative_id = $rep->id;
                            $trip->active_vehicle_id = $vehicle->id;
                        }
                        if ($trip->status === 'closed') {
                            $trip->closed_at = now()->subMinutes(40);
                        } $trip->save();
                        foreach (array_slice($customers, $i * 4, 4) as $j => $customer) {
                            $visit = Visit::create(['customer_id' => $customer->id, 'representative_id' => $rep->id, 'trip_id' => $trip->id, 'sequence' => $j + 1, 'scheduled_for' => $trip->scheduled_for, 'window_start' => '09:00', 'window_end' => '17:00']);
                            if ($i >= 4 || ($i < 3 && $j < 2)) {
                                $visit->status = $i === 5 && $j === 3 ? VisitStatus::Failed : VisitStatus::Completed;
                                $visit->checked_in_at = now()->subMinutes(100 - $j * 15);
                                $visit->finished_at = now()->subMinutes(90 - $j * 15);
                                $visit->checkin_latitude = $customer->latitude;
                                $visit->checkin_longitude = $customer->longitude;
                                $visit->accuracy_m = 12;
                                $visit->distance_m = 8;
                                $visit->location_captured_at = $visit->checked_in_at;
                                $visit->location_verified = true;
                                $visit->outcome_note = $visit->status === VisitStatus::Failed ? 'العميل مغلق • مثال تجريبي' : 'زيارة وطلب تجريبي';
                                $visit->save();
                            }
                        }
                        if ($i !== 3) {
                            for ($k = 0; $k < 18; $k++) {
                                $age = ($i === 1 ? 22 : ($i >= 4 ? 40 : 0)) + (17 - $k) * 3;
                                LocationPoint::create(['trip_id' => $trip->id, 'user_id' => $rep->id, 'event_id' => Str::uuid(), 'latitude' => 24.75 + $i * .009 + $k * .0012, 'longitude' => 46.61 + $i * .008 + $k * .0017, 'accuracy_m' => 10 + $k % 5, 'speed_kmh' => 25, 'captured_at' => now()->subMinutes($age)->subSeconds(25), 'received_at' => now()->subMinutes($age)->subSeconds(20), 'source' => 'demo', 'quality' => 'accepted']);
                            }
                        }
                        foreach ($products as $pi => $product) {
                            $initial = ($i === 2 && $pi === 2) ? 35 : 120;
                            StockBalance::create(['warehouse_id' => $mobile->id, 'product_id' => $product->id, 'quantity' => $initial]);
                            StockMovement::create(['warehouse_id' => $mobile->id, 'product_id' => $product->id, 'quantity' => $initial, 'reason' => 'demo_opening', 'reference' => 'DEMO-OPEN', 'actor_id' => $manager->id, 'created_at' => now()->subDays(8)]);
                        }
                        for ($day = 0; $day < 7; $day++) {
                            $customer = $customers[$i * 4 + ($day % 4)];
                            $product = $products[$day % 4];
                            $qty = 8 + $i + $day;
                            $order = Order::create(['code' => 'SO-DEMO-'.$i.'-'.$day, 'customer_id' => $customer->id, 'representative_id' => $rep->id, 'warehouse_id' => $mobile->id, 'trip_id' => $day === 0 ? $trip->id : null]);
                            $order->total_minor = $qty * $product->price_minor;
                            $order->status = 'fulfilled';
                            $order->paid_minor = $day % 3 === 0 ? intdiv($order->total_minor, 2) : $order->total_minor;
                            $order->created_at = now()->subDays($day)->subMinutes($i * 10);
                            $order->save();
                            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $qty, 'unit_price_minor' => $product->price_minor, 'total_minor' => $order->total_minor]);
                            StockBalance::where('warehouse_id', $mobile->id)->where('product_id', $product->id)->decrement('quantity', $qty);
                            StockMovement::create(['warehouse_id' => $mobile->id, 'product_id' => $product->id, 'quantity' => -$qty, 'reason' => 'sale', 'reference' => $order->code, 'actor_id' => $rep->id, 'created_at' => $order->created_at]);
                            Collection::create(['order_id' => $order->id, 'actor_id' => $rep->id, 'receipt' => 'RC-DEMO-'.$i.'-'.$day, 'amount_minor' => $order->paid_minor, 'method' => ['cash', 'bank', 'card'][$day % 3], 'collected_at' => $order->created_at]);
                        }
                    }
                    foreach ($products as $pi => $product) {
                        $quantity = 2000 + $pi * 400;
                        StockBalance::create(['warehouse_id' => $main->id, 'product_id' => $product->id, 'quantity' => $quantity]);
                        StockMovement::create(['warehouse_id' => $main->id, 'product_id' => $product->id, 'quantity' => $quantity, 'reason' => 'demo_opening', 'reference' => 'DEMO-OPEN', 'actor_id' => $manager->id, 'created_at' => now()->subDays(8)]);
                    }
                    foreach (StockBalance::with('warehouse', 'product')->where('quantity', '>', 0)->get() as $opening) {
                        app(LotLedger::class)->opening($opening->warehouse, $opening->product);
                    }
                    AuditLog::create(['actor_id' => $manager->id, 'action' => 'demo.dataset_created', 'subject_type' => 'companies', 'subject_id' => $company->id, 'metadata' => ['synthetic' => true], 'created_at' => now()]);
                }
            });
        } finally {
            $context->clear();
        }
        if ($credentials && ! app()->environment('testing')) {
            if (! is_dir(base_path('.runtime'))) {
                mkdir(base_path('.runtime'), 0777, true);
            }
            file_put_contents(base_path('.runtime/demo-access.txt'), implode(PHP_EOL, $credentials).PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        $this->command?->info('Synthetic dataset ready; credentials in .runtime/demo-access.txt. Existing data retained.');
    }
}
