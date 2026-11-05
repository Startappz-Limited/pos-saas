<?php

use App\Enums\StockIntakeQualityStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockIntake;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/*
 * An intake's quality status is derived from its quantities, never chosen
 * (decided 5 November 2026): nothing rejected is passed, some of each is
 * partial, nothing accepted is failed. It is set on every save; a status sent
 * with the form is ignored. Intake is web-only: neither the flat API nor
 * /api/v1 has an intake endpoint.
 */

beforeEach(function () {
    $this->user = assignToNewShops(User::factory()->create());
    foreach (['stock-intake.view', 'stock-intake.create', 'stock-intake.update'] as $name) {
        Permission::findOrCreate($name);
    }
    $this->user->givePermissionTo(['stock-intake.view', 'stock-intake.create', 'stock-intake.update']);
    $this->actingAs($this->user);

    $this->item = PurchaseOrderItem::factory()->create([
        'purchase_order_id' => PurchaseOrder::factory()->ordered()->create()->id,
        'quantity_ordered' => 100,
        'quantity_remaining' => 100,
    ]);
});

dataset('outcomes', [
    'nothing rejected' => [10, 0, StockIntakeQualityStatus::PASSED],
    'some of each' => [8, 2, StockIntakeQualityStatus::PARTIAL],
    'nothing accepted' => [0, 10, StockIntakeQualityStatus::FAILED],
]);

it('derives the status from the quantities', function (int $accepted, int $rejected, StockIntakeQualityStatus $expected) {
    expect(StockIntakeQualityStatus::fromQuantities($accepted, $rejected))->toBe($expected)
        ->and(StockIntakeQualityStatus::fromQuantities((string) $accepted.'.00', (string) $rejected.'.00'))->toBe($expected);
})->with('outcomes');

it('sets the status on create', function (int $accepted, int $rejected, StockIntakeQualityStatus $expected) {
    $this->post(route('stock-intakes.store'), [
        'purchase_order_item_id' => $this->item->id,
        'quantity_received' => 10,
        'quantity_accepted' => $accepted,
        'quantity_rejected' => $rejected,
    ])->assertSessionHasNoErrors();

    expect(StockIntake::where('purchase_order_item_id', $this->item->id)->sole()->quality_status)->toBe($expected);
})->with('outcomes');

it('sets the status on edit', function (int $accepted, int $rejected, StockIntakeQualityStatus $expected) {
    $intake = StockIntake::factory()->pending()->create(['quantity_received' => 10, 'quantity_accepted' => 5, 'quantity_rejected' => 5]);

    $this->put(route('stock-intakes.update', $intake), [
        'intake_date' => $intake->intake_date->toDateString(),
        'quantity_received' => 10,
        'quantity_accepted' => $accepted,
        'quantity_rejected' => $rejected,
    ])->assertSessionHasNoErrors();

    expect($intake->fresh()->quality_status)->toBe($expected);
})->with('outcomes');

it('ignores a submitted status, on create and on edit', function () {
    $this->post(route('stock-intakes.store'), [
        'purchase_order_item_id' => $this->item->id,
        'quantity_received' => 10,
        'quantity_accepted' => 8,
        'quantity_rejected' => 2,
        'quality_status' => 'passed',
    ])->assertSessionHasNoErrors();
    $intake = StockIntake::where('purchase_order_item_id', $this->item->id)->sole();
    expect($intake->quality_status)->toBe(StockIntakeQualityStatus::PARTIAL);

    $this->put(route('stock-intakes.update', $intake), [
        'intake_date' => $intake->intake_date->toDateString(),
        'quantity_received' => 10,
        'quantity_accepted' => 10,
        'quantity_rejected' => 0,
        'quality_status' => 'excellent',
    ])->assertSessionHasNoErrors();
    expect($intake->fresh()->quality_status)->toBe(StockIntakeQualityStatus::PASSED);
});

it('shows the derived status read-only on both forms, never a choice', function () {
    $this->get(route('stock-intakes.create'))->assertOk()
        ->assertDontSee('name="quality_status"', false)->assertSee('quality-status-display', false)->assertSee('Passed');

    $intake = StockIntake::factory()->pending()->create(['quantity_received' => 10, 'quantity_accepted' => 0, 'quantity_rejected' => 10]);
    $this->get(route('stock-intakes.edit', $intake))->assertOk()
        ->assertDontSee('name="quality_status"', false)->assertSee('Failed');
    $this->get(route('stock-intakes.show', $intake))->assertOk()->assertSee('Failed');
});

it('recomputes every existing intake from its quantities, and changes nothing on a second run', function () {
    $intakes = collect([
        ['excellent', 10, 0, 'passed'],
        ['acceptable', 8, 2, 'partial'],
        ['passed', 8, 2, 'partial'],
        ['rejected', 0, 10, 'failed'],
        [null, 10, 0, 'passed'],
    ])->map(function (array $row) {
        $intake = StockIntake::factory()->create(['quantity_received' => 10, 'quantity_accepted' => $row[1], 'quantity_rejected' => $row[2]]);
        DB::table('stock_intakes')->where('id', $intake->id)->update(['quality_status' => $row[0]]);

        return [$intake->id, $row[3]];
    });
    StockIntake::find($intakes->first()[0])->delete(); // soft-deleted intakes are recomputed too

    $migration = require database_path('migrations/2026_11_05_000001_derive_stock_intake_quality_status.php');
    $migration->up();
    $migration->up();

    foreach ($intakes as [$id, $expected]) {
        expect(DB::table('stock_intakes')->where('id', $id)->value('quality_status'))->toBe($expected);
    }
});
