<?php

namespace App\Http\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse
{
    // 成功状态码
    public const CODE_SUCCESS = 0;
    
    // 错误状态码
    public const CODE_ERROR = 1;
    public const CODE_VALIDATION_ERROR = 2;
    public const CODE_UNAUTHORIZED = 401;
    public const CODE_FORBIDDEN = 403;
    public const CODE_NOT_FOUND = 404;
    public const CODE_SERVER_ERROR = 500;

    /**
     * 成功响应
     *
     * @param mixed $data 返回数据
     * @param string $message 提示信息
     * @return JsonResponse
     */
    public static function success($data = null, string $message = '操作成功'): JsonResponse
    {
        return response()->json([
            'code' => self::CODE_SUCCESS,
            'message' => $message,
            'data' => $data
        ], Response::HTTP_OK);
    }

    /**
     * 错误响应
     *
     * @param string $message 错误信息
     * @param int $code 业务错误码
     * @param int $httpCode HTTP 状态码
     * @param mixed $data 额外数据
     * @return JsonResponse
     */
    public static function error(
        string $message = '操作失败',
        int $code = self::CODE_ERROR,
        int $httpCode = Response::HTTP_BAD_REQUEST,
        $data = null
    ): JsonResponse {
        return response()->json([
            'code' => $code,
            'message' => $message,
            'data' => $data
        ], $httpCode);
    }

    /**
     * 参数验证错误
     *
     * @param string $message 错误信息
     * @param mixed $errors 错误详情
     * @return JsonResponse
     */
    public static function validationError(string $message = '参数验证失败', $errors = null): JsonResponse
    {
        return self::error(
            $message,
            self::CODE_VALIDATION_ERROR,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $errors
        );
    }

    /**
     * 未找到响应
     *
     * @param string $message 提示信息
     * @return JsonResponse
     */
    public static function notFound(string $message = '资源不存在'): JsonResponse
    {
        return self::error(
            $message,
            self::CODE_NOT_FOUND,
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * 未授权响应
     *
     * @param string $message 提示信息
     * @return JsonResponse
     */
    public static function unauthorized(string $message = '未授权'): JsonResponse
    {
        return self::error(
            $message,
            self::CODE_UNAUTHORIZED,
            Response::HTTP_UNAUTHORIZED
        );
    }

    /**
     * 禁止访问响应
     *
     * @param string $message 提示信息
     * @return JsonResponse
     */
    public static function forbidden(string $message = '无权限访问'): JsonResponse
    {
        return self::error(
            $message,
            self::CODE_FORBIDDEN,
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * 服务器错误响应
     *
     * @param string $message 错误信息
     * @param mixed $data 错误详情
     * @return JsonResponse
     */
    public static function serverError(string $message = '服务器内部错误', $data = null): JsonResponse
    {
        return self::error(
            $message,
            self::CODE_SERVER_ERROR,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $data
        );
    }
}
