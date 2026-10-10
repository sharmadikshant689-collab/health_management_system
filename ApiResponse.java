package com.curatech;

public record ApiResponse(boolean success, String message, Object data) {
    public static ApiResponse success(String message, Object data) {
        return new ApiResponse(true, message, data);
    }

    public static ApiResponse failure(String message) {
        return new ApiResponse(false, message, null);
    }
}
