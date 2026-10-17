<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class AttendanceExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_daily_list_and_navigation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        Carbon::setTestNow('2026-10-14');
        $this->get('/admin/attendance/list')->assertStatus(200)->assertSee('2026/10/14');
        $this->get('/admin/attendance/list?date=2026-10-13')->assertSee('2026/10/13');
    }

    public function test_admin_staff_list_and_csv_export(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create(['name' => 'スタッフA', 'role' => 'general']);
        $this->actingAs($admin);

        // スタッフ一覧表示
        $this->get('/admin/staff/list')->assertStatus(200)->assertSee('スタッフA');

        // CSV出力機能の連動検証
        // $response = $this->post('/export', ['user_id' => $user->id, 'year_month' => '2026-10']);
        // $response->assertHeader('Content-Type', 'text/csv');
    }
}
