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
| 1. Public routes      - sign-in, public browsing, payment webhook
| 2. User routes        - require a valid Sanctum bearer token
| 3. Admin routes       - require a Sanctum token belonging to an admin user
*/

// ---------------------------------------------------------------------------
// 1. PUBLIC
// ---------------------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
});

// Public browsing (personalised automatically when a valid token is sent)
Route::get('/needs', [NeedController::class, 'index']);
Route::get('/needs/{id}', [NeedController::class, 'show'])->whereNumber('id');
Route::post('/needs/{id}/click', [NeedController::class, 'incrementClick'])->whereNumber('id')->middleware('throttle:60,1');
Route::post('/needs/{id}/share', [NeedController::class, 'share'])->whereNumber('id')->middleware('throttle:60,1');
Route::get('/needs/{id}/comments', [CommentController::class, 'index'])->whereNumber('id');
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');
Route::get('/ads', [AdController::class, 'index']);
Route::post('/ads/{id}/click', [AdController::class, 'trackClick'])->whereNumber('id')->middleware('throttle:60,1');
Route::post('/ads/{id}/impression', [AdController::class, 'trackImpression'])->whereNumber('id')->middleware('throttle:60,1');
Route::get('/groups', [GroupController::class, 'index']);
Route::get('/groups/{id}', [GroupController::class, 'show'])->whereNumber('id');

// Paystack server-to-server webhook (authenticated by HMAC signature, not a token)
Route::post('/paystack/webhook', [TransactionController::class, 'paystackWebhook']);

// ---------------------------------------------------------------------------
// 2. AUTHENTICATED USER
// ---------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    // Auth & profile
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Needs
    Route::post('/needs', [NeedController::class, 'store']);
    Route::put('/needs/{id}', [NeedController::class, 'update']);
    Route::delete('/needs/{id}', [NeedController::class, 'destroy']);
    Route::post('/needs/{id}/bookmark', [NeedController::class, 'toggleBookmark']);
    Route::post('/needs/{id}/repost', [NeedController::class, 'repost']);
    Route::post('/needs/{id}/recommend', [NeedController::class, 'recommend']);
    Route::post('/needs/{id}/mention-peers', [NeedController::class, 'mentionPeers']);
    Route::post('/needs/{id}/comments', [CommentController::class, 'store']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);
    Route::get('/needs/{id}/chats', [NeedChatController::class, 'index']);
    Route::post('/needs/{id}/chats', [NeedChatController::class, 'store']);

    // Marketplace
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // Bookmarks & connections
    Route::get('/bookmarks', [BookmarkController::class, 'index']);
    Route::get('/connections', [ConnectionController::class, 'index']);
    Route::get('/connections/suggestions', [ConnectionController::class, 'suggestions']);
    Route::get('/connections/pending', [ConnectionController::class, 'pending']);
    Route::post('/connections/request', [ConnectionController::class, 'sendRequest']);
    Route::post('/connections/{id}/accept', [ConnectionController::class, 'acceptRequest']);
    Route::post('/connections/{id}/decline', [ConnectionController::class, 'declineRequest']);
    Route::delete('/connections/{targetUserId}', [ConnectionController::class, 'removeConnection']);

    // Trade groups
    Route::get('/groups/my', [GroupController::class, 'myGroups']);
    Route::post('/groups', [GroupController::class, 'store']);
    Route::put('/groups/{id}', [GroupController::class, 'update']);
    Route::delete('/groups/{id}', [GroupController::class, 'destroy']);
    Route::post('/groups/{id}/join', [GroupController::class, 'join']);
    Route::post('/groups/{id}/leave', [GroupController::class, 'leave']);

    // Messaging
    Route::get('/conversations', [MessageController::class, 'conversations']);
    Route::post('/conversations/start', [MessageController::class, 'startOrGetConversation']);
    Route::get('/conversations/{id}', [MessageController::class, 'showConversation']);
    Route::post('/conversations/{id}/messages', [MessageController::class, 'sendMessage']);

    // Notifications & reports
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/reports', [ReportController::class, 'store']);

    // Ads
    Route::get('/ads/my', [AdController::class, 'myAds']);
    Route::post('/ads', [AdController::class, 'store']);
    Route::put('/ads/{id}/status', [AdController::class, 'toggleStatus']);
    Route::delete('/ads/{id}', [AdController::class, 'destroy']);

    // Wallet, subscriptions & Paystack
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/transactions/deposit', [TransactionController::class, 'deposit']);
    Route::post('/transactions/withdraw', [TransactionController::class, 'withdraw']);
    Route::post('/subscriptions/subscribe', [TransactionController::class, 'subscribe']);
    Route::post('/paystack/initialize', [TransactionController::class, 'initializePaystack']);
    Route::get('/paystack/verify/{reference}', [TransactionController::class, 'verifyPaystack']);

    // Media upload
    Route::post('/upload', [MediaUploadController::class, 'upload'])->middleware('throttle:30,1');
});

