<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Los tests unitarios también arrancan la app: los casts de Eloquent (fechas,
// enums, encrypted) y los servicios de ARCA resuelven config del container.
pest()->extend(TestCase::class)->in('Unit');
