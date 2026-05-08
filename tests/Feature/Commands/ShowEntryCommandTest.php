<?php

use Illuminate\Support\Facades\Artisan;

it('outputs error message containing the ID and exits FAILURE for a non-existent ULID', function () {
    Artisan::call('chronicle:show', ['id' => '01FAKEULIDXXXXXXXXX']);

    $output = Artisan::output();

    expect($output)
        ->toContain('01FAKEULIDXXXXXXXXX')
        ->toContain('not found');
});
