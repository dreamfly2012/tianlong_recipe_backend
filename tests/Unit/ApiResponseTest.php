<?php

namespace Tests\Unit;

use App\Http\Helpers\ApiResponse;
use Tests\TestCase;

class ApiResponseTest extends TestCase
{
    public function test_success_response(): void
    {
        $response = ApiResponse::success(['data' => 'test'], '操作成功');

        $this->assertEquals(200, $response->status());
        $json = $response->getData(true);
        $this->assertEquals(0, $json['code']);
        $this->assertEquals('操作成功', $json['message']);
        $this->assertEquals(['data' => 'test'], $json['data']);
    }

    public function test_error_response(): void
    {
        $response = ApiResponse::error('错误信息', 1, 400);

        $this->assertEquals(400, $response->status());
        $json = $response->getData(true);
        $this->assertEquals(1, $json['code']);
        $this->assertEquals('错误信息', $json['message']);
    }

    public function test_validation_error_response(): void
    {
        $response = ApiResponse::validationError('参数错误', ['email' => ['邮箱格式不正确']]);

        $this->assertEquals(422, $response->status());
        $json = $response->getData(true);
        $this->assertEquals(2, $json['code']);
        $this->assertEquals('参数错误', $json['message']);
    }

    public function test_not_found_response(): void
    {
        $response = ApiResponse::notFound('资源不存在');

        $this->assertEquals(404, $response->status());
        $json = $response->getData(true);
        $this->assertEquals(404, $json['code']);
    }

    public function test_unauthorized_response(): void
    {
        $response = ApiResponse::unauthorized();

        $this->assertEquals(401, $response->status());
        $json = $response->getData(true);
        $this->assertEquals(401, $json['code']);
    }

    public function test_forbidden_response(): void
    {
        $response = ApiResponse::forbidden('无权限访问');

        $this->assertEquals(403, $response->status());
        $json = $response->getData(true);
        $this->assertEquals(403, $json['code']);
    }

    public function test_server_error_response(): void
    {
        $response = ApiResponse::serverError('服务器内部错误');

        $this->assertEquals(500, $response->status());
        $json = $response->getData(true);
        $this->assertEquals(500, $json['code']);
    }
}