// ---------------------------------------------------------------------------
// 3. ADMIN PORTAL
// ---------------------------------------------------------------------------
Route::prefix('admin')->group(function () {
    // Admin sign-in & recovery (public, strictly throttled)
    Route::post('/auth/login', [AdminController::class, 'adminLogin'])->middleware('throttle:5,1');
    Route::post('/auth/send-otp', [AdminController::class, 'sendAdminOtp'])->middleware('throttle:3,1');
    Route::post('/auth/verify-otp', [AdminController::class, 'verifyAdminOtp'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password-otp', [AdminController::class, 'resetAdminPasswordWithOtp'])->middleware('throttle:5,1');

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/auth/me', [AdminController::class, 'adminMe']);
        Route::post('/auth/logout', [AdminController::class, 'adminLogout']);
        Route::post('/auth/change-password', [AdminController::class, 'changeAdminPassword']);

        Route::get('/stats', [AdminController::class, 'stats']);

        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'createUser']);
        Route::put('/users/{id}', [AdminController::class, 'updateUser']);
        Route::delete('/users/{id}', [AdminController::class, 'deleteUser']);

        Route::get('/needs', [AdminController::class, 'needs']);
        Route::post('/needs', [AdminController::class, 'createNeed']);
        Route::put('/needs/{id}/status', [AdminController::class, 'updateNeedStatus']);
        Route::delete('/needs/{id}', [AdminController::class, 'deleteNeed']);

        Route::get('/products', [AdminController::class, 'products']);
        Route::post('/products', [AdminController::class, 'createProduct']);
        Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
        Route::delete('/products/{id}', [AdminController::class, 'deleteProduct']);

        Route::get('/reports', [AdminController::class, 'reports']);
        Route::put('/reports/{id}', [AdminController::class, 'updateReport']);

        Route::get('/ads', [AdminController::class, 'ads']);
        Route::post('/ads', [AdminController::class, 'createAd']);
        Route::put('/ads/{id}', [AdminController::class, 'updateAd']);
        Route::delete('/ads/{id}', [AdminController::class, 'deleteAd']);

        Route::get('/transactions', [AdminController::class, 'transactions']);
        Route::post('/transactions', [AdminController::class, 'createTransaction']);
        Route::put('/transactions/{id}', [AdminController::class, 'updateTransaction']);

        // Trade groups & special syndicates
        Route::get('/groups', [AdminController::class, 'groups']);
        Route::post('/groups', [AdminController::class, 'createGroup']);
        Route::delete('/groups/{id}', [AdminController::class, 'deleteGroup']);

        Route::get('/settings', [AdminController::class, 'getSettings']);
        Route::put('/settings', [AdminController::class, 'updateSettings']);
        Route::get('/profile', [AdminController::class, 'getAdminProfile']);
        Route::put('/profile', [AdminController::class, 'updateAdminProfile']);

        // 30-day profile change queue & special group 3-signature disbursements
        Route::get('/profile-changes', [AdminController::class, 'profileChanges']);
        Route::put('/profile-changes/{id}', [AdminController::class, 'updateProfileChange']);
        Route::get('/group-disbursements', [AdminController::class, 'groupDisbursements']);
        Route::post('/group-disbursements', [AdminController::class, 'createGroupDisbursement']);
        Route::put('/group-disbursements/{id}', [AdminController::class, 'updateGroupDisbursement']);

        Route::get('/notifications', [AdminController::class, 'adminNotifications']);
        Route::put('/notifications/{id}/read', [AdminController::class, 'markAdminNotificationRead']);
        Route::post('/notifications/read-all', [AdminController::class, 'markAllAdminNotificationsRead']);
        Route::delete('/notifications/{id}', [AdminController::class, 'deleteAdminNotification']);
        Route::post('/notifications/broadcast', [AdminController::class, 'broadcastNotification']);
    });
});
