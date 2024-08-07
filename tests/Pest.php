<?php

uses(\NcooDev\HormLogger\Tests\TestCase::class)->in('Feature');

beforeEach(function () {
    // fresh de la db
    $this->artisan('migrate:fresh');
});
