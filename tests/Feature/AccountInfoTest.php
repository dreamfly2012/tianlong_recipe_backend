<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountInfoTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => '旧昵称',
            'email' => 'account@test.com',
            'wechat_nickname' => '旧昵称',
            'wechat_avatar' => null,
        ]);
    }

    public function test_account_info_requires_auth(): void
    {
        $this->getJson('/api/account/info')->assertStatus(401);
        $this->putJson('/api/account/info', ['nickname' => '新昵称'])->assertStatus(401);
    }

    public function test_can_read_current_profile(): void
    {
        $this->user->update(['wechat_avatar' => 'https://cdn.example.com/avatars/me.jpg']);

        Sanctum::actingAs($this->user);

        $this->getJson('/api/account/info')
            ->assertStatus(200)
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.url', 'https://cdn.example.com/avatars/me.jpg')
            ->assertJsonPath('data.user.id', $this->user->id);
    }

    public function test_put_avatar_updates_wechat_avatar_field(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/account/info', [
            'avatarUrl' => 'https://cdn.example.com/avatars/new.jpg',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.url', 'https://cdn.example.com/avatars/new.jpg');

        $this->assertEquals('https://cdn.example.com/avatars/new.jpg', $this->user->fresh()->wechat_avatar);
    }

    public function test_both_avatar_aliases_write_the_same_column(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/account/info', [
            'avatarUrl' => 'https://cdn.example.com/avatars/from_avatar_url.jpg',
            'wechat_avatar' => 'https://cdn.example.com/avatars/from_wechat_avatar.jpg',
        ])->assertStatus(200);

        // 两个字段名等价，同时传时以 avatarUrl 为准，且只写 wechat_avatar 一列。
        $this->assertEquals('https://cdn.example.com/avatars/from_avatar_url.jpg', $this->user->fresh()->wechat_avatar);
    }

    public function test_only_wechat_avatar_alias_is_accepted(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/account/info', [
            'wechat_avatar' => 'https://cdn.example.com/avatars/only_alias.jpg',
        ])->assertStatus(200);

        $this->assertEquals('https://cdn.example.com/avatars/only_alias.jpg', $this->user->fresh()->wechat_avatar);
    }

    public function test_put_nickname_syncs_name_and_wechat_nickname(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/account/info', ['nickname' => '新昵称'])->assertStatus(200);

        $fresh = $this->user->fresh();

        $this->assertEquals('新昵称', $fresh->name);
        $this->assertEquals('新昵称', $fresh->wechat_nickname);
    }

    public function test_relative_avatar_address_is_rejected(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/account/info', [
            'avatarUrl' => 'tmp_abc.jpg',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', '头像地址无效：需要完整的 http(s) 地址');

        $this->assertNull($this->user->fresh()->wechat_avatar);
    }

    public function test_wxfile_avatar_path_asks_for_upload(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/account/info', [
            'avatarUrl' => 'wxfile://tmp_1234.jpg',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', '头像地址无效：请先调用 /api/upload/avatar 上传，再保存返回的地址');
    }

    public function test_put_without_any_updatable_field_fails(): void
    {
        Sanctum::actingAs($this->user);

        $this->putJson('/api/account/info', ['avatarUrl' => ''])
            ->assertStatus(422)
            ->assertJsonPath('message', '没有需要更新的字段');
    }
}
