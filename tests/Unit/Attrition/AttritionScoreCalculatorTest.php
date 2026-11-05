<?php

declare(strict_types=1);

namespace Tests\Tmonier\SyliusPredictiveAnalyticsPlugin\Unit\Attrition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition\AttritionScoreCalculator;
use Tmonier\SyliusPredictiveAnalyticsPlugin\Attrition\AttritionStatus;

/**
 * US-220 : règle explicable du score d'attrition. L'horloge est figée : les seuils sont exacts.
 */
final class AttritionScoreCalculatorTest extends TestCase
{
    private const NOW = '2026-11-05 12:00:00';

    private \DateTimeImmutable $now;

    private AttritionScoreCalculator $calculator;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable(self::NOW);
        $this->calculator = new AttritionScoreCalculator(new MockClock($this->now));
    }

    /**
     * @return iterable<string, array{int, AttritionStatus}>
     */
    public static function thresholds(): iterable
    {
        // Rythme habituel : une commande tous les 21 jours, soit 42 jours (2x) et 63 jours (3x).
        yield 'dans le rythme' => [10, AttritionStatus::Active];
        yield 'juste au-dessous de 2x' => [41, AttritionStatus::Active];
        yield '2x pile reste actif' => [42, AttritionStatus::Active];
        yield 'juste au-dessus de 2x' => [43, AttritionStatus::AtRisk];
        yield 'juste au-dessous de 3x' => [62, AttritionStatus::AtRisk];
        yield '3x pile reste à risque' => [63, AttritionStatus::AtRisk];
        yield 'juste au-dessus de 3x' => [64, AttritionStatus::Critical];
        yield 'très en retard' => [200, AttritionStatus::Critical];
    }

    #[DataProvider('thresholds')]
    public function testStatusFollowsTheTwoAndThreeTimesThresholds(int $daysSinceLastOrder, AttritionStatus $expected): void
    {
        $score = $this->calculator->calculate($this->regularOrders(21, 5, $daysSinceLastOrder));

        self::assertSame($expected, $score->status);
    }

    public function testNominalCaseExposesTheReasonFigures(): void
    {
        $score = $this->calculator->calculate($this->regularOrders(21, 5, 47));

        self::assertSame(AttritionStatus::AtRisk, $score->status);
        self::assertSame(21.0, $score->medianIntervalDays);
        self::assertSame(47, $score->daysSinceLastOrder);
        self::assertSame(5, $score->orderCount);
    }

    public function testMedianOfAnEvenNumberOfIntervalsIsTheMeanOfTheMiddleOnes(): void
    {
        // Intervalles 10, 30, 10, 30 : médiane 20, donc 2x = 40 jours.
        $last = $this->now->modify('-41 days');
        $dates = [
            $this->now->modify('-400 days'),
            $last->modify('-80 days'),
            $last->modify('-70 days'),
            $last->modify('-40 days'),
            $last->modify('-30 days'),
            $last,
        ];

        $score = $this->calculator->calculate($dates);

        self::assertSame(20.0, $score->medianIntervalDays);
        self::assertSame(AttritionStatus::AtRisk, $score->status);
    }

    public function testFewerThanFourOrdersMeansInsufficientHistoryWithoutScore(): void
    {
        $score = $this->calculator->calculate($this->regularOrders(21, 3, 200));

        self::assertSame(AttritionStatus::InsufficientHistory, $score->status);
        self::assertNull($score->medianIntervalDays);
        self::assertNull($score->daysSinceLastOrder);
        self::assertSame(3, $score->orderCount);
    }

    public function testExactlyFourOrdersAreEnoughToScore(): void
    {
        $score = $this->calculator->calculate($this->regularOrders(21, 4, 10));

        self::assertSame(AttritionStatus::Active, $score->status);
    }

    public function testNoOrderMeansInsufficientHistory(): void
    {
        $score = $this->calculator->calculate([]);

        self::assertSame(AttritionStatus::InsufficientHistory, $score->status);
        self::assertSame(0, $score->orderCount);
    }

    public function testOrdersOlderThanTwelveMonthsDoNotCount(): void
    {
        // 3 commandes récentes + 2 commandes d'il y a plus de 12 mois : 3 commandes sur la fenêtre.
        $dates = [
            $this->now->modify('-800 days'),
            $this->now->modify('-700 days'),
            $this->now->modify('-100 days'),
            $this->now->modify('-80 days'),
            $this->now->modify('-60 days'),
        ];

        $score = $this->calculator->calculate($dates);

        self::assertSame(AttritionStatus::InsufficientHistory, $score->status);
        self::assertSame(3, $score->orderCount);
    }

    public function testOrdersOlderThanTwelveMonthsDoNotChangeTheMedian(): void
    {
        // Un saut de 600 jours avant la fenêtre ne doit pas fausser la médiane de 21 jours.
        $score = $this->calculator->calculate($this->regularOrders(21, 5, 10));

        self::assertSame(21.0, $score->medianIntervalDays);
    }

    public function testHistoryShorterThanSixMonthsGivesNoAlertStatus(): void
    {
        // 5 commandes toutes les 10 jours, la première il y a 100 jours : moins de 6 mois d'historique.
        $dates = [];
        for ($i = 4; $i >= 0; --$i) {
            $dates[] = $this->now->modify(sprintf('-%d days', 100 - (4 - $i) * 10));
        }

        $score = $this->calculator->calculate($dates);

        self::assertSame(AttritionStatus::TooRecent, $score->status);
        self::assertFalse($score->status->isScored());
    }

    public function testHistoryOfExactlySixMonthsIsScored(): void
    {
        $first = $this->now->modify('-6 months');
        $dates = [$first, $first->modify('+30 days'), $first->modify('+60 days'), $first->modify('+90 days')];

        self::assertSame(AttritionStatus::Critical, $this->calculator->calculate($dates)->status);
    }

    public function testHistoryOneDayShortOfSixMonthsIsTooRecent(): void
    {
        $first = $this->now->modify('-6 months')->modify('+1 day');
        $dates = [$first, $first->modify('+30 days'), $first->modify('+60 days'), $first->modify('+90 days')];

        self::assertSame(AttritionStatus::TooRecent, $this->calculator->calculate($dates)->status);
    }

    public function testLessThanFourOrdersTakesPrecedenceOverTooRecentHistory(): void
    {
        $dates = [$this->now->modify('-20 days'), $this->now->modify('-10 days')];

        self::assertSame(AttritionStatus::InsufficientHistory, $this->calculator->calculate($dates)->status);
    }

    public function testOrdersOnTheSameDayDoNotMakeEveryDelayCritical(): void
    {
        // Médiane de 0 jour : le rythme est plancher à 1 jour, 3 jours de silence ne sont pas critiques.
        $last = $this->now->modify('-3 days');
        $dates = [$this->now->modify('-400 days'), $last, $last, $last, $last];

        self::assertSame(AttritionStatus::AtRisk, $this->calculator->calculate($dates)->status);
    }

    public function testLastOrderInTheFutureCountsAsZeroDaysSince(): void
    {
        $score = $this->calculator->calculate($this->regularOrders(21, 5, -2));

        self::assertSame(0, $score->daysSinceLastOrder);
        self::assertSame(AttritionStatus::Active, $score->status);
    }

    /**
     * @return list<\DateTimeImmutable> `$count` commandes espacées de `$interval` jours, la dernière il y a `$daysSinceLast`
     *                                  jours, précédées d'une commande ancienne hors fenêtre (historique long)
     */
    private function regularOrders(int $interval, int $count, int $daysSinceLast): array
    {
        $last = $this->now->modify(sprintf('%+d days', -$daysSinceLast));
        $dates = [];
        for ($i = $count - 1; $i >= 0; --$i) {
            $dates[] = $last->modify(sprintf('-%d days', $i * $interval));
        }

        return [$this->now->modify('-800 days'), ...$dates];
    }
}
