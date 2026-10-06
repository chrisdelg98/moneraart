<?php

declare(strict_types=1);
use Tests\TestCase;

/*
 * Both suites boot the application.
 *
 * Unit tests here still touch config (encryption keys, settings defaults), so a
 * bare PHPUnit TestCase would fail on container resolution. The cost is a few
 * milliseconds per test; the benefit is that a test never fails for a reason
 * that has nothing to do with what it is testing.
 */
pest()->extend(TestCase::class)->in('Feature', 'Unit');
