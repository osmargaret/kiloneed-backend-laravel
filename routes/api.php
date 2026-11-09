<?php

use App\Http\Controllers\Api\AdController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ConnectionController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\MediaUploadController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\NeedChatController;
use App\Http\Controllers\Api\NeedController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\TransactionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Kiloneed (Frontend & Admin Portal)
|--------------------------------------------------------------------------
*/

// Auth & User Profile
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Needs Feed & Operations
Route::get('/needs', [NeedController::class, 'index']);
Route::post('/needs', [NeedController::class, 'store']);
Route::get('/needs/{id}', [NeedController::class, 'show']);
Route::put('/needs/{id}', [NeedController::class, 'update']);
Route::delete('/needs/{id}', [NeedController::class, 'destroy']);
Route::post('/needs/{id}/click', [NeedController::class, 'incrementClick']);
Route::post('/needs/{id}/share', [NeedController::class, 'share']);
Route::post('/needs/{id}/bookmark', [NeedController::class, 'toggleBookmark']);
Route::post('/needs/{id}/repost', [NeedController::class, 'repost']);
Route::post('/needs/{id}/recommend', [NeedController::class, 'recommend']);
Route::post('/needs/{id}/mention-peers', [NeedController::class, 'mentionPeers']);

// Comments on Needs
Route::get('/needs/{id}/comments', [CommentController::class, 'index']);
Route::post('/needs/{id}/comments', [CommentController::class, 'store']);
Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

// In-Need Direct Chats
Route::get('/needs/{id}/chats', [NeedChatController::class, 'index']);
Route::post('/needs/{id}/chats', [NeedChatController::class, 'store']);

