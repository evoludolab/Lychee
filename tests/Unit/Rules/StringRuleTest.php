<?php

/**
 * SPDX-License-Identifier: MIT
 * Copyright (c) 2017-2018 Tobias Reich
 * Copyright (c) 2018-2025 LycheeOrg.
 */

/**
 * We don't care for unhandled exceptions in tests.
 * It is the nature of a test to throw an exception.
 * Without this suppression we had 100+ Linter warning in this file which
 * don't help anything.
 *
 * @noinspection PhpDocMissingThrowsInspection
 * @noinspection PhpUnhandledExceptionInspection
 */

namespace Tests\Unit\Rules;

use App\Rules\StringRule;
use Tests\AbstractTestCase;

class StringRuleTest extends AbstractTestCase
{
	public function testNegative(): void
	{
		$rule = new StringRule(true);
		$msg = $rule->message();
		$expected = ':attribute must be either null or a non-empty string';

		self::assertEquals($expected, $msg);
	}
}