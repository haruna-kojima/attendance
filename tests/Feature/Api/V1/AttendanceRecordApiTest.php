<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Attendance;
use App\Models\BreakLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttendanceRecordApiTest extends TestCase
{
    use RefreshDatabase; // テスト実行ごとにデータベースを綺麗にリセットする設定

    /**
     * AP01: 勤怠一覧API (GET) のテスト
     */
    public function test_can_get_attendance_records_list(): void
    {
        $user = User::factory()->create(['name' => '山田太郎', 'role' => 'general']);
        Attendance::factory()->create([
            'user_id' => $user->id,
            'date' => '2026-05-01',
            'clock_in_at' => '2026-05-01 09:00:00',
            'clock_out_at' => '2026-05-01 18:00:00',
        ]);

        $response = $this->getJson('/api/v1/attendance-records');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonFragment(['user_name' => '山田太郎']);
    }

    /**
     * AP02: 勤怠詳細API (GET) のテスト
     */
    public function test_can_get_attendance_record_detail(): void
    {
        $user = User::factory()->create(['name' => '山田太郎']);
        $record = Attendance::factory()->create(['user_id' => $user->id, 'date' => '2026-05-01']);

        // 正常系 (200)
        $response = $this->getJson("/api/v1/attendance-records/{$record->id}");
        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'user', 'breaks', 'applications']]);

        // 異常系: 存在しないID (404)
        $invalidResponse = $this->getJson('/api/v1/attendance-records/99999');
        $invalidResponse->assertStatus(404)
            ->assertJson(['error' => '勤怠情報が見つかりませんでした。']);
    }

    /**
     * AP03: 勤怠登録API (POST) のテスト
     */
    public function test_can_store_attendance_record(): void
    {
        $user = User::factory()->create(['role' => 'general']);

        // 未認証時は 401
        $this->postJson('/api/v1/attendance-records', [])->assertStatus(401);

        // 仕様書指定: Sanctum認証をシミュレート
        Sanctum::actingAs($user, ['*']);

        // 正常系 (201)
        $postData = [
            'date' => '2026-05-01',
            'clock_in' => '09:00:00',
            'clock_out' => '18:00:00',
            'comment' => 'テスト備考'
        ];
        $this->postJson('/api/v1/attendance-records', $postData)->assertStatus(201);

        // 異常系: バリデーションエラー (422) と仕様書指定の日本語メッセージ検証
        $invalidData = [
            'date' => '', // 必須エラー
            'clock_in' => 'invalid-time', // 形式エラー
        ];
        $response = $this->postJson('/api/v1/attendance-records', $invalidData);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'clock_in'])
            ->assertJsonFragment(['勤怠日は必須です。'])
            ->assertJsonFragment(['出勤時刻は HH:MM:SS 形式で指定してください。']);
    }

    /**
     * AP04: 勤怠更新API (PUT) のテスト
     */
    public function test_can_update_attendance_record_by_owner_or_admin(): void
    {
        $owner = User::factory()->create(['role' => 'general']);
        $otherUser = User::factory()->create(['role' => 'general']);
        $admin = User::factory()->create(['role' => 'admin']);

        $record = Attendance::factory()->create(['user_id' => $owner->id, 'date' => '2026-05-01']);

        // 1. 他の一般ユーザーが更新しようとしたら 403 Forbidden
        Sanctum::actingAs($otherUser, ['*']);
        $this->putJson("/api/v1/attendance-records/{$record->id}", ['comment' => '書き換え'])->assertStatus(403);

        // 2. 本人（所有者）なら 200 OK
        Sanctum::actingAs($owner, ['*']);
        $this->putJson("/api/v1/attendance-records/{$record->id}", ['comment' => '本人の修正'])->assertStatus(200);

        // 3. 管理者（admin）なら他人のデータでも 200 OK (Policyのbeforeが作動)
        Sanctum::actingAs($admin, ['*']);
        $this->putJson("/api/v1/attendance-records/{$record->id}", ['comment' => '管理者の強制修正'])->assertStatus(200);
    }

    /**
     * AP05: 勤怠削除API (DELETE) のテスト
     */
    public function test_can_delete_attendance_record(): void
    {
        $owner = User::factory()->create(['role' => 'general']);
        $record = Attendance::factory()->create(['user_id' => $owner->id]);

        Sanctum::actingAs($owner, ['*']);

        // 正常系 (204)
        $this->deleteJson("/api/v1/attendance-records/{$record->id}")->assertStatus(204);

        // データベースから消えているか確認
        $this->assertDatabaseMissing('attendances', ['id' => $record->id]);
    }
}