// Marketplace Products
Route::get('/products', [ProductController::class, 'index']);
Route::post('/products', [ProductController::class, 'store']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::put('/products/{id}', [ProductController::class, 'update']);
Route::delete('/products/{id}', [ProductController::class, 'destroy']);

// Bookmarks
Route::get('/bookmarks', [BookmarkController::class, 'index']);

// Network Connections
Route::get('/connections', [ConnectionController::class, 'index']);
Route::get('/connections/suggestions', [ConnectionController::class, 'suggestions']);
Route::get('/connections/pending', [ConnectionController::class, 'pending']);
Route::post('/connections/request', [ConnectionController::class, 'sendRequest']);
Route::post('/connections/{id}/accept', [ConnectionController::class, 'acceptRequest']);
Route::post('/connections/{id}/decline', [ConnectionController::class, 'declineRequest']);
Route::delete('/connections/{targetUserId}', [ConnectionController::class, 'removeConnection']);

// Trade Groups
Route::get('/groups', [GroupController::class, 'index']);
Route::get('/groups/my', [GroupController::class, 'myGroups']);
Route::post('/groups', [GroupController::class, 'store']);
Route::get('/groups/{id}', [GroupController::class, 'show']);
Route::post('/groups/{id}/join', [GroupController::class, 'join']);
Route::post('/groups/{id}/leave', [GroupController::class, 'leave']);

// Direct Messages / Conversations
Route::get('/conversations', [MessageController::class, 'conversations']);
Route::post('/conversations/start', [MessageController::class, 'startOrGetConversation']);
Route::get('/conversations/{id}', [MessageController::class, 'showConversation']);
Route::post('/conversations/{id}/messages', [MessageController::class, 'sendMessage']);

// Notifications
Route::get('/notifications', [NotificationController::class, 'index']);
Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

// Reports & Moderation
Route::post('/reports', [ReportController::class, 'store']);

// Sponsored Ads
Route::get('/ads', [AdController::class, 'index']);
Route::get('/ads/my', [AdController::class, 'myAds']);
Route::post('/ads', [AdController::class, 'store']);
Route::post('/ads/{id}/click', [AdController::class, 'trackClick']);

// Wallet, Subscriptions & Transactions (with Paystack Live Integration)
Route::get('/transactions', [TransactionController::class, 'index']);
Route::post('/transactions/deposit', [TransactionController::class, 'deposit']);
Route::post('/transactions/withdraw', [TransactionController::class, 'withdraw']);
Route::post('/subscriptions/subscribe', [TransactionController::class, 'subscribe']);
Route::post('/paystack/initialize', [TransactionController::class, 'initializePaystack']);
Route::get('/paystack/verify/{reference}', [TransactionController::class, 'verifyPaystack']);

// Media Upload
Route::post('/upload', [MediaUploadController::class, 'upload']);

// Admin Portal API Routes
Route::prefix('admin')->group(function () {
    Route::get('/stats', [AdminController::class, 'stats']);

    // Users & KYC
    Route::get('/users', [AdminController::class, 'users']);
    Route::post('/users', [AdminController::class, 'createUser']);
    Route::put('/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

    // Needs Moderation & Official Broadcast
    Route::get('/needs', [AdminController::class, 'needs']);
    Route::post('/needs', [AdminController::class, 'createNeed']);
    Route::put('/needs/{id}/status', [AdminController::class, 'updateNeedStatus']);
    Route::delete('/needs/{id}', [AdminController::class, 'deleteNeed']);

    // Marketplace Moderation
    Route::get('/products', [AdminController::class, 'products']);
    Route::post('/products', [AdminController::class, 'createProduct']);
    Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
    Route::delete('/products/{id}', [AdminController::class, 'deleteProduct']);

    // Trust & Safety Reports
    Route::get('/reports', [AdminController::class, 'reports']);
    Route::put('/reports/{id}', [AdminController::class, 'updateReport']);

    // Sponsored Ads
    Route::get('/ads', [AdminController::class, 'ads']);
    Route::post('/ads', [AdminController::class, 'createAd']);
    Route::put('/ads/{id}', [AdminController::class, 'updateAd']);
    Route::delete('/ads/{id}', [AdminController::class, 'deleteAd']);

    // Revenue & Transactions Ledger
    Route::get('/transactions', [AdminController::class, 'transactions']);
    Route::post('/transactions', [AdminController::class, 'createTransaction']);
    Route::put('/transactions/{id}', [AdminController::class, 'updateTransaction']);

    // Trade Groups & Special Syndicates
    Route::get('/groups', [AdminController::class, 'groups']);
    Route::post('/groups', [AdminController::class, 'createGroup']);
    Route::delete('/groups/{id}', [AdminController::class, 'deleteGroup']);

    // Tenant Settings & Admin Profile
    Route::get('/settings', [AdminController::class, 'getSettings']);
    Route::put('/settings', [AdminController::class, 'updateSettings']);
    Route::get('/profile', [AdminController::class, 'getAdminProfile']);
    Route::put('/profile', [AdminController::class, 'updateAdminProfile']);

    // 30-Day Profile Change Approval Queue & Special Group 3-Sig Disbursements
    Route::get('/profile-changes', [AdminController::class, 'profileChanges']);
    Route::put('/profile-changes/{id}', [AdminController::class, 'updateProfileChange']);
    Route::get('/group-disbursements', [AdminController::class, 'groupDisbursements']);
    Route::post('/group-disbursements', [AdminController::class, 'createGroupDisbursement']);
    Route::put('/group-disbursements/{id}', [AdminController::class, 'updateGroupDisbursement']);

    // Admin Notifications & System Broadcast
    Route::get('/notifications', [AdminController::class, 'adminNotifications']);
    Route::put('/notifications/{id}/read', [AdminController::class, 'markAdminNotificationRead']);
    Route::post('/notifications/read-all', [AdminController::class, 'markAllAdminNotificationsRead']);
    Route::delete('/notifications/{id}', [AdminController::class, 'deleteAdminNotification']);
    Route::post('/notifications/broadcast', [AdminController::class, 'broadcastNotification']);

    // Admin Auth OTP & Forgot Password
    Route::post('/auth/send-otp', [AdminController::class, 'sendAdminOtp']);
    Route::post('/auth/verify-otp', [AdminController::class, 'verifyAdminOtp']);
    Route::post('/auth/reset-password-otp', [AdminController::class, 'resetAdminPasswordWithOtp']);
});
