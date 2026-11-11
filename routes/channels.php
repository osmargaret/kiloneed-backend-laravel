<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Direct 1-on-1 user notification and message channel
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Needs discussion channel
Broadcast::channel('need.{needId}', function ($user, $needId) {
    return true;
});

// Trade group channel
Broadcast::channel('group.{groupId}', function ($user, $groupId) {
    return true;
});
