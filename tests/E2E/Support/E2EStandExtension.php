<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\E2E\Support;

use PHPUnit\Event\Code\Test;
use PHPUnit\Event\Code\TestMethod;
use PHPUnit\Event\Test\AfterLastTestMethodErrored;
use PHPUnit\Event\Test\AfterLastTestMethodErroredSubscriber;
use PHPUnit\Event\Test\BeforeFirstTestMethodErrored;
use PHPUnit\Event\Test\BeforeFirstTestMethodErroredSubscriber;
use PHPUnit\Event\Test\Errored;
use PHPUnit\Event\Test\ErroredSubscriber;
use PHPUnit\Event\Test\Failed;
use PHPUnit\Event\Test\FailedSubscriber;
use PHPUnit\Event\Test\MarkedIncomplete;
use PHPUnit\Event\Test\MarkedIncompleteSubscriber;
use PHPUnit\Event\Test\Passed;
use PHPUnit\Event\Test\PassedSubscriber;
use PHPUnit\Event\Test\Skipped;
use PHPUnit\Event\Test\SkippedSubscriber;
use PHPUnit\Event\TestRunner\ExecutionFinished;
use PHPUnit\Event\TestRunner\ExecutionFinishedSubscriber;
use PHPUnit\Event\TestSuite\Skipped as SuiteSkipped;
use PHPUnit\Event\TestSuite\SkippedSubscriber as SuiteSkippedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * - records every E2E test outcome into E2EReport;
 * - when all tests have executed: removes the provisioned realm/client (if any), then writes
 *   the JSON report (KEYCLOAK_E2E_REPORT, default build/e2e/report.json).
 *
 * Inert for runs without E2E tests: nothing is provisioned and no report is written.
 */
final class E2EStandExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        $facade->registerSubscribers(
            new class implements PassedSubscriber {
                public function notify(Passed $event): void
                {
                    E2EStandExtension::record($event->test(), 'passed');
                }
            },
            new class implements FailedSubscriber {
                public function notify(Failed $event): void
                {
                    E2EStandExtension::record($event->test(), 'failed', $event->throwable()->message());
                }
            },
            new class implements ErroredSubscriber {
                public function notify(Errored $event): void
                {
                    $message = E2EStandExtension::describe($event->throwable());
                    E2EStandExtension::record($event->test(), 'errored', $message);
                }
            },
            new class implements SkippedSubscriber {
                public function notify(Skipped $event): void
                {
                    E2EStandExtension::record($event->test(), 'skipped', $event->message());
                }
            },
            new class implements MarkedIncompleteSubscriber {
                public function notify(MarkedIncomplete $event): void
                {
                    E2EStandExtension::record($event->test(), 'incomplete', $event->throwable()->message());
                }
            },
            new class implements SuiteSkippedSubscriber {
                public function notify(SuiteSkipped $event): void
                {
                    if (E2EReport::isE2EClass($event->testSuite()->name())) {
                        E2EReport::recordTest(
                            class: $event->testSuite()->name(),
                            method: null,
                            status: 'skipped',
                            message: $event->message(),
                            tests: $event->testSuite()->count(),
                        );
                    }
                }
            },
            new class implements BeforeFirstTestMethodErroredSubscriber {
                public function notify(BeforeFirstTestMethodErrored $event): void
                {
                    if (E2EReport::isE2EClass($event->testClassName())) {
                        E2EReport::recordTest(
                            class: $event->testClassName(),
                            method: null,
                            status: 'errored',
                            message: E2EStandExtension::describe($event->throwable()),
                        );
                    }
                }
            },
            new class implements AfterLastTestMethodErroredSubscriber {
                public function notify(AfterLastTestMethodErrored $event): void
                {
                    if (E2EReport::isE2EClass($event->testClassName())) {
                        E2EReport::recordTest(
                            class: $event->testClassName(),
                            method: 'tearDownAfterClass',
                            status: 'errored',
                            message: E2EStandExtension::describe($event->throwable()),
                        );
                    }
                }
            },
            new class implements ExecutionFinishedSubscriber {
                public function notify(ExecutionFinished $event): void
                {
                    E2EStand::destroy();

                    $path = E2EReport::write(E2ESettings::reportPath());
                    if ($path !== null) {
                        fwrite(STDERR, sprintf("[e2e] Report: %s\n", $path));
                    }
                }
            },
        );
    }

    /**
     * @internal used by the anonymous subscribers above
     */
    public static function record(Test $test, string $status, ?string $message = null): void
    {
        if (!$test instanceof TestMethod || !E2EReport::isE2EClass($test->className())) {
            return;
        }

        E2EReport::recordTest(
            class: $test->className(),
            method: $test->methodName(),
            status: $status,
            message: $message,
        );
    }

    /**
     * @internal
     */
    public static function describe(\PHPUnit\Event\Code\Throwable $throwable): string
    {
        $message = $throwable->className() . ': ' . $throwable->message();

        return $throwable->hasPrevious()
            ? $message . ' <- ' . self::describe($throwable->previous())
            : $message;
    }
}
