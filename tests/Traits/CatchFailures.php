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

namespace Tests\Traits;

use Illuminate\Testing\Assert as PHPUnit;
use Illuminate\Testing\TestResponse;

/**
 * This trait allows to retrieve the message returned by the back-end in case of unexpected results.
 * This provides more readable results than: "status code 500 does match expected status code 200".
 */
trait CatchFailures
{
	/**
	 * Some of the exceptions we get are expected. We silence then.
	 *
	 * @var string[]
	 */
	protected array $catchFailureSilence = ["App\Exceptions\MediaFileOperationException"];

	/**
	 * We trim the trace of exceptions to get better data.
	 *
	 * @var string[]
	 */
	protected array $exception_noise = [
		'Illuminate\Database\Query\Builder',
		'Illuminate\Database\Connection',
		'Illuminate\Pipeline\Pipeline',
		'Illuminate\Container\BoundMethod',
		'Illuminate\Container\Util',
		'Illuminate\Container\Container',
	];

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 * @param int|array                                   $expectedStatusCode
	 *
	 * @return void
	 */
	protected function assertStatus(TestResponse $response, int|array $expectedStatusCode): void
	{
		$expectedStatusCodeArray = is_int($expectedStatusCode) ? [$expectedStatusCode] : $expectedStatusCode;

		if ($response->getStatusCode() === 500 && $expectedStatusCode !== 500) {
			$exception = $response->json();
			if (in_array($exception['exception'], $this->catchFailureSilence, true)) {
				return;
			}
			$this->trimException($exception);
			dump($exception);
		// We remove 204 as it does not have content
		// We remove 302 because it does not have json data.
		} elseif (!in_array($response->getStatusCode(), [204, 302, ...$expectedStatusCodeArray], true)) {
			$exception = $response->json();
			$this->trimException($exception);
		}
		PHPUnit::assertContains($response->getStatusCode(), $expectedStatusCodeArray);
	}

	/**
	 * An exception is an array of the shape:
	 * array{message:string, exception:string, file:string, line:int, trace:array{}, previous_exception: obj }
	 * Unfortunately the trace contains the full call stack and dumping it completely does not add significant
	 * information. Most of the time only the first 3 values of the trace are of interest.
	 *
	 * For this reason this function only keeps the first 3 values of the trace of the exception returned.
	 *
	 * Additionally, this transformation is applied recursively on the previous_exception in the case of
	 * exception encapsulation.
	 *
	 * @param array|null $exception
	 *
	 * @return void
	 */
	private function trimException(array|null &$exception): void
	{
		if (!is_array($exception)) {
			return;
		}

		if (isset($exception['trace'])) {
			$exception['trace'] = array_values(array_filter($exception['trace'], fn ($t) => !in_array($t['class'] ?? '', $this->exception_noise, true)));
			$exception['trace'] = array_slice($exception['trace'], 0, 3);
		}

		if (isset($exception['previous_exception'])) {
			$this->trimException($exception['previous_exception']);
		}
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertOk(TestResponse $response): void
	{
		$this->assertStatus($response, 200);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertCreated(TestResponse $response): void
	{
		$this->assertStatus($response, 201);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertNoContent(TestResponse $response): void
	{
		$this->assertStatus($response, 204);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertRedirect(TestResponse $response): void
	{
		$this->assertStatus($response, 302);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertUnauthorized(TestResponse $response): void
	{
		$this->assertStatus($response, 401);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertSupporterRequired(TestResponse $response): void
	{
		$this->assertStatus($response, 402);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertForbidden(TestResponse $response): void
	{
		$this->assertStatus($response, 403);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertNotFound(TestResponse $response): void
	{
		$this->assertStatus($response, 404);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertConflict(TestResponse $response): void
	{
		$this->assertStatus($response, 409);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	protected function assertUnprocessable(TestResponse $response): void
	{
		$this->assertStatus($response, 422);
	}

	/**
	 * @param TestResponse<\Illuminate\Http\JsonResponse> $response
	 *
	 * @return void
	 */
	public function assertInternalServerError(TestResponse $response): void
	{
		$this->assertStatus($response, 500);
	}
}