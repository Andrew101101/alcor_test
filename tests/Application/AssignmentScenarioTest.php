<?php

declare(strict_types=1);

namespace Tests\Application;

use App\Application\Command\AddManualAdjustment;
use App\Application\Command\AddManualAdjustmentHandler;
use App\Application\Command\CalculateEarningLine;
use App\Application\Command\CalculateEarningLineHandler;
use App\Application\Command\RecalculateEarningLine;
use App\Application\Command\RecalculateEarningLineHandler;
use App\Application\Query\AuditEntry;
use App\Application\Query\EarningLineAudit;
use App\Application\Query\GetEarningLineAudit;
use App\Application\Query\GetEarningLineAuditHandler;
use App\Domain\Comment;
use App\Domain\EarningLineId;
use App\Domain\Money;
use App\Infrastructure\InMemoryEventStore;
use PHPUnit\Framework\TestCase;

/**
 * The assignment's example table, driven end-to-end through commands and read back through the audit query.
 * Every step is a separate command: the line is reloaded from the event store each time.
 */
final class AssignmentScenarioTest extends TestCase
{
    public function testAssignmentTableThroughCommands(): void
    {
        $store = new InMemoryEventStore();
        $calculate = new CalculateEarningLineHandler($store);
        $recalculate = new RecalculateEarningLineHandler($store);
        $adjust = new AddManualAdjustmentHandler($store);
        $getAudit = new GetEarningLineAuditHandler($store);

        $id = EarningLineId::generate();
        $currentValue = static fn (): string => $getAudit(new GetEarningLineAudit($id))->currentValue->getFormatted();

        $calculate(new CalculateEarningLine($id, Money::ofCents(100000)));
        self::assertSame('$1,000.00', $currentValue(), 'step 1');

        $recalculate(new RecalculateEarningLine($id, Money::ofCents(105000)));
        self::assertSame('$1,050.00', $currentValue(), 'step 2');

        $adjust(new AddManualAdjustment($id, Money::ofCents(-4555), Comment::fromString('Employee declined dental benefit; reversing deduction')));
        self::assertSame('$1,004.45', $currentValue(), 'step 3');

        $recalculate(new RecalculateEarningLine($id, Money::ofCents(120000)));
        self::assertSame('$1,004.45', $currentValue(), 'step 4: must be ignored');

        $adjust(new AddManualAdjustment($id, Money::ofCents(10010), Comment::fromString('Late correction: missed approved overtime bonus')));
        self::assertSame('$1,104.55', $currentValue(), 'step 5');

        $adjust(new AddManualAdjustment($id, Money::ofCents(-10), Comment::fromString('Minor rounding adjustment')));
        self::assertSame('$1,104.45', $currentValue(), 'step 6');

        $adjust(new AddManualAdjustment($id, Money::ofCents(-20), Comment::fromString('Second minor rounding adjustment')));
        self::assertSame('$1,104.25', $currentValue(), 'step 7');

        $adjust(new AddManualAdjustment($id, Money::ofCents(20), Comment::fromString('Correcting mistake in adjustment #4')));
        self::assertSame('$1,104.45', $currentValue(), 'step 8');

        // 8 steps, step 4 ignored -> 7 stored events
        self::assertCount(7, $store->load($id));

        // Expected final audit history from the assignment
        $audit = $getAudit(new GetEarningLineAudit($id));
        $this->printAudit($audit);

        self::assertSame('$1,050.00', $audit->systemValue->getFormatted(), 'System value (frozen at step 3)');
        self::assertSame(
            ['-$45.55', '$100.10', '-$0.10', '-$0.20', '$0.20'],
            array_map(static fn (AuditEntry $entry): string => $entry->amount->getFormatted(), $audit->adjustments),
        );
        self::assertSame('$1,104.45', $audit->currentValue->getFormatted(), 'Current (new) value');
        self::assertTrue($audit->isLocked);
    }

    /**
     * Prints the final audit history as a table (to STDERR, so it does not mix with PHPUnit output).
     */
    private function printAudit(EarningLineAudit $audit): void
    {
        $row = static fn (string $entry, string $value, string $comment = ''): string
            => sprintf("│ %-28s │ %12s │ %-55s │\n", $entry, $value, $comment);
        $border = static fn (string $l, string $m, string $r): string
            => $l . str_repeat('─', 30) . $m . str_repeat('─', 14) . $m . str_repeat('─', 57) . $r . "\n";

        $out = "\n" . $border('┌', '┬', '┐')
            . $row('Entry', 'Value', 'Comment')
            . $border('├', '┼', '┤')
            . $row($audit->isLocked ? 'System value (frozen)' : 'System value', $audit->systemValue->getFormatted());

        foreach ($audit->adjustments as $entry) {
            $out .= $row(
                'Adjustment ' . $entry->number,
                ($entry->amount->getAmount() > 0 ? '+' : '') . $entry->amount->getFormatted(),
                $entry->comment,
            );
        }

        $out .= $border('├', '┼', '┤')
            . $row('Current (new) value', $audit->currentValue->getFormatted(), $audit->isLocked ? 'locked: recalculation ignored' : '')
            . $border('└', '┴', '┘');

        fwrite(STDERR, $out);
    }
}
