<?php


use BiiiiiigMonster\Hasin\Tests\Models\User;

test('orWhereDoesntHaveIn same as orWhereDoesntHave', function () {
    $orWhereDoesntHave = User::where('age', '>', 18)->orWhereDoesntHave('posts', function ($query) {
        $query->where('votes', '>', 20);
    })->orderBy('id')->pluck('id');
    $orWhereDoesntHaveIn = User::where('age', '>', 18)->orWhereDoesntHaveIn('posts', function ($query) {
        $query->where('votes', '>', 20);
    })->orderBy('id')->pluck('id');

    expect($orWhereDoesntHave)->toEqual($orWhereDoesntHaveIn);
});

test('nested orWhereDoesntHaveIn same as nested orWhereDoesntHave', function () {
    $orWhereDoesntHave = User::where('age', '>', 18)->orWhereDoesntHave('posts', function ($query) {
        $query->where('votes', '>', 20)->orWhereDoesntHave('comments', function ($nestQuery) {
            $nestQuery->where('status', '=', 2);
        });
    })->orderBy('id')->pluck('id');
    $orWhereDoesntHaveIn = User::where('age', '>', 18)->orWhereDoesntHaveIn('posts', function ($query) {
        $query->where('votes', '>', 20)->orWhereDoesntHaveIn('comments', function ($nestQuery) {
            $nestQuery->where('status', '=', 2);
        });
    })->orderBy('id')->pluck('id');

    expect($orWhereDoesntHave)->toEqual($orWhereDoesntHaveIn);
});
