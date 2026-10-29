<?php

declare(strict_types=1);

use App\Models\Sales\Sale;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;

it('no usa borrado lógico en ventas', function () {
    expect(Schema::hasColumn('sales', 'deleted_at'))->toBeFalse()
        ->and(in_array(SoftDeletes::class, class_uses_recursive(Sale::class), true))->toBeFalse();
});
