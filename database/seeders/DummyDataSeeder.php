<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\AttendanceLog;
use App\Models\AttendanceRequest;
use App\Models\Device;
use App\Models\Holiday;
use App\Models\LatePenaltyTier;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Location;
use App\Models\Notification;
use App\Models\SalaryComponent;
use App\Models\Shift;
use App\Models\User;
use App\Models\UserSalaryComponent;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $period = $this->buildPeriod();

            $locations = $this->seedLocations();
            $users = $this->seedUsers($locations);
            $shifts = Shift::query()->get()->keyBy('code');

            $this->seedSchedules($users, $shifts, $period['month_start']);

            $salaryComponents = $this->seedSalaryComponents();
            $this->seedUserCompensation($users, $salaryComponents);

            $deviceFingerprints = $this->seedDevices($users, $period['today']);
            $holidays = $this->seedHolidays($period);
            $leaveContext = $this->seedLeaveData($users, $period, $holidays);
            $manualCheckIns = $this->seedAttendanceRequests($users, $locations, $period, $holidays, $deviceFingerprints);

            $this->seedAttendances(
                $users,
                $locations,
                $shifts,
                $period,
                $holidays,
                $leaveContext['approved_dates'],
                $manualCheckIns,
                $deviceFingerprints
            );

            $this->seedNotifications($users, $period);
        });

        $this->command?->info('Dummy data refreshed successfully.');
        $this->command?->info('Demo logins:');
        $this->command?->info('  Super Admin: admin@example.com / password');
        $this->command?->info('  Admin: ops.admin@example.com / password');
        $this->command?->info('  Supervisor: supervisor@example.com / password');
        $this->command?->info('  Employee: john@example.com / password');
        $this->command?->info('  Display Screen: display@example.com / password');
    }

    protected function buildPeriod(): array
    {
        $today = now()->startOfDay();

        return [
            'today' => $today,
            'month_start' => $today->copy()->startOfMonth(),
            'month_end' => $today->copy()->endOfMonth(),
        ];
    }

    protected function seedLocations(): Collection
    {
        $definitions = [
            'hq' => [
                'code' => 'OFFICE-JKT-01',
                'name' => 'Jakarta HQ',
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'allowed_radius_m' => 150,
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
            'bandung' => [
                'code' => 'OFFICE-BDG-01',
                'name' => 'Bandung Branch',
                'latitude' => -6.91474400,
                'longitude' => 107.60981000,
                'allowed_radius_m' => 120,
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
            'warehouse' => [
                'code' => 'WAREHOUSE-BKS-01',
                'name' => 'Bekasi Warehouse',
                'latitude' => -6.24158600,
                'longitude' => 106.99241600,
                'allowed_radius_m' => 180,
                'timezone' => 'Asia/Jakarta',
                'is_active' => true,
            ],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $key) {
            $location = Location::updateOrCreate(
                ['code' => $definition['code']],
                $definition
            );

            return [$key => $location];
        });
    }

    protected function seedUsers(Collection $locations): Collection
    {
        $definitions = [
            'admin' => [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'employee_id' => 'ADM-001',
                'phone' => '081200000001',
                'department' => 'Management',
                'position' => 'System Administrator',
                'location' => 'hq',
                'status' => 'active',
                'base_salary' => 18000000,
                'role' => 'super_admin',
            ],
            'ops_admin' => [
                'name' => 'Rina Prameswari',
                'email' => 'ops.admin@example.com',
                'employee_id' => 'ADM-002',
                'phone' => '081200000002',
                'department' => 'Operations',
                'position' => 'Operations Admin',
                'location' => 'hq',
                'status' => 'active',
                'base_salary' => 12500000,
                'role' => 'admin',
            ],
            'supervisor' => [
                'name' => 'Maya Pratama',
                'email' => 'supervisor@example.com',
                'employee_id' => 'SPV-001',
                'phone' => '081200000003',
                'department' => 'Engineering',
                'position' => 'Engineering Lead',
                'location' => 'hq',
                'status' => 'active',
                'base_salary' => 13500000,
                'role' => 'supervisor',
            ],
            'display' => [
                'name' => 'Barcode Display',
                'email' => 'display@example.com',
                'employee_id' => 'DISPLAY-001',
                'phone' => null,
                'department' => 'Operations',
                'position' => 'Kiosk Display',
                'location' => 'hq',
                'status' => 'active',
                'base_salary' => 0,
                'role' => 'display_screen',
            ],
            'john' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'employee_id' => 'EMP-1001',
                'phone' => '081200000101',
                'department' => 'Engineering',
                'position' => 'Software Developer',
                'location' => 'hq',
                'status' => 'active',
                'base_salary' => 9500000,
                'role' => 'employee',
            ],
            'sarah' => [
                'name' => 'Sarah Wijaya',
                'email' => 'sarah@example.com',
                'employee_id' => 'EMP-1002',
                'phone' => '081200000102',
                'department' => 'Engineering',
                'position' => 'QA Engineer',
                'location' => 'hq',
                'status' => 'active',
                'base_salary' => 8500000,
                'role' => 'employee',
            ],
            'budi' => [
                'name' => 'Budi Santoso',
                'email' => 'budi@example.com',
                'employee_id' => 'EMP-1003',
                'phone' => '081200000103',
                'department' => 'Operations',
                'position' => 'Warehouse Officer',
                'location' => 'warehouse',
                'status' => 'active',
                'base_salary' => 6200000,
                'role' => 'employee',
            ],
            'linda' => [
                'name' => 'Linda Hartono',
                'email' => 'linda@example.com',
                'employee_id' => 'EMP-1004',
                'phone' => '081200000104',
                'department' => 'HR',
                'position' => 'People Operations',
                'location' => 'hq',
                'status' => 'active',
                'base_salary' => 7800000,
                'role' => 'employee',
            ],
            'andre' => [
                'name' => 'Andre Setiawan',
                'email' => 'andre@example.com',
                'employee_id' => 'EMP-1005',
                'phone' => '081200000105',
                'department' => 'Finance',
                'position' => 'Payroll Specialist',
                'location' => 'hq',
                'status' => 'active',
                'base_salary' => 8000000,
                'role' => 'employee',
            ],
            'nisa' => [
                'name' => 'Nisa Kurnia',
                'email' => 'nisa@example.com',
                'employee_id' => 'EMP-1006',
                'phone' => '081200000106',
                'department' => 'Operations',
                'position' => 'Customer Success',
                'location' => 'bandung',
                'status' => 'active',
                'base_salary' => 6800000,
                'role' => 'employee',
            ],
            'rio' => [
                'name' => 'Rio Saputra',
                'email' => 'rio@example.com',
                'employee_id' => 'EMP-1007',
                'phone' => '081200000107',
                'department' => 'Engineering',
                'position' => 'Mobile Developer',
                'location' => 'bandung',
                'status' => 'active',
                'base_salary' => 9000000,
                'role' => 'employee',
            ],
            'eka' => [
                'name' => 'Eka Lestari',
                'email' => 'eka@example.com',
                'employee_id' => 'EMP-1008',
                'phone' => '081200000108',
                'department' => 'Operations',
                'position' => 'Field Staff',
                'location' => 'warehouse',
                'status' => 'active',
                'base_salary' => 5600000,
                'role' => 'employee',
            ],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $key) use ($locations) {
            $user = User::updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => 'password',
                    'employee_id' => $definition['employee_id'],
                    'phone' => $definition['phone'],
                    'department' => $definition['department'],
                    'position' => $definition['position'],
                    'default_location_id' => $locations->get($definition['location'])->id,
                    'status' => $definition['status'],
                    'base_salary' => $definition['base_salary'],
                ]
            );

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$definition['role']]);

            return [$key => $user->fresh(['roles', 'defaultLocation'])];
        });
    }

    protected function seedSchedules(Collection $users, Collection $shifts, Carbon $monthStart): void
    {
        $assignments = [
            'admin' => 'SHIFT-MORNING',
            'ops_admin' => 'SHIFT-MORNING',
            'supervisor' => 'SHIFT-FLEXI',
            'john' => 'SHIFT-FLEXI',
            'sarah' => 'SHIFT-FLEXI',
            'budi' => 'SHIFT-MORNING',
            'linda' => 'SHIFT-MORNING',
            'andre' => 'SHIFT-MORNING',
            'nisa' => 'SHIFT-AFTERNOON',
            'rio' => 'SHIFT-FLEXI',
            'eka' => 'SHIFT-MORNING',
        ];

        foreach ($assignments as $userKey => $shiftCode) {
            $this->saveModel(
                UserSchedule::class,
                [
                    'user_id' => $users->get($userKey)->id,
                    'shift_id' => $shifts->get($shiftCode)->id,
                    'start_date' => $monthStart->toDateString(),
                ],
                [
                    'user_id' => $users->get($userKey)->id,
                    'shift_id' => $shifts->get($shiftCode)->id,
                    'start_date' => $monthStart->toDateString(),
                    'end_date' => null,
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function seedSalaryComponents(): Collection
    {
        $definitions = [
            'transport' => [
                'name' => 'Transport Allowance',
                'type' => 'FIXED',
                'description' => 'Monthly commuting support.',
                'is_active' => true,
            ],
            'meal' => [
                'name' => 'Meal Allowance',
                'type' => 'FIXED',
                'description' => 'Daily meal support rolled into payroll.',
                'is_active' => true,
            ],
            'attendance_bonus' => [
                'name' => 'Attendance Incentive',
                'type' => 'VARIABLE',
                'description' => 'Attendance-driven monthly incentive.',
                'is_active' => true,
            ],
            'performance_bonus' => [
                'name' => 'Performance Bonus',
                'type' => 'VARIABLE',
                'description' => 'Variable bonus based on output.',
                'is_active' => true,
            ],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $key) {
            $component = SalaryComponent::updateOrCreate(
                ['name' => $definition['name']],
                $definition
            );

            return [$key => $component];
        });
    }

    protected function seedUserCompensation(Collection $users, Collection $salaryComponents): void
    {
        $assignments = [
            'admin' => [
                'transport' => 1500000,
                'meal' => 1200000,
                'attendance_bonus' => 1000000,
                'performance_bonus' => 2500000,
            ],
            'ops_admin' => [
                'transport' => 900000,
                'meal' => 700000,
                'attendance_bonus' => 600000,
                'performance_bonus' => 1000000,
            ],
            'supervisor' => [
                'transport' => 1000000,
                'meal' => 750000,
                'attendance_bonus' => 650000,
                'performance_bonus' => 1750000,
            ],
            'john' => [
                'transport' => 850000,
                'meal' => 650000,
                'attendance_bonus' => 500000,
                'performance_bonus' => 1200000,
            ],
            'sarah' => [
                'transport' => 750000,
                'meal' => 600000,
                'attendance_bonus' => 450000,
                'performance_bonus' => 900000,
            ],
            'budi' => [
                'transport' => 600000,
                'meal' => 500000,
                'attendance_bonus' => 350000,
            ],
            'linda' => [
                'transport' => 650000,
                'meal' => 550000,
                'attendance_bonus' => 400000,
                'performance_bonus' => 650000,
            ],
            'andre' => [
                'transport' => 650000,
                'meal' => 550000,
                'attendance_bonus' => 400000,
                'performance_bonus' => 800000,
            ],
            'nisa' => [
                'transport' => 500000,
                'meal' => 450000,
                'attendance_bonus' => 325000,
            ],
            'rio' => [
                'transport' => 700000,
                'meal' => 575000,
                'attendance_bonus' => 425000,
                'performance_bonus' => 950000,
            ],
            'eka' => [
                'transport' => 450000,
                'meal' => 400000,
                'attendance_bonus' => 250000,
            ],
            'display' => [],
        ];

        foreach ($assignments as $userKey => $components) {
            $user = $users->get($userKey);
            $componentIds = [];

            foreach ($components as $componentKey => $amount) {
                $component = $salaryComponents->get($componentKey);
                $componentIds[] = $component->id;

                UserSalaryComponent::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'salary_component_id' => $component->id,
                    ],
                    ['amount' => $amount]
                );
            }

            $query = UserSalaryComponent::query()->where('user_id', $user->id);

            if (empty($componentIds)) {
                $query->delete();
                continue;
            }

            $query->whereNotIn('salary_component_id', $componentIds)->delete();
        }
    }

    protected function seedDevices(Collection $users, Carbon $today): Collection
    {
        $definitions = [
            [
                'user' => 'admin',
                'fingerprint' => 'seed-admin-macbook',
                'device_name' => 'Admin MacBook Pro',
                'device_info' => ['platform' => 'macOS', 'browser' => 'Chrome', 'model' => 'MacBook Pro 14'],
                'is_approved' => true,
                'registered_at' => $today->copy()->subDays(20)->setTime(9, 15),
                'last_used_at' => $today->copy()->subHours(1),
                'primary' => true,
            ],
            [
                'user' => 'ops_admin',
                'fingerprint' => 'seed-ops-admin-laptop',
                'device_name' => 'Ops Admin ThinkPad',
                'device_info' => ['platform' => 'Windows', 'browser' => 'Edge', 'model' => 'ThinkPad T14'],
                'is_approved' => true,
                'registered_at' => $today->copy()->subDays(12)->setTime(8, 45),
                'last_used_at' => $today->copy()->subHours(2),
                'primary' => true,
            ],
            [
                'user' => 'supervisor',
                'fingerprint' => 'seed-supervisor-iphone',
                'device_name' => 'Maya iPhone 15',
                'device_info' => ['platform' => 'iOS', 'browser' => 'Safari', 'model' => 'iPhone 15'],
                'is_approved' => true,
                'registered_at' => $today->copy()->subDays(15)->setTime(10, 0),
                'last_used_at' => $today->copy()->subMinutes(40),
                'primary' => true,
            ],
            [
                'user' => 'john',
                'fingerprint' => 'seed-john-iphone',
                'device_name' => 'John iPhone 14',
                'device_info' => ['platform' => 'iOS', 'browser' => 'Safari', 'model' => 'iPhone 14'],
                'is_approved' => true,
                'registered_at' => $today->copy()->subDays(11)->setTime(8, 20),
                'last_used_at' => $today->copy()->subMinutes(35),
                'primary' => true,
            ],
            [
                'user' => 'john',
                'fingerprint' => 'seed-john-tablet',
                'device_name' => 'John Android Tablet',
                'device_info' => ['platform' => 'Android', 'browser' => 'Chrome', 'model' => 'Galaxy Tab'],
                'is_approved' => false,
                'registered_at' => $today->copy()->subDays(1)->setTime(19, 10),
                'last_used_at' => null,
                'primary' => false,
            ],
            [
                'user' => 'sarah',
                'fingerprint' => 'seed-sarah-laptop',
                'device_name' => 'Sarah Zenbook',
                'device_info' => ['platform' => 'Windows', 'browser' => 'Chrome', 'model' => 'ASUS Zenbook'],
                'is_approved' => true,
                'registered_at' => $today->copy()->subDays(18)->setTime(9, 30),
                'last_used_at' => $today->copy()->subDays(1)->setTime(17, 20),
                'primary' => true,
            ],
            [
                'user' => 'budi',
                'fingerprint' => 'seed-budi-android',
                'device_name' => 'Budi Android',
                'device_info' => ['platform' => 'Android', 'browser' => 'Chrome', 'model' => 'Redmi Note'],
                'is_approved' => false,
                'registered_at' => $today->copy()->subDays(2)->setTime(7, 15),
                'last_used_at' => null,
                'primary' => true,
            ],
            [
                'user' => 'nisa',
                'fingerprint' => 'seed-nisa-iphone',
                'device_name' => 'Nisa iPhone 13',
                'device_info' => ['platform' => 'iOS', 'browser' => 'Safari', 'model' => 'iPhone 13'],
                'is_approved' => true,
                'registered_at' => $today->copy()->subDays(9)->setTime(12, 5),
                'last_used_at' => $today->copy()->subDays(1)->setTime(22, 10),
                'primary' => true,
            ],
            [
                'user' => 'rio',
                'fingerprint' => 'seed-rio-pixel',
                'device_name' => 'Rio Pixel 8',
                'device_info' => ['platform' => 'Android', 'browser' => 'Chrome', 'model' => 'Pixel 8'],
                'is_approved' => true,
                'registered_at' => $today->copy()->subDays(7)->setTime(8, 0),
                'last_used_at' => $today->copy()->subDays(1)->setTime(18, 15),
                'primary' => true,
            ],
            [
                'user' => 'eka',
                'fingerprint' => 'seed-eka-android',
                'device_name' => 'Eka Android',
                'device_info' => ['platform' => 'Android', 'browser' => 'Chrome', 'model' => 'Galaxy A55'],
                'is_approved' => true,
                'registered_at' => $today->copy()->subDays(6)->setTime(6, 50),
                'last_used_at' => $today->copy()->subDays(2)->setTime(17, 5),
                'primary' => true,
            ],
        ];

        $primaryFingerprints = collect();

        foreach ($definitions as $definition) {
            $user = $users->get($definition['user']);

            Device::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'device_fingerprint' => $definition['fingerprint'],
                ],
                [
                    'device_name' => $definition['device_name'],
                    'device_info' => $definition['device_info'],
                    'is_approved' => $definition['is_approved'],
                    'registered_at' => $definition['registered_at'],
                    'last_used_at' => $definition['last_used_at'],
                ]
            );

            if ($definition['primary']) {
                $primaryFingerprints->put($user->id, $definition['fingerprint']);
            }
        }

        return $primaryFingerprints;
    }

    protected function seedHolidays(array $period): Collection
    {
        $pastBusinessDays = $this->businessDaysInRange(
            $period['month_start'],
            $period['today']->copy()->subDay(),
            []
        );

        $futureBusinessDays = $this->nextBusinessDays(
            $period['today']->copy()->addDay(),
            4,
            []
        );

        $definitions = collect([
            [
                'date' => $this->dayAt($pastBusinessDays, 5)?->toDateString(),
                'name' => 'Company Anniversary',
                'type' => 'COMPANY',
                'overtime_multiplier' => 2.0,
            ],
            [
                'date' => $this->dayAt($futureBusinessDays, 1)?->toDateString(),
                'name' => 'Regional Service Holiday',
                'type' => 'NATIONAL',
                'overtime_multiplier' => 2.5,
            ],
        ])->filter(fn (array $holiday) => !empty($holiday['date']));

        return $definitions->map(function (array $definition) {
            return Holiday::updateOrCreate(
                ['date' => $definition['date']],
                $definition
            );
        })->values();
    }

    protected function seedLeaveData(Collection $users, array $period, Collection $holidays): array
    {
        $holidayDates = $holidays->map(fn (Holiday $holiday) => $holiday->date->toDateString())->all();
        $pastBusinessDays = $this->businessDaysInRange(
            $period['month_start'],
            $period['today']->copy()->subDay(),
            $holidayDates
        );
        $futureBusinessDays = $this->nextBusinessDays(
            $period['today']->copy()->addDay(),
            8,
            $holidayDates
        );

        $leaveTypes = LeaveType::query()->get()->keyBy('code');
        $opsAdmin = $users->get('ops_admin');

        $definitions = collect([
            [
                'user' => 'john',
                'type' => 'ANNUAL',
                'start' => $this->dayAt($pastBusinessDays, 4),
                'end' => $this->dayAt($pastBusinessDays, 5),
                'status' => 'APPROVED',
                'reason' => 'Family trip planned earlier in the month.',
                'reviewed_by' => $opsAdmin->id,
                'reviewed_at' => $period['today']->copy()->setTime(10, 15),
                'admin_note' => 'Approved and reflected in the balance.',
                'created_at' => $period['today']->copy()->subDays(10)->setTime(9, 0),
            ],
            [
                'user' => 'linda',
                'type' => 'PERSONAL',
                'start' => $this->dayAt($pastBusinessDays, 8),
                'end' => $this->dayAt($pastBusinessDays, 8),
                'status' => 'APPROVED',
                'reason' => 'Handled a family administrative appointment.',
                'reviewed_by' => $opsAdmin->id,
                'reviewed_at' => $period['today']->copy()->setTime(11, 0),
                'admin_note' => 'Approved.',
                'created_at' => $period['today']->copy()->subDays(6)->setTime(14, 10),
            ],
            [
                'user' => 'nisa',
                'type' => 'SICK',
                'start' => $this->dayAt($pastBusinessDays, 2),
                'end' => $this->dayAt($pastBusinessDays, 2),
                'status' => 'APPROVED',
                'reason' => 'Recovered from a short flu.',
                'reviewed_by' => $opsAdmin->id,
                'reviewed_at' => $period['today']->copy()->subDays(4)->setTime(9, 30),
                'admin_note' => 'Auto-approved sick leave example.',
                'created_at' => $period['today']->copy()->subDays(12)->setTime(8, 15),
            ],
            [
                'user' => 'supervisor',
                'type' => 'ANNUAL',
                'start' => $this->dayAt($pastBusinessDays, 10),
                'end' => $this->dayAt($pastBusinessDays, 10),
                'status' => 'APPROVED',
                'reason' => 'Used one leave day for personal matters.',
                'reviewed_by' => $users->get('admin')->id,
                'reviewed_at' => $period['today']->copy()->subDays(2)->setTime(16, 0),
                'admin_note' => 'Approved by super admin.',
                'created_at' => $period['today']->copy()->subDays(9)->setTime(10, 10),
            ],
            [
                'user' => 'sarah',
                'type' => 'ANNUAL',
                'start' => $this->dayAt($futureBusinessDays, 1),
                'end' => $this->dayAt($futureBusinessDays, 2),
                'status' => 'PENDING',
                'reason' => 'Requested upcoming annual leave for a long weekend.',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'admin_note' => null,
                'created_at' => $period['today']->copy()->subDays(1)->setTime(15, 20),
            ],
            [
                'user' => 'budi',
                'type' => 'PERSONAL',
                'start' => $this->dayAt($futureBusinessDays, 3),
                'end' => $this->dayAt($futureBusinessDays, 3),
                'status' => 'REJECTED',
                'reason' => 'Requested leave for a supplier visit that can be rescheduled.',
                'reviewed_by' => $opsAdmin->id,
                'reviewed_at' => $period['today']->copy()->setTime(14, 0),
                'admin_note' => 'Rejected because warehouse coverage is needed.',
                'created_at' => $period['today']->copy()->subDays(3)->setTime(11, 45),
            ],
            [
                'user' => 'rio',
                'type' => 'ANNUAL',
                'start' => $this->dayAt($futureBusinessDays, 4),
                'end' => $this->dayAt($futureBusinessDays, 4),
                'status' => 'CANCELLED',
                'reason' => 'Cancelled after sprint scope changed.',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'admin_note' => 'Cancelled by employee.',
                'created_at' => $period['today']->copy()->subDays(2)->setTime(13, 15),
            ],
            [
                'user' => 'eka',
                'type' => 'UNPAID',
                'start' => $this->dayAt($futureBusinessDays, 0),
                'end' => $this->dayAt($futureBusinessDays, 0),
                'status' => 'PENDING',
                'reason' => 'Requested unpaid leave for a personal errand.',
                'reviewed_by' => null,
                'reviewed_at' => null,
                'admin_note' => null,
                'created_at' => $period['today']->copy()->subHours(5),
            ],
        ]);

        $approvedDates = [];

        foreach ($definitions as $definition) {
            if (!$definition['start'] instanceof Carbon || !$definition['end'] instanceof Carbon) {
                continue;
            }

            $startDate = $definition['start']->copy();
            $endDate = $definition['end']->copy();

            if ($endDate->lt($startDate)) {
                $endDate = $startDate->copy();
            }

            $leaveType = $leaveTypes->get($definition['type']);
            $user = $users->get($definition['user']);
            $daysRequested = $this->countBusinessDays($startDate, $endDate, $holidayDates);

            if ($daysRequested <= 0) {
                continue;
            }

            $leaveRequest = $this->saveModel(
                LeaveRequest::class,
                [
                    'user_id' => $user->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                ],
                [
                    'user_id' => $user->id,
                    'leave_type_id' => $leaveType->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'days_requested' => $daysRequested,
                    'reason' => $definition['reason'],
                    'status' => $definition['status'],
                    'reviewed_by' => $definition['reviewed_by'],
                    'reviewed_at' => $definition['reviewed_at'],
                    'admin_note' => $definition['admin_note'],
                    'created_at' => $definition['created_at'],
                    'updated_at' => $definition['reviewed_at'] ?? now(),
                ]
            );

            if ($leaveRequest->status === 'APPROVED') {
                foreach ($this->businessDatesBetween($startDate, $endDate, $holidayDates) as $date) {
                    $approvedDates[$user->id][$date->toDateString()] = true;
                }
            }
        }

        $this->rebuildLeaveBalances($users, $leaveTypes, (int) $period['today']->year);

        return ['approved_dates' => $approvedDates];
    }

    protected function seedAttendanceRequests(
        Collection $users,
        Collection $locations,
        array $period,
        Collection $holidays,
        Collection $deviceFingerprints
    ): array {
        $holidayDates = $holidays->map(fn (Holiday $holiday) => $holiday->date->toDateString())->all();
        $businessDays = $this->businessDaysInRange(
            $period['month_start'],
            $period['today']->copy()->subDay(),
            $holidayDates
        );

        $opsAdmin = $users->get('ops_admin');
        $manualCheckIns = [];

        $approvedDefinitions = [
            [
                'user' => 'john',
                'location' => 'hq',
                'time' => $this->dayAt($businessDays, 1)?->copy()->setTime(9, 12),
                'reason' => 'Missed the barcode due to unstable connection during morning arrival.',
                'failure_reason' => 'Barcode expired while reconnecting.',
            ],
            [
                'user' => 'nisa',
                'location' => 'bandung',
                'time' => $this->dayAt($businessDays, 6)?->copy()->setTime(13, 5),
                'reason' => 'Needed a manual check-in because the display screen restarted during shift start.',
                'failure_reason' => 'Display screen was restarting.',
            ],
        ];

        foreach ($approvedDefinitions as $definition) {
            if (!$definition['time'] instanceof Carbon) {
                continue;
            }

            $user = $users->get($definition['user']);
            $location = $locations->get($definition['location']);

            $attendanceRequest = $this->saveModel(
                AttendanceRequest::class,
                [
                    'user_id' => $user->id,
                    'check_type' => 'IN',
                    'request_time' => $definition['time'],
                ],
                [
                    'user_id' => $user->id,
                    'location_id' => $location->id,
                    'request_time' => $definition['time'],
                    'check_type' => 'IN',
                    'gps_lat' => $location->latitude,
                    'gps_lng' => $location->longitude,
                    'distance_m' => 18.4,
                    'gps_accuracy_m' => 12.0,
                    'reason' => $definition['reason'],
                    'photo_path' => null,
                    'failure_reason' => $definition['failure_reason'],
                    'ip_address' => $this->ipForUser($user),
                    'status' => 'APPROVED',
                    'admin_note' => 'Approved for demo data.',
                    'reviewed_by' => $opsAdmin->id,
                    'reviewed_at' => $period['today']->copy()->setTime(10, 30),
                    'created_at' => $definition['time']->copy()->addHours(1),
                    'updated_at' => $period['today']->copy()->setTime(10, 30),
                ]
            );

            $lateMin = 0;
            $penaltyTier = 'NONE';

            $attendance = $this->saveAttendanceRecord(
                [
                    'user_id' => $user->id,
                    'check_type' => 'IN',
                    'scan_time' => $definition['time'],
                ],
                [
                    'user_id' => $user->id,
                    'location_id' => $location->id,
                    'scan_time' => $definition['time'],
                    'check_type' => 'IN',
                    'gps_lat' => $location->latitude,
                    'gps_lng' => $location->longitude,
                    'gps_accuracy_m' => 12.0,
                    'distance_m' => 18.4,
                    'time_slot' => $this->timeSlotFor($definition['time']),
                    'ip_address' => $this->ipForUser($user),
                    'device_id' => $deviceFingerprints->get($user->id, 'seed-device-' . $user->id),
                    'status' => 'ON_TIME',
                    'late_min' => $lateMin,
                    'early_leave_min' => 0,
                    'work_minutes' => 0,
                    'penalty_tier' => $penaltyTier,
                    'is_holiday' => false,
                    'overtime_min' => 0,
                    'overtime_multiplier' => 1.0,
                    'method' => 'MANUAL',
                    'approved_by' => $opsAdmin->id,
                    'approved_at' => $period['today']->copy()->setTime(10, 30),
                    'created_at' => $definition['time']->copy()->addHours(1),
                    'updated_at' => $period['today']->copy()->setTime(10, 30),
                ]
            );

            $this->saveAttendanceLog(
                ['attendance_request_id' => $attendanceRequest->id, 'action' => 'MANUAL_REQUEST'],
                [
                    'user_id' => $user->id,
                    'attendance_id' => null,
                    'attendance_request_id' => $attendanceRequest->id,
                    'action' => 'MANUAL_REQUEST',
                    'actor_id' => $user->id,
                    'reason' => $definition['reason'],
                    'payload' => ['failure_reason' => $definition['failure_reason']],
                    'created_at' => $definition['time']->copy()->addHours(1),
                    'updated_at' => $definition['time']->copy()->addHours(1),
                ]
            );

            $this->saveAttendanceLog(
                ['attendance_request_id' => $attendanceRequest->id, 'action' => 'MANUAL_APPROVE'],
                [
                    'user_id' => $user->id,
                    'attendance_id' => $attendance->id,
                    'attendance_request_id' => $attendanceRequest->id,
                    'action' => 'MANUAL_APPROVE',
                    'actor_id' => $opsAdmin->id,
                    'reason' => 'Approved for demo data.',
                    'payload' => ['adjusted_time' => $definition['time']->toIso8601String()],
                    'created_at' => $period['today']->copy()->setTime(10, 30),
                    'updated_at' => $period['today']->copy()->setTime(10, 30),
                ]
            );

            $manualCheckIns[$user->id][$definition['time']->toDateString()] = $definition['time']->copy();
        }

        $pendingDefinitions = [
            [
                'user' => 'sarah',
                'location' => 'hq',
                'check_type' => 'IN',
                'time' => $period['today']->copy()->setTime(9, 40),
                'reason' => 'Network timeout prevented the request from completing after reaching the office.',
                'failure_reason' => 'API request timed out.',
                'distance_m' => 22.7,
                'created_at' => $period['today']->copy()->subHours(2),
            ],
            [
                'user' => 'budi',
                'location' => 'warehouse',
                'check_type' => 'OUT',
                'time' => $period['today']->copy()->subDay()->setTime(17, 8),
                'reason' => 'Needed a manual checkout after the warehouse tablet battery ran out.',
                'failure_reason' => 'Device battery depleted.',
                'distance_m' => 31.2,
                'created_at' => $period['today']->copy()->subDay()->setTime(17, 20),
            ],
        ];

        foreach ($pendingDefinitions as $definition) {
            $user = $users->get($definition['user']);
            $location = $locations->get($definition['location']);

            $attendanceRequest = $this->saveModel(
                AttendanceRequest::class,
                [
                    'user_id' => $user->id,
                    'check_type' => $definition['check_type'],
                    'request_time' => $definition['time'],
                ],
                [
                    'user_id' => $user->id,
                    'location_id' => $location->id,
                    'request_time' => $definition['time'],
                    'check_type' => $definition['check_type'],
                    'gps_lat' => $location->latitude,
                    'gps_lng' => $location->longitude,
                    'distance_m' => $definition['distance_m'],
                    'gps_accuracy_m' => 15.0,
                    'reason' => $definition['reason'],
                    'photo_path' => null,
                    'failure_reason' => $definition['failure_reason'],
                    'ip_address' => $this->ipForUser($user),
                    'status' => 'PENDING',
                    'admin_note' => null,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'created_at' => $definition['created_at'],
                    'updated_at' => $definition['created_at'],
                ]
            );

            $this->saveAttendanceLog(
                ['attendance_request_id' => $attendanceRequest->id, 'action' => 'MANUAL_REQUEST'],
                [
                    'user_id' => $user->id,
                    'attendance_id' => null,
                    'attendance_request_id' => $attendanceRequest->id,
                    'action' => 'MANUAL_REQUEST',
                    'actor_id' => $user->id,
                    'reason' => $definition['reason'],
                    'payload' => ['failure_reason' => $definition['failure_reason']],
                    'created_at' => $definition['created_at'],
                    'updated_at' => $definition['created_at'],
                ]
            );
        }

        $rejectedTime = $this->dayAt($businessDays, 3)?->copy()->setTime(8, 4);
        if ($rejectedTime instanceof Carbon) {
            $user = $users->get('eka');
            $location = $locations->get('warehouse');

            $attendanceRequest = $this->saveModel(
                AttendanceRequest::class,
                [
                    'user_id' => $user->id,
                    'check_type' => 'IN',
                    'request_time' => $rejectedTime,
                ],
                [
                    'user_id' => $user->id,
                    'location_id' => $location->id,
                    'request_time' => $rejectedTime,
                    'check_type' => 'IN',
                    'gps_lat' => $location->latitude,
                    'gps_lng' => $location->longitude,
                    'distance_m' => 1250.0,
                    'gps_accuracy_m' => 45.0,
                    'reason' => 'Submitted a check-in request from outside the operational area.',
                    'photo_path' => null,
                    'failure_reason' => 'Outside the allowed radius.',
                    'ip_address' => $this->ipForUser($user),
                    'status' => 'REJECTED',
                    'admin_note' => 'Rejected because the GPS position was far outside the site radius.',
                    'reviewed_by' => $opsAdmin->id,
                    'reviewed_at' => $period['today']->copy()->setTime(13, 20),
                    'created_at' => $rejectedTime->copy()->addMinutes(25),
                    'updated_at' => $period['today']->copy()->setTime(13, 20),
                ]
            );

            $this->saveAttendanceLog(
                ['attendance_request_id' => $attendanceRequest->id, 'action' => 'MANUAL_REQUEST'],
                [
                    'user_id' => $user->id,
                    'attendance_id' => null,
                    'attendance_request_id' => $attendanceRequest->id,
                    'action' => 'MANUAL_REQUEST',
                    'actor_id' => $user->id,
                    'reason' => 'Submitted a check-in request from outside the operational area.',
                    'payload' => ['failure_reason' => 'Outside the allowed radius.'],
                    'created_at' => $rejectedTime->copy()->addMinutes(25),
                    'updated_at' => $rejectedTime->copy()->addMinutes(25),
                ]
            );

            $this->saveAttendanceLog(
                ['attendance_request_id' => $attendanceRequest->id, 'action' => 'MANUAL_REJECT'],
                [
                    'user_id' => $user->id,
                    'attendance_id' => null,
                    'attendance_request_id' => $attendanceRequest->id,
                    'action' => 'MANUAL_REJECT',
                    'actor_id' => $opsAdmin->id,
                    'reason' => 'Rejected because the GPS position was far outside the site radius.',
                    'payload' => ['distance_m' => 1250.0],
                    'created_at' => $period['today']->copy()->setTime(13, 20),
                    'updated_at' => $period['today']->copy()->setTime(13, 20),
                ]
            );
        }

        return $manualCheckIns;
    }

    protected function seedAttendances(
        Collection $users,
        Collection $locations,
        Collection $shifts,
        array $period,
        Collection $holidays,
        array $approvedLeaveDates,
        array $manualCheckIns,
        Collection $deviceFingerprints
    ): void {
        $holidayDates = $holidays->map(fn (Holiday $holiday) => $holiday->date->toDateString())->all();
        $attendanceDays = $this->businessDaysInRange(
            $period['month_start'],
            $period['today']->copy()->subDay(),
            $holidayDates
        );

        $profiles = [
            'supervisor' => ['shift' => 'SHIFT-FLEXI', 'late' => [6], 'absent' => [], 'early' => [], 'overtime' => [2 => 45, 12 => 60]],
            'john' => ['shift' => 'SHIFT-FLEXI', 'late' => [7], 'absent' => [14], 'early' => [], 'overtime' => [5 => 75, 11 => 45]],
            'sarah' => ['shift' => 'SHIFT-FLEXI', 'late' => [3, 10], 'absent' => [], 'early' => [], 'overtime' => [8 => 30]],
            'budi' => ['shift' => 'SHIFT-MORNING', 'late' => [4, 9], 'absent' => [13], 'early' => [6], 'overtime' => [11 => 60]],
            'linda' => ['shift' => 'SHIFT-MORNING', 'late' => [], 'absent' => [], 'early' => [], 'overtime' => [9 => 35]],
            'andre' => ['shift' => 'SHIFT-MORNING', 'late' => [2], 'absent' => [], 'early' => [], 'overtime' => [4 => 55, 10 => 40]],
            'nisa' => ['shift' => 'SHIFT-AFTERNOON', 'late' => [5], 'absent' => [], 'early' => [], 'overtime' => [7 => 30]],
            'rio' => ['shift' => 'SHIFT-FLEXI', 'late' => [], 'absent' => [9], 'early' => [], 'overtime' => [6 => 90]],
            'eka' => ['shift' => 'SHIFT-MORNING', 'late' => [8], 'absent' => [11], 'early' => [3], 'overtime' => []],
        ];

        $userOrder = array_keys($profiles);

        foreach ($userOrder as $index => $userKey) {
            $user = $users->get($userKey);
            $location = $user->defaultLocation;
            $shift = $shifts->get($profiles[$userKey]['shift']);
            $lateDates = $this->datesForIndexes($attendanceDays, $profiles[$userKey]['late']);
            $absentDates = $this->datesForIndexes($attendanceDays, $profiles[$userKey]['absent']);
            $earlyDates = $this->datesForIndexes($attendanceDays, $profiles[$userKey]['early']);
            $overtimePlan = $this->minutesForIndexedDates($attendanceDays, $profiles[$userKey]['overtime']);

            foreach ($attendanceDays as $date) {
                $dateKey = $date->toDateString();

                if (($approvedLeaveDates[$user->id][$dateKey] ?? false) === true) {
                    continue;
                }

                $manualCheckIn = $manualCheckIns[$user->id][$dateKey] ?? null;

                if (isset($absentDates[$dateKey]) && !$manualCheckIn) {
                    $absentTime = $this->shiftStartForDate($shift, $date);

                    $attendance = $this->saveAttendanceRecord(
                        [
                            'user_id' => $user->id,
                            'check_type' => 'IN',
                            'scan_time' => $absentTime,
                        ],
                        [
                            'user_id' => $user->id,
                            'location_id' => $location->id,
                            'scan_time' => $absentTime,
                            'check_type' => 'IN',
                            'gps_lat' => null,
                            'gps_lng' => null,
                            'gps_accuracy_m' => null,
                            'distance_m' => null,
                            'time_slot' => null,
                            'ip_address' => null,
                            'device_id' => null,
                            'status' => 'ABSENT',
                            'late_min' => 0,
                            'early_leave_min' => 0,
                            'work_minutes' => 0,
                            'penalty_tier' => 'ABSENT',
                            'is_holiday' => false,
                            'overtime_min' => 0,
                            'overtime_multiplier' => 1.0,
                            'method' => 'SYSTEM',
                            'approved_by' => null,
                            'approved_at' => null,
                            'created_at' => $absentTime,
                            'updated_at' => $absentTime,
                        ]
                    );

                    $this->saveAttendanceLog(
                        ['attendance_id' => $attendance->id, 'action' => 'SYSTEM_ABSENT'],
                        [
                            'user_id' => $user->id,
                            'attendance_id' => $attendance->id,
                            'attendance_request_id' => null,
                            'action' => 'SYSTEM_ABSENT',
                            'actor_id' => null,
                            'reason' => 'System marked employee absent because no check-in was recorded.',
                            'payload' => ['date' => $dateKey],
                            'created_at' => $absentTime,
                            'updated_at' => $absentTime,
                        ]
                    );

                    continue;
                }

                $checkInTime = $manualCheckIn instanceof Carbon
                    ? $manualCheckIn->copy()
                    : $this->buildCheckInTime(
                        $shift,
                        $date,
                        isset($lateDates[$dateKey]),
                        $index
                    );

                $lateMin = max(0, $checkInTime->diffInMinutes($this->shiftStartForDate($shift, $date), false));
                $isLate = isset($lateDates[$dateKey]) && !$manualCheckIn;

                if ($isLate) {
                    $lateMin = $this->shiftStartForDate($shift, $date)->diffInMinutes($checkInTime);
                } else {
                    $lateMin = 0;
                }

                if (!$manualCheckIn) {
                    $attendanceIn = $this->saveAttendanceRecord(
                        [
                            'user_id' => $user->id,
                            'check_type' => 'IN',
                            'scan_time' => $checkInTime,
                        ],
                        [
                            'user_id' => $user->id,
                            'location_id' => $location->id,
                            'scan_time' => $checkInTime,
                            'check_type' => 'IN',
                            'gps_lat' => $location->latitude,
                            'gps_lng' => $location->longitude,
                            'gps_accuracy_m' => 10.5,
                            'distance_m' => 18.0 + $index,
                            'time_slot' => $this->timeSlotFor($checkInTime),
                            'ip_address' => $this->ipForUser($user),
                            'device_id' => $deviceFingerprints->get($user->id, 'seed-device-' . $user->id),
                            'status' => $isLate ? 'LATE' : 'ON_TIME',
                            'late_min' => $lateMin,
                            'early_leave_min' => 0,
                            'work_minutes' => 0,
                            'penalty_tier' => $isLate ? $this->penaltyTierForLateMinutes($lateMin) : 'NONE',
                            'is_holiday' => false,
                            'overtime_min' => 0,
                            'overtime_multiplier' => 1.0,
                            'method' => 'AUTO',
                            'approved_by' => null,
                            'approved_at' => null,
                            'created_at' => $checkInTime,
                            'updated_at' => $checkInTime,
                        ]
                    );

                    $this->saveAttendanceLog(
                        ['attendance_id' => $attendanceIn->id, 'action' => 'AUTO_CHECKIN'],
                        [
                            'user_id' => $user->id,
                            'attendance_id' => $attendanceIn->id,
                            'attendance_request_id' => null,
                            'action' => 'AUTO_CHECKIN',
                            'actor_id' => $user->id,
                            'reason' => null,
                            'payload' => ['distance_m' => 18.0 + $index],
                            'created_at' => $checkInTime,
                            'updated_at' => $checkInTime,
                        ]
                    );
                }

                $earlyLeaveMin = isset($earlyDates[$dateKey]) ? 35 : 0;
                $regularOvertimeMin = $overtimePlan[$dateKey] ?? 0;
                $shiftEnd = $this->shiftEndForDate($shift, $date);
                $checkOutTime = $earlyLeaveMin > 0
                    ? $shiftEnd->copy()->subMinutes($earlyLeaveMin)
                    : $shiftEnd->copy()->addMinutes($regularOvertimeMin);

                $workMinutes = $checkInTime->diffInMinutes($checkOutTime);
                $checkOutStatus = $earlyLeaveMin > 0 ? 'EARLY' : 'ON_TIME';

                $attendanceOut = $this->saveAttendanceRecord(
                    [
                        'user_id' => $user->id,
                        'check_type' => 'OUT',
                        'scan_time' => $checkOutTime,
                    ],
                    [
                        'user_id' => $user->id,
                        'location_id' => $location->id,
                        'scan_time' => $checkOutTime,
                        'check_type' => 'OUT',
                        'gps_lat' => $location->latitude,
                        'gps_lng' => $location->longitude,
                        'gps_accuracy_m' => 11.0,
                        'distance_m' => 20.0 + $index,
                        'time_slot' => $this->timeSlotFor($checkOutTime),
                        'ip_address' => $this->ipForUser($user),
                        'device_id' => $deviceFingerprints->get($user->id, 'seed-device-' . $user->id),
                        'status' => $checkOutStatus,
                        'late_min' => 0,
                        'early_leave_min' => $earlyLeaveMin,
                        'work_minutes' => $workMinutes,
                        'penalty_tier' => 'NONE',
                        'is_holiday' => false,
                        'overtime_min' => $regularOvertimeMin,
                        'overtime_multiplier' => 1.0,
                        'method' => 'AUTO',
                        'approved_by' => null,
                        'approved_at' => null,
                        'created_at' => $checkOutTime,
                        'updated_at' => $checkOutTime,
                    ]
                );

                $this->saveAttendanceLog(
                    ['attendance_id' => $attendanceOut->id, 'action' => 'AUTO_CHECKOUT'],
                    [
                        'user_id' => $user->id,
                        'attendance_id' => $attendanceOut->id,
                        'attendance_request_id' => null,
                        'action' => 'AUTO_CHECKOUT',
                        'actor_id' => $user->id,
                        'reason' => null,
                        'payload' => ['work_minutes' => $workMinutes, 'overtime_min' => $regularOvertimeMin],
                        'created_at' => $checkOutTime,
                        'updated_at' => $checkOutTime,
                    ]
                );
            }
        }

        $todayContext = $this->overtimeContextForDate($period['today'], $holidayDates);
        $todayDefinitions = [
            [
                'user' => 'supervisor',
                'shift' => 'SHIFT-FLEXI',
                'location' => 'hq',
                'check_in' => $period['today']->copy()->setTime(9, 8),
                'check_out' => null,
                'status' => 'ON_TIME',
                'late_min' => 0,
            ],
            [
                'user' => 'john',
                'shift' => 'SHIFT-FLEXI',
                'location' => 'hq',
                'check_in' => $period['today']->copy()->setTime(9, 18),
                'check_out' => $period['today']->copy()->setTime(15, 40),
                'status' => 'ON_TIME',
                'late_min' => 0,
            ],
            [
                'user' => 'budi',
                'shift' => 'SHIFT-MORNING',
                'location' => 'warehouse',
                'check_in' => $period['today']->copy()->setTime(8, 27),
                'check_out' => $period['today']->copy()->setTime(17, 40),
                'status' => 'LATE',
                'late_min' => 27,
            ],
            [
                'user' => 'andre',
                'shift' => 'SHIFT-MORNING',
                'location' => 'hq',
                'check_in' => $period['today']->copy()->setTime(7, 59),
                'check_out' => $period['today']->copy()->setTime(16, 10),
                'status' => 'ON_TIME',
                'late_min' => 0,
            ],
        ];

        foreach ($todayDefinitions as $definition) {
            $user = $users->get($definition['user']);
            $location = $locations->get($definition['location']);
            $shift = $shifts->get($definition['shift']);

            $attendanceIn = $this->saveAttendanceRecord(
                [
                    'user_id' => $user->id,
                    'check_type' => 'IN',
                    'scan_time' => $definition['check_in'],
                ],
                [
                    'user_id' => $user->id,
                    'location_id' => $location->id,
                    'scan_time' => $definition['check_in'],
                    'check_type' => 'IN',
                    'gps_lat' => $location->latitude,
                    'gps_lng' => $location->longitude,
                    'gps_accuracy_m' => 9.5,
                    'distance_m' => 16.0,
                    'time_slot' => $this->timeSlotFor($definition['check_in']),
                    'ip_address' => $this->ipForUser($user),
                    'device_id' => $deviceFingerprints->get($user->id, 'seed-device-' . $user->id),
                    'status' => $definition['status'],
                    'late_min' => $definition['late_min'],
                    'early_leave_min' => 0,
                    'work_minutes' => 0,
                    'penalty_tier' => $definition['late_min'] > 0
                        ? $this->penaltyTierForLateMinutes($definition['late_min'])
                        : 'NONE',
                    'is_holiday' => $todayContext['is_holiday'],
                    'overtime_min' => 0,
                    'overtime_multiplier' => $todayContext['multiplier'],
                    'method' => 'AUTO',
                    'approved_by' => null,
                    'approved_at' => null,
                    'created_at' => $definition['check_in'],
                    'updated_at' => $definition['check_in'],
                ]
            );

            $this->saveAttendanceLog(
                ['attendance_id' => $attendanceIn->id, 'action' => 'AUTO_CHECKIN'],
                [
                    'user_id' => $user->id,
                    'attendance_id' => $attendanceIn->id,
                    'attendance_request_id' => null,
                    'action' => 'AUTO_CHECKIN',
                    'actor_id' => $user->id,
                    'reason' => null,
                    'payload' => ['distance_m' => 16.0],
                    'created_at' => $definition['check_in'],
                    'updated_at' => $definition['check_in'],
                ]
            );

            if (!$definition['check_out']) {
                continue;
            }

            $workMinutes = $definition['check_in']->diffInMinutes($definition['check_out']);
            $regularShiftMinutes = $this->shiftStartForDate($shift, $period['today'])
                ->diffInMinutes($this->shiftEndForDate($shift, $period['today']));
            $overtimeMin = $todayContext['all_minutes_overtime']
                ? $workMinutes
                : max(0, $workMinutes - $regularShiftMinutes);

            $attendanceOut = $this->saveAttendanceRecord(
                [
                    'user_id' => $user->id,
                    'check_type' => 'OUT',
                    'scan_time' => $definition['check_out'],
                ],
                [
                    'user_id' => $user->id,
                    'location_id' => $location->id,
                    'scan_time' => $definition['check_out'],
                    'check_type' => 'OUT',
                    'gps_lat' => $location->latitude,
                    'gps_lng' => $location->longitude,
                    'gps_accuracy_m' => 10.0,
                    'distance_m' => 18.0,
                    'time_slot' => $this->timeSlotFor($definition['check_out']),
                    'ip_address' => $this->ipForUser($user),
                    'device_id' => $deviceFingerprints->get($user->id, 'seed-device-' . $user->id),
                    'status' => 'ON_TIME',
                    'late_min' => 0,
                    'early_leave_min' => 0,
                    'work_minutes' => $workMinutes,
                    'penalty_tier' => 'NONE',
                    'is_holiday' => $todayContext['is_holiday'],
                    'overtime_min' => $overtimeMin,
                    'overtime_multiplier' => $todayContext['multiplier'],
                    'method' => 'AUTO',
                    'approved_by' => null,
                    'approved_at' => null,
                    'created_at' => $definition['check_out'],
                    'updated_at' => $definition['check_out'],
                ]
            );

            $this->saveAttendanceLog(
                ['attendance_id' => $attendanceOut->id, 'action' => 'AUTO_CHECKOUT'],
                [
                    'user_id' => $user->id,
                    'attendance_id' => $attendanceOut->id,
                    'attendance_request_id' => null,
                    'action' => 'AUTO_CHECKOUT',
                    'actor_id' => $user->id,
                    'reason' => null,
                    'payload' => ['work_minutes' => $workMinutes, 'overtime_min' => $overtimeMin],
                    'created_at' => $definition['check_out'],
                    'updated_at' => $definition['check_out'],
                ]
            );
        }
    }

    protected function seedNotifications(Collection $users, array $period): void
    {
        $definitions = [
            [
                'user' => 'ops_admin',
                'title' => 'Attendance requests pending',
                'message' => 'There are manual attendance requests waiting for review.',
                'type' => 'warning',
                'is_read' => false,
                'created_at' => $period['today']->copy()->setTime(9, 45),
            ],
            [
                'user' => 'john',
                'title' => 'Manual request approved',
                'message' => 'Your manual attendance request was approved by admin.',
                'type' => 'success',
                'is_read' => true,
                'created_at' => $period['today']->copy()->subDays(1)->setTime(16, 15),
            ],
            [
                'user' => 'sarah',
                'title' => 'Leave request pending',
                'message' => 'Your annual leave request is waiting for approval.',
                'type' => 'info',
                'is_read' => false,
                'created_at' => $period['today']->copy()->subHours(6),
            ],
            [
                'user' => 'budi',
                'title' => 'Leave request rejected',
                'message' => 'Your personal leave request was rejected due to coverage needs.',
                'type' => 'error',
                'is_read' => false,
                'created_at' => $period['today']->copy()->setTime(14, 5),
            ],
            [
                'user' => 'supervisor',
                'title' => 'Weekend shift active',
                'message' => 'A weekend attendance window is active for today.',
                'type' => 'info',
                'is_read' => false,
                'created_at' => $period['today']->copy()->setTime(8, 0),
            ],
        ];

        foreach ($definitions as $definition) {
            $user = $users->get($definition['user']);

            $this->saveModel(
                Notification::class,
                [
                    'user_id' => $user->id,
                    'title' => $definition['title'],
                    'message' => $definition['message'],
                ],
                [
                    'user_id' => $user->id,
                    'title' => $definition['title'],
                    'message' => $definition['message'],
                    'channel' => 'in_app',
                    'type' => $definition['type'],
                    'is_read' => $definition['is_read'],
                    'read_at' => $definition['is_read'] ? $definition['created_at']->copy()->addHours(3) : null,
                    'created_at' => $definition['created_at'],
                    'updated_at' => $definition['created_at'],
                ]
            );
        }
    }

    protected function rebuildLeaveBalances(Collection $users, Collection $leaveTypes, int $year): void
    {
        foreach ($users as $userKey => $user) {
            if ($userKey === 'display') {
                continue;
            }

            foreach ($leaveTypes as $leaveType) {
                $usedDays = (float) LeaveRequest::query()
                    ->where('user_id', $user->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('status', 'APPROVED')
                    ->whereYear('start_date', $year)
                    ->sum('days_requested');

                $pendingDays = (float) LeaveRequest::query()
                    ->where('user_id', $user->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('status', 'PENDING')
                    ->whereYear('start_date', $year)
                    ->sum('days_requested');

                LeaveBalance::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $year,
                    ],
                    [
                        'allocated_days' => $leaveType->default_days,
                        'used_days' => $usedDays,
                        'pending_days' => $pendingDays,
                    ]
                );
            }
        }
    }

    protected function saveAttendanceRecord(array $identity, array $attributes): Attendance
    {
        /** @var Attendance $attendance */
        $attendance = $this->saveModel(Attendance::class, $identity, $attributes);

        return $attendance;
    }

    protected function saveAttendanceLog(array $identity, array $attributes): AttendanceLog
    {
        /** @var AttendanceLog $log */
        $log = $this->saveModel(AttendanceLog::class, $identity, $attributes);

        return $log;
    }

    protected function saveModel(string $modelClass, array $identity, array $attributes): Model
    {
        /** @var Model $model */
        $model = $modelClass::query()->firstOrNew($identity);
        $model->forceFill($attributes);
        $model->save();

        return $model;
    }

    protected function businessDaysInRange(Carbon $start, Carbon $end, array $holidayDates): Collection
    {
        if ($end->lt($start)) {
            return collect();
        }

        $dates = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dateKey = $cursor->toDateString();

            if ($cursor->isWeekday() && !in_array($dateKey, $holidayDates, true)) {
                $dates->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $dates;
    }

    protected function nextBusinessDays(Carbon $start, int $count, array $holidayDates): Collection
    {
        $dates = collect();
        $cursor = $start->copy();

        while ($dates->count() < $count) {
            $dateKey = $cursor->toDateString();

            if ($cursor->isWeekday() && !in_array($dateKey, $holidayDates, true)) {
                $dates->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $dates;
    }

    protected function businessDatesBetween(Carbon $start, Carbon $end, array $holidayDates): Collection
    {
        return $this->businessDaysInRange($start->copy()->startOfDay(), $end->copy()->startOfDay(), $holidayDates);
    }

    protected function countBusinessDays(Carbon $start, Carbon $end, array $holidayDates): float
    {
        return (float) $this->businessDatesBetween($start, $end, $holidayDates)->count();
    }

    protected function dayAt(Collection $days, int $index): ?Carbon
    {
        if ($days->isEmpty()) {
            return null;
        }

        $index = max(0, min($index, $days->count() - 1));

        return $days->values()->get($index)?->copy();
    }

    protected function datesForIndexes(Collection $days, array $indexes): array
    {
        $mapped = [];

        foreach ($indexes as $index) {
            $date = $this->dayAt($days, $index);

            if ($date instanceof Carbon) {
                $mapped[$date->toDateString()] = true;
            }
        }

        return $mapped;
    }

    protected function minutesForIndexedDates(Collection $days, array $indexedMinutes): array
    {
        $mapped = [];

        foreach ($indexedMinutes as $index => $minutes) {
            $date = $this->dayAt($days, (int) $index);

            if ($date instanceof Carbon) {
                $mapped[$date->toDateString()] = (int) $minutes;
            }
        }

        return $mapped;
    }

    protected function shiftStartForDate(Shift $shift, Carbon $date): Carbon
    {
        return Carbon::parse($date->toDateString() . ' ' . Carbon::parse($shift->start_time)->format('H:i:s'));
    }

    protected function shiftEndForDate(Shift $shift, Carbon $date): Carbon
    {
        $start = $this->shiftStartForDate($shift, $date);
        $end = Carbon::parse($date->toDateString() . ' ' . Carbon::parse($shift->end_time)->format('H:i:s'));

        if ($end->lte($start)) {
            $end->addDay();
        }

        return $end;
    }

    protected function buildCheckInTime(Shift $shift, Carbon $date, bool $isLate, int $offsetSeed): Carbon
    {
        $shiftStart = $this->shiftStartForDate($shift, $date);

        if ($isLate) {
            $lateMin = $shift->late_after_min + 4 + (($offsetSeed * 5 + $date->day) % 18);

            return $shiftStart->copy()->addMinutes($lateMin);
        }

        $offsets = [-5, -3, -1, 2, 4];
        $offset = $offsets[$offsetSeed % count($offsets)];

        return $shiftStart->copy()->addMinutes($offset);
    }

    protected function penaltyTierForLateMinutes(int $lateMinutes): string
    {
        if ($lateMinutes <= 0) {
            return 'NONE';
        }

        return LatePenaltyTier::findForMinutes($lateMinutes)?->penalty_type ?? 'NONE';
    }

    protected function overtimeContextForDate(Carbon $date, array $holidayDates): array
    {
        $dateKey = $date->toDateString();

        if (in_array($dateKey, $holidayDates, true)) {
            return [
                'multiplier' => 2.0,
                'is_holiday' => true,
                'all_minutes_overtime' => true,
            ];
        }

        if ($date->isSaturday()) {
            return [
                'multiplier' => 1.5,
                'is_holiday' => false,
                'all_minutes_overtime' => true,
            ];
        }

        if ($date->isSunday()) {
            return [
                'multiplier' => 2.0,
                'is_holiday' => false,
                'all_minutes_overtime' => true,
            ];
        }

        return [
            'multiplier' => 1.0,
            'is_holiday' => false,
            'all_minutes_overtime' => false,
        ];
    }

    protected function timeSlotFor(Carbon $time): string
    {
        $slotStart = $time->copy()->startOfMinute();
        $slotEnd = $slotStart->copy()->addMinutes(5);

        return $slotStart->format('H:i') . '-' . $slotEnd->format('H:i');
    }

    protected function ipForUser(User $user): string
    {
        return '10.10.0.' . (($user->id % 200) + 20);
    }
}
